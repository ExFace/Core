/**
 * Facade-independent JSONEditor adapter. UXON context is evaluated on every request,
 * so schema, prototype and object can be linked to other widgets.
 *
 * Requires JSONEditor, jQuery and exfTools; UXON popups also require picoModal.
 */
(function (oGlobal) {
    "use strict";

    // Server validation waits for a typing pause on top of JSONEditor's own 150 ms debounce.
    var VALIDATION_DELAY_MS = 700;

    // AJAX dialogs may evaluate this library again when reopening the editor.
    var oScrollPositions = oGlobal._uxonEditorScrollPos = oGlobal._uxonEditorScrollPos || {};
    var oCollapsedNodes = oGlobal._uxonEditorCollapsedNodes = oGlobal._uxonEditorCollapsedNodes || {};

    function normalizeValue(mValue) {
        if (mValue === undefined || mValue === null || mValue === '') {
            return '{}';
        }
        return typeof mValue === 'string' ? mValue : JSON.stringify(mValue);
    }

    function emptyValue(sValue) {
        return /^\s*(\{\s*\}|\[\s*\])\s*$/.test(sValue) ? '' : sValue;
    }

    function create(domEditor, oConfig, mValue) {
        var $ = oGlobal.jQuery;
        var JSONEditor = oConfig.JSONEditor || oGlobal.JSONEditor;
        var picoModal = oConfig.picoModal || oGlobal.picoModal;
        var ace = JSONEditor.ace || oGlobal.ace;
        var oEditor;
        var bDestroyed = false;
        var bSettingValue = false;
        var bDisabled = oConfig.disabled === true;
        var sLastValue;
        var bChangeScheduled = false;
        var aModals = [];
        var aRequests = [];
        var aTimers = [];
        var jqEditor = $(domEditor);
        var jqHint;
        var jqUnavailable;
        // Only the latest validation and autosuggest request matter - older ones are aborted.
        var oPendingValidation = null;
        var oAutosuggestAbort = null;

        function t(sKey) {
            return oConfig.translations[sKey];
        }

        function createNoResultsMessage() {
            return $('<div class="uxoneditor-search-info" role="status"></div>').text(t('SEARCH.NO_RESULTS'));
        }

        function isServerAvailable() {
            return oGlobal.navigator.onLine !== false &&
                (!oGlobal.exfLauncher || oGlobal.exfLauncher.isOnline()) &&
                (!oConfig.serverAvailable || oConfig.serverAvailable());
        }

        function refreshServerAvailability() {
            if (bDestroyed) {
                return false;
            }
            var bAvailable = isServerAvailable();
            if (oEditor && oConfig.uxon && (oConfig.autosuggest || oConfig.validate !== false)) {
                if (!jqUnavailable) {
                    jqUnavailable = $('<span class="exf-jsoneditor-unavailable" role="status" tabindex="0"></span>')
                        .text('\u26a0')
                        .attr('title', t('SERVER_UNAVAILABLE'))
                        .attr('aria-label', t('SERVER_UNAVAILABLE'));
                }
                var jqMenu = jqEditor.find('.jsoneditor-menu');
                var jqParent = jqMenu.length ? jqMenu : jqEditor;
                if (jqUnavailable.parent()[0] !== jqParent[0]) {
                    jqUnavailable.appendTo(jqParent);
                }
                jqUnavailable.toggleClass('exf-jsoneditor-unavailable-floating', jqMenu.length === 0)
                    .prop('hidden', bAvailable);
                if (!bAvailable) {
                    delete oEditor._autosuggestLastResult;
                    jqEditor.find('[id$="_warning"]').remove();
                }
                updatePresetHint();
            }
            return bAvailable;
        }

        function reportError(oError) {
            if (bDestroyed) {
                return;
            }
            console.error(oError);
            if (reportAjaxError(oError.response)) {
                return;
            }
            if (oConfig.onError) {
                oConfig.onError(oError);
            }
        }

        function reportAjaxError(oRequest, oModal) {
            if (bDestroyed || (oModal && oModal._exfClosed) || !oConfig.onAjaxError ||
                !oRequest || typeof oRequest.responseText !== 'string' ||
                !oRequest.responseText.trim().startsWith('<')) {
                return false;
            }
            oConfig.onAjaxError(oRequest, oModal ? oModal.modalElem() : null);
            return true;
        }

        function later(fnCallback, iDelay) {
            var iTimer = setTimeout(function () {
                aTimers = aTimers.filter(function (iItem) { return iItem !== iTimer; });
                if (!bDestroyed) {
                    fnCallback();
                }
            }, iDelay);
            aTimers.push(iTimer);
            return iTimer;
        }

        function cancelLater(iTimer) {
            clearTimeout(iTimer);
            aTimers = aTimers.filter(function (iItem) { return iItem !== iTimer; });
        }

        /**
         * Stops a scheduled or running server validation.
         *
         * Its promise is resolved empty: JSONEditor ignores results of superseded
         * validations anyway, but must not be left waiting or receive an error.
         */
        function cancelValidation() {
            var oPending = oPendingValidation;
            if (!oPending) {
                return;
            }
            oPendingValidation = null;
            cancelLater(oPending.timer);
            if (oPending.abort) {
                oPending.abort.abort();
            }
            oPending.resolve([]);
        }

        /**
         * Validates UXON on the server once the user stopped typing.
         *
         * Every call supersedes the previous one, so fast typing produces a single
         * request instead of one per short pause.
         */
        function validateLater(oJson) {
            cancelValidation();
            return new Promise(function (fnResolve, fnReject) {
                var oPending = {resolve: fnResolve, abort: null};
                oPendingValidation = oPending;
                oPending.timer = later(function () {
                    if (!refreshServerAvailability()) {
                        oPendingValidation = null;
                        fnResolve([]);
                        return;
                    }
                    oPending.abort = new AbortController();
                    exf_performValidation('', JSON.stringify(oJson), oPending.abort.signal).then(function (aErrors) {
                        if (oPendingValidation !== oPending) return;
                        oPendingValidation = null;
                        if (!refreshServerAvailability()) {
                            fnResolve([]);
                            return;
                        }
                        if (!Array.isArray(aErrors)) {
                            throw new Error(t('ERROR.MALFORMED_RESPONSE'));
                        }
                        exf_toggleErrorWarning($, domEditor.id, aErrors[0]);
                        fnResolve(aErrors);
                    }).catch(function (oError) {
                        // Superseded or destroyed - the promise was resolved by cancelValidation()
                        if (oPending.abort.signal.aborted) return;
                        oPendingValidation = null;
                        if (!refreshServerAvailability()) {
                            fnResolve([]);
                            return;
                        }
                        reportError(oError);
                        fnReject(oError);
                    });
                }, VALIDATION_DELAY_MS);
            });
        }

        function notifyChange() {
            if (!oEditor || bSettingValue || bDestroyed) {
                return;
            }
            var sValue = oApi.getValue();
            if (sValue !== sLastValue) {
                sLastValue = sValue;
                if (oConfig.onChange) {
                    oConfig.onChange(sValue);
                }
            }
            updatePresetHint();
        }

        function scheduleChange() {
            if (bSettingValue || bChangeScheduled) {
                return;
            }
            // Ace replacements emit removal and insertion in the same JS turn.
            bChangeScheduled = true;
            queueMicrotask(function () {
                bChangeScheduled = false;
                notifyChange();
            });
        }

        function updatePresetHint() {
            if (!jqHint || !oEditor) {
                return;
            }
            jqHint.toggle(isServerAvailable() && !bDisabled && oEditor.getMode() === 'tree' && isTreeEmpty());
        }

        /**
         * Checks for an empty root object/array without serializing the whole tree.
         *
         * Runs on every focus, pointer and server request, so it must stay cheap
         * for large UXON. Primitive roots have no `childs` and are not empty.
         */
        function isTreeEmpty() {
            var oRoot = oEditor.node;
            return !!oRoot && Array.isArray(oRoot.childs) && oRoot.childs.length === 0;
        }

        function applyDisabled() {
            if (!oEditor) {
                return;
            }
            var sMode = oEditor.getMode();
            if (bDisabled && sMode === 'tree') {
                oEditor.setMode('view');
            } else if (!bDisabled && sMode === 'view') {
                oEditor.setMode('tree');
            }
            if (oEditor.aceEditor) {
                oEditor.aceEditor.setReadOnly(bDisabled);
            }
            updatePresetHint();
        }

        function rememberCollapsedNodes() {
            if (!oConfig.scrollKey || bSettingValue || !oEditor || oEditor.getMode() === 'code' || !oEditor.node) {
                return;
            }
            var aPaths = [];
            function collect(oNode) {
                if (!oNode.childs) return;
                if (!oNode.expanded) aPaths.push(oNode.getPath());
                oNode.childs.forEach(collect);
            }
            collect(oEditor.node);
            oCollapsedNodes[domEditor.id + ':' + oConfig.scrollKey] = aPaths;
        }

        function restoreCollapsedNodes() {
            if (!oConfig.scrollKey || !oEditor.node || oEditor.getMode() === 'code') {
                return;
            }
            var aPaths = oCollapsedNodes[domEditor.id + ':' + oConfig.scrollKey];
            if (aPaths === undefined) return;
            oEditor.expandAll();
            aPaths.forEach(function (aPath) {
                var oNode = oEditor.node.findNodeByPath(aPath);
                if (oNode && oNode.childs) oNode.collapse(false);
            });
        }

        function restoreScroll() {
            if (!oConfig.scrollKey) {
                return;
            }
            var sKey = domEditor.id + ':' + oConfig.scrollKey;
            var jqScroller = jqEditor.find('.jsoneditor-tree');
            jqScroller.off('scroll.exfJsonEditor').on('scroll.exfJsonEditor', function () {
                if (!bDestroyed && jqScroller[0].isConnected) {
                    oScrollPositions[sKey] = jqScroller.scrollTop();
                }
            });
            if (oScrollPositions[sKey] !== undefined) {
                later(function () { jqScroller.scrollTop(oScrollPositions[sKey]); }, 100);
            }
        }

        function request(oData, oModal) {
            if (!refreshServerAvailability()) {
                showServerUnavailable(oModal);
                return $.Deferred().reject({}, 'abort', 'unavailable').promise();
            }
            $(oModal.modalElem()).find('.uxoneditor-server-unavailable').remove();
            var oRequest = $.ajax({
                type: 'POST',
                url: oConfig.ajaxUrl,
                dataType: 'json',
                data: oData
            });
            aRequests.push(oRequest);
            oRequest.always(function () {
                aRequests = aRequests.filter(function (oItem) { return oItem !== oRequest; });
            });
            oModal.afterClose(function () { oRequest.abort(); });
            return oRequest;
        }

        function showServerUnavailable(oModal) {
            if (bDestroyed || oModal._exfClosed) return;
            var jqModal = $(oModal.modalElem());
            jqModal.find('.spinner').hide();
            if (jqModal.find('.uxoneditor-server-unavailable').length === 0) {
                $('<div class="uxoneditor-search-info uxoneditor-server-unavailable" role="status"></div>')
                    .text(t('SERVER_UNAVAILABLE')).insertAfter(jqModal.find('.pico-modal-header'));
            }
        }

        function exf_fetchAutosuggest(text, path, input, uxon, oSignal){
            var formData = new URLSearchParams({
        		action: 'exface.Core.UxonAutosuggest',
        		text: text,
        		path: JSON.stringify(path),
        		input: input,
        		schema: oConfig.schema(),
                prototype: oConfig.rootPrototype(),
                object: oConfig.rootObject(),
        		uxon: uxon
        	});
        	return fetch(oConfig.ajaxUrl,
                {
                    method: "POST",
                    mode: "cors",
                    cache: "no-cache",
                    signal: oSignal,
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
        	      body: formData, // body data type must match "Content-Type" header
        	   }
            )
          	.then(exf_validateResponse);
        }

        function exf_performValidation(path, uxon, oSignal){
            var formData = new URLSearchParams({
        		action: 'exface.Core.UxonValidate',
        		path: JSON.stringify(path),
        		schema: oConfig.schema(),
                prototype: oConfig.rootPrototype(),
                object: oConfig.rootObject(),
        		uxon: uxon
        	});
        	return fetch(oConfig.ajaxUrl,
                {
                    method: "POST",
                    mode: "cors",
                    cache: "no-cache",
                    signal: oSignal,
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
        	      body: formData, // body data type must match "Content-Type" header
        	   }
            )
          	.then(exf_validateResponse);
        }

        function exf_validateResponse(response) {
            if (
                response
                && response.ok
                && response.status === 200
                && response.headers
                && /^application\/json(?:;|$)/i.test(response.headers.get('content-type') || '')
            ) {
                return response.json();
            } else {
                return response.text().then(function (sBody) {
                    return Promise.reject({
                        message: t("ERROR.MALFORMED_RESPONSE"),
                        response: {
                            status: response.status,
                            statusText: response.statusText,
                            responseText: sBody
                        }
                    });
                });
            }
        }

        function exf_openModal(
            title,
            contentHTML,
            focus = false,
            cssClass = '',
            onAfterCreate
        ){
            if (!onAfterCreate) {
                onAfterCreate = function(modal) {
                };
            }
            var contentStr =
                '<div class="pico-modal-contents">' +
                '   <div class="pico-modal-header">' + title + '</div>' +
                    contentHTML +
                '</div>';

            var oModal = picoModal({
                parent: oConfig.modalParent || window.document.body,
                title: title,
                content: contentStr,
                overlayClass: 'jsoneditor-modal-overlay',
                modalClass: 'jsoneditor-modal uxoneditor-modal ' + cssClass,
                focus: focus
            })
            .afterCreate(function (oModal) {
                if (oConfig.onModal) {
                    oConfig.onModal(oModal.modalElem(), oModal.overlayElem());
                }
                onAfterCreate(oModal);
            })
            .afterClose(function (modal) {
                modal._exfClosed = true;
                aModals = aModals.filter(function (oItem) { return oItem !== modal; });
                notifyChange();
                modal.destroy();
            });
            aModals.push(oModal);
            return oModal.show();

        }

        function exf_getNodeFromTarget(target){
           while (target) {
        	    if (target.node) {
        	       return target.node;
        	    }
        	    target = target.parentNode;
           }

           return undefined;
        }

        function exf_focusFirstChildValue(node, onlyEmptyValues){
            if (onlyEmptyValues === undefined) {
                onlyEmptyValues = false;
            }
        	var child, found;
        	for (var i in node.childs) {
        		child = node.childs[i];
        		if ((child.type === 'string' || child.type === 'auto') && (onlyEmptyValues === false || child.getValue() === undefined || child.getValue() === '')) {
        			child.focus(child.getField() ? 'value' : 'field');
                    return child;
        		} else {
        			found = exf_focusFirstChildValue(child, onlyEmptyValues);
                    if (found) {
                        return found;
                    }
        		}
        	}
        	return false;
        }

        function exf_onBlur(event){
            var editor = event.data.jsonEditor;
        	var node = exf_getNodeFromTarget(this);
        	if (node.getValue() !== '') {
        		return;
        	}
        	var path = node.getPath();
        	var prop = path[path.length-1];
            var val;
            var tpl;
            var type = 'auto';
        	if (editor._autosuggestLastResult && editor._autosuggestLastResult.templates) {
        		tpl = editor._autosuggestLastResult.templates[prop];
        		if (tpl) {
        			try {
                        val = JSON.parse(tpl);
                        type = (Array.isArray(val) ? 'array' : 'object');
                    } catch (e) {
                        val = tpl;
                        if (tpl.includes("\n")) {
        			        node.setValue(val, type);
                            exf_openAceModal(node);
                            return;
                        }
                    }
        			node.setValue(val, type);
        			node.expand(true);
        			exf_focusFirstChildValue(node);
        		}
        	}
        }

        function exf_onClickValue(event){
            var editor = event.data.jsonEditor;
        	var oNode = exf_getNodeFromTarget(this);
            var sVal = oNode.getValue();
            if (typeof sVal === 'string' && sVal.includes("\n")) {
                exf_openAceModal(oNode);
            }
        }

          function exf_addHelpButton($, editorId, url, title) {
            var helpBtn = $(
                '<button type="button" class="exf-jsoneditor-help" style="background: transparent;"><i ' +
                'class="fa fa-question-circle-o" style="font-size: 22px"></i></button>'
            );
            helpBtn.attr('title', t('HELP'));

            $('#' + editorId + ' .jsoneditor-menu .jsoneditor-search').before(helpBtn);

            var helpBtnContent = '<iframe src="' + url + '"></iframe>';
            helpBtn.click(function() {
                return exf_openModal(
                    title,
                    helpBtnContent,
                    false,
                     "jsoneditor-modal-nopadding jsoneditor-modal-maximized"
                );
            });
        }

        function exf_toggleErrorWarning($, editorId, error) {
            var button = $('#' + editorId + '_warning').remove();

            if(error === undefined || error === null || error === "") {
                return;
            }

            var warning = $(
                '<button id="' + editorId +'_warning" title="ERROR - Click for details." style="background: transparent; color:#F3B316;"><i ' +
                'class="fa fa-exclamation-triangle" style="font-size: 22px"></i></button>'
            );

            $('#' + editorId + ' .jsoneditor-menu .jsoneditor-search').before(warning);

            var pathHtml = error.path.length === 0 ? '' :
                '<p><b>Path:</b> ' + error.path.join(' . ') + '</p>';
            var contentHtml =
                '<div>' +
                    pathHtml +
                    '<p><b>Error:</b> ' + error.message + '</p>' +
                '</div>';
            warning.click(function() {
                return exf_openModal(
                    "Warning",
                    contentHtml,
                    false,
                    "jsoneditor-modal"
                );
            });
        }

        function exf_getJsonPathViewContent(){
            var jsonPathViewContent =
                '<div style="display:flex; gap:8px; align-items:center;">' +
                '   <textarea id="jsonPathView" ' +
                '      class="preset-path uxoneditor-input"' +
                '      readonly> ' +
                '   </textarea>' +
                '   <div>' +
                '      <button id="jsonPathCopyBtn" class="uxoneditor-btn" title="' + t("CONTEXT_MENU.CLIPBOARD.COPY_HINT") + '">' +
                '         ' + t("CONTEXT_MENU.CLIPBOARD.COPY") +
                '       </button>' +
                '   </div>' +
                '</div>';
            return jsonPathViewContent;
        }

        function exf_initJsonPathCopy(modal){
            var pathView = modal.modalElem().querySelector('#jsonPathView');
            var pathViewCopyBtn = modal.modalElem().querySelector('#jsonPathCopyBtn');
            if (!pathView || !pathViewCopyBtn) return;

            pathViewCopyBtn.addEventListener('click', async function () {
                var text = pathView.value || '';

                exfTools.clipboard.copyText(text);

                if (oConfig.onSuccess) {
                    oConfig.onSuccess(t("CONTEXT_MENU.CLIPBOARD.COPY_SUCCESS"));
                }
            });
        }

        function exf_filterAutosuggest(aSuggestions, sSearch) {
            if (Array.isArray(aSuggestions) === false) {
                return aSuggestions;
            }
           sSearch = sSearch.toLowerCase();
            var aFiltered = [ [],[],[] ];
            var aResult = [];
            var iLen = aSuggestions.length;
            aSuggestions.forEach(function(sVal){
                var sValLower = (sVal || '').toLowerCase();
                switch (true) {
                    case sValLower.startsWith(sSearch):
                        aFiltered[0].push(sVal);
                        break;
                    case sValLower.indexOf('.' + sSearch) > -1:
                        aFiltered[1].push(sVal);
                        break;
                    default:
                        aFiltered[2].push(sVal);
                }
            });

            aFiltered.forEach(function(aPart){
                aResult = aResult.concat(aPart);
            });

            return aResult;
        }

        function exf_convertToJsonPath(aPath){
            let str = '$';
            aPath.forEach(function(crumb) {
               if (crumb.toString().match(/\d+/)) {
                   str += '[' + crumb + ']';
               } else {
                   str += '.' + crumb;
               }
            });
            return str;
        }
        function exf_convertToArrayPath(jsonPathString){
            let aSplit = jsonPathString.split('.');
            let aPath = [];
            aSplit.forEach(function(sPart){
                if (sPart === '$') return;
                if (sPart.endsWith(']')) {
                    let sPartSplit = sPart.split('[');
                    aPath.push(sPartSplit[0]);
                    aPath.push(sPartSplit[1].slice(0, -1));
                } else {
                    aPath.push(sPart);
                }
            });
            return aPath;
        }

        function exf_openJsonPathViewModal(node) {
            return exf_openModal(
                t("JSON_PATH"),
                exf_getJsonPathViewContent(),
                false,
                "jsoneditor-modal jsonPathView",
                function(modal) {
                    exf_loadJsonPathView(modal, node);
                    exf_initJsonPathCopy(modal);
                }
            );
        }

        function exf_openPasteModal(node) {
            return exf_openModal(
                t("CONTEXT_MENU.CLIPBOARD.PASTE_DIALOG_TITLE"),
                '<div>' +
                '   <textarea class="uxoneditor-input"></textarea>' +
                '</div>',
                false,
                "jsoneditor-modal jsonPathView",
                function(modal) {
                    var oModalElem = modal.modalElem();
                    var menuNode = node;
                    var jqTextArea = $(oModalElem).find('textarea').first();
                    later(function() {
                        jqTextArea.focus();
                    }, 0);
                    oModalElem.addEventListener("paste", function() {
                        later(function() {
                            var sPasted = jqTextArea.val();
                            try {
                                var json = JSON.parse(sPasted);
                                menuNode.setValue(json);
                                menuNode.expand(true);
                            } catch (e) {
                                menuNode.setValue(sPasted);
                            }
                            modal.close();
                        }, 0);
                    });
                }
            );
        }

        function exf_openAceModal(node) {
            return exf_openModal(
                t("CONTEXT_MENU.EDITOR.WINDOW"),
                '   <div class="uxoneditor-value-editor" style="height: calc(100% - 40px)">' + node.getValue().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;") + '</div>' +
                '   <div class="jsoneditor-jmespath-block jsoneditor-modal-actions">' +
                '      <div class="action-buttons">' +
                '          <input class="uxoneditor-input uxoneditor-btn-ok" autofocus type="submit" value="' + t("BUTTON_OK") + '"/>' +
                '          <input class="uxoneditor-input uxoneditor-btn-cancel" type="submit" value="' + t("BUTTON_CANCEL") + '" />' +
                '      </div>' +
                '   </div>',
                false,
                "jsoneditor-modal jsoneditor-modal-maximized",
                function(modal) {
                    var editor = ace.edit(modal.modalElem().querySelector(".uxoneditor-value-editor"), {
                        wrap: true
                    });
                    modal.afterClose(function () { editor.destroy(); });
                    editor.focus();

                    modal.modalElem().querySelector(".uxoneditor-btn-ok").onclick = function() {
                        node.setValue(editor.getValue());
                        node.focus('value');
                        modal.close();
                    };

                    modal.modalElem().querySelector(".uxoneditor-btn-cancel").onclick = function() {
                        modal.close();
                    };
                }
            );
        }

        function exf_openPresetsModal(node){
            if (!refreshServerAvailability()) return;
            return exf_openModal(
                t("PRESETS.TITLE"),
                exf_getPresetsBtnContent(),
                false,
                'jsoneditor-modal jsoneditor-modal-maximized',
                function(modal) {
                    return exf_loadPresets(modal, node);
                }
            );
        }

        function exf_openDetailsModal(node){
            if (!refreshServerAvailability()) return;
            return exf_openModal(
                t("WIDGET_DETAILS"),
                exf_getDetailsBtnContent(),
                false,
                'jsoneditor-modal jsoneditor-modal-maximized',
                function(modal) {
                    return exf_loadDetails(modal, node);
                }
            );
        }

        function exf_loadJsonPathView(oModal, oNode){
            // get node path tree
            var oShowPathElem = oModal.modalElem().querySelector('#jsonPathView');
            var aPath = oNode.getPath();
            oShowPathElem.value = exf_convertToJsonPath(aPath);
            oShowPathElem.title = oNode.editor.options.name + (aPath.length > 0 ? ' > ' : '') + aPath.join(' > ');
        }

        function exf_insertIntoArray(aTargetArray, iPosition, oObject){
            return aTargetArray.splice(iPosition, 0, oObject);
        }

        function exf_getRootNodeValue(){
            let rootNode = exf_getNodeFromTarget(jqEditor.find('.jsoneditor-tree tr:first-of-type td:last-of-type .jsoneditor-readonly').get()[0]);
            return ( rootNode === undefined ) ? null  : rootNode.getValue();
        }

        function exf_getNodeType(node){
           if ( node === null || node.type === null ) {
               return 'root';
           }

           switch(node.type.toLowerCase()) {
               case 'auto'  : return 'auto';
               case 'string': return 'string';
               case 'array' : return 'array';
               case 'object': return 'object';
               default      : return 'undefined';
           }
        }

        function exf_autoWidth(element){
            var jqEl = $(element);
            later(function(){
                var fSiblingsWidth = 0;
                var fParentWidth = jqEl.parent().width();
                jqEl.siblings().each(function(){
                    fSiblingsWidth += $(this).outerWidth(true);
                });
                jqEl.css('width', 'calc(100% - ' + (fSiblingsWidth + 10) + 'px)');
            }, 0);
        }

        function exf_getPresetsBtnContent(node){
            return  '   <div class="jsoneditor-jmespath-block" style="width: 100%; height: calc(100% - 18px - 25px - 35px - 4px)">' +
                    '       <div style="height: 100%; width: calc(70% - 15px); float: left; margin-right: 15px">' +
                    '           <input class="uxoneditor-input" id="uxonPresetSearch"></input>' +
                    '           <div class="spinner"></div>' +
                    '           <div id="uxonPresetCards" class="uxoneditor-preset-cards">' +
                    '           </div>' +
                    '       </div>' +
                    '       <div style="width: 30%; height: 100%; display: inline-block">' +
                    '          <div class="jsoneditor-jmespath-label">' + t("PRESETS.PREVIEW") + ' </div>' +
                    '          <div class="jsoneditor-jmespath-block">' +
                    '              <div id="uxonPresetDescription" class="markdown-body" style="height: 180px; overflow: auto;"></div>' +
                    '          </div>' +
                    '          <div class="jsoneditor-jmespath-block" style="height: calc(100% - 10px - 188px - 25px - 10px)">' +
                    '              <div class="uxoneditor-preset-preview" style="height: 100%"> </div>' +
                    '          </div>' +
                    '       </div>' +
                    '   </div>' +
                    '   <div class="jsoneditor-jmespath-label">' + t("PRESETS.USE_PRESET_AT") + ' </div>' +
                    '   <div class="jsoneditor-jmespath-block jsoneditor-modal-actions">' +
                    '       <input class="uxoneditor-input" id="uxonPresetPath" style="margin-right: 4px;" readonly></input>' +
                    '       <div class="action-buttons">' +
                    '         <input class="uxoneditor-input uxoneditor-preset-replace" autofocus disabled type="submit" value="' + t("PRESETS.BUTTON_REPLACE") + '"/>' +
                    '         <input class="uxoneditor-input uxoneditor-preset-wrap" disabled type="submit" value="' + t("PRESETS.BUTTON_WRAP") + '"   />' +
                    '         <input class="uxoneditor-input uxoneditor-preset-prepend" disabled type="submit" value="' + t("PRESETS.BUTTON_PREPEND") + '"/>' +
                    '         <input class="uxoneditor-input uxoneditor-preset-append" disabled type="submit" value="' + t("PRESETS.BUTTON_APPEND") + '" />' +
                    '         <input class="uxoneditor-input uxoneditor-preset-cancel" type="submit" value="' + t("BUTTON_CANCEL") + '" />' +
                    '       </div>' +
                    '   </div>';
        }

        function exf_loadPresets(modal, node){

            var oPreviewEditor = new JSONEditor(
                modal.modalElem().querySelector(".uxoneditor-preset-preview"),
                {
                    mode: 'view',
                    modes: ['code', 'view'],
                    mainMenuBar: true,
                    navigationBar: false
                }
            );
            modal.afterClose(function () { oPreviewEditor.destroy(); });

            // get node path tree
            var path = node.getPath();
            var nodeType = exf_getNodeType(node);
            var parentNodeType = exf_getNodeType(node.parent);
            var nodeIsWrappingTarget;
            var aPresetData = [];
            var wrapData = {};

            // Wrap button enabled if node type is object
            if ( nodeType === 'object' || nodeType === 'root' ) {
                nodeIsWrappingTarget = true;
            }
            else {
                nodeIsWrappingTarget = false;
            }

            var hasArrayContext = (node.parent !== null && node.parent.type === 'array') ? true : false;

            var oNodePathElem = modal.modalElem().querySelector('#uxonPresetPath');
            exf_autoWidth(oNodePathElem);
            oNodePathElem.value = exf_convertToJsonPath(path);
            oNodePathElem.title = node.editor.options.name + (path.length > 0 ? ' > ' : '') + path.join(' > ');

            request({
                    action: 'exface.Core.UxonAutosuggest',
                    path: JSON.stringify(path),
                    input: 'preset',
                    schema: oConfig.schema(),
                    prototype: oConfig.rootPrototype(),
                    object: oConfig.rootObject(),
                    uxon: node.editor.getText()
                }, modal)
            .done(function(data, textStatus, jqXHR) {
                if (bDestroyed || modal._exfClosed) return;
                // Fill path textarea
                var elem = modal.modalElem();
                var aPresetOptions = [];
                var length = data.length;
                var jqTileContainer = $(elem.querySelector('#uxonPresetCards'));

                var fnListTiles = function(aPresetData, jqTileContainer, fnClick, sSearch) {
                    sSearch = sSearch === undefined ? '' : sSearch.toLowerCase();
                    var sGroupName = '';

                    jqTileContainer.empty().prev('.spinner').show();

                    aPresetData.forEach(function(oRow){
                        if (! oRow['PROTOTYPE__LABEL']) {
                           oRow['PROTOTYPE__LABEL'] = t("PRESETS.GROUP_GENERAL");
                        }

                        if (sSearch !== '') {
                            if (! oRow['NAME'].toLowerCase().includes(sSearch) && ! oRow['PROTOTYPE__LABEL'].toLowerCase().includes(sSearch)) {
                                return;
                            }
                        }

                        if (sGroupName !== oRow['PROTOTYPE__LABEL']) {
                            $('<div class="uxoneditor-preset-group" style="clear: both; width: 100%; font-weight: bold; font-size: 120%; padding: 10px 0 5px 0;">' + oRow['PROTOTYPE__LABEL'] + '</div>').appendTo(jqTileContainer);
                            sGroupName = oRow['PROTOTYPE__LABEL'];
                        }

                        if (oRow['THUMBNAIL']) {
                            $(  '<div class="uxoneditor-preset-card">' +
                                '   <img class="uxoneditor-preset-pic" src="vendor/' + oRow['THUMBNAIL'] + '"></img>' +
                                '   <div class="uxoneditor-preset-name">' + oRow['NAME'] + '</div>' +
                                '</div>'
                            ).data('presetData', oRow).appendTo(jqTileContainer);
                        } else {
                            $(  '<div class="uxoneditor-preset-card text-only">' +
                                '   <div class="uxoneditor-preset-name">' + oRow['NAME'] + '</div>' +
                                '</div>'
                            ).data('presetData', oRow).appendTo(jqTileContainer);
                        }
                    });

                    var jqCards = jqTileContainer.children('.uxoneditor-preset-card');
                    jqCards.click(function(event) {fnClick(event, this, $(this).data('presetData'));});
                    if (jqCards.length === 0) {
                        createNoResultsMessage().appendTo(jqTileContainer);
                    }

                    jqTileContainer.prev('.spinner').hide();
                }

                var fnClickTile = function(event, card, oPresetData) {
                    var jqCard = $(card);
                    var oPresetWrapBtn = modal.modalElem().querySelector("#presetWrap");

                    jqTileContainer.children().removeClass('selected');
                    jqCard.addClass('selected');

                    oPreviewEditor.setText(oPresetData['UXON']);

                    modal.modalElem().querySelector('#uxonPresetDescription').innerHTML = oPresetData['DESCRIPTION'];
                    oPreviewEditor.expandAll(true);
                    modal.modalElem().querySelector(".uxoneditor-preset-replace").disabled = false;

                    // Check if clicked editor node is object and preset is a wrapper
                    if ( oPresetData.WRAP_FLAG === "1" && nodeIsWrappingTarget ) {
                        modal.modalElem().querySelector(".uxoneditor-preset-wrap").disabled = false;
                        wrapData = oPresetData;
                    } else {
                        modal.modalElem().querySelector(".uxoneditor-preset-wrap").disabled = true;
                    }
                    modal.modalElem().querySelector(".uxoneditor-preset-replace").disabled = false;

                    if(hasArrayContext) {
                        modal.modalElem().querySelector(".uxoneditor-preset-prepend").disabled = false;
                        modal.modalElem().querySelector(".uxoneditor-preset-append").disabled = false;
                    } else{
                        modal.modalElem().querySelector(".uxoneditor-preset-prepend").disabled = true;
                        modal.modalElem().querySelector(".uxoneditor-preset-append").disabled = true;
                    }
                };

                aPresetData = data;
                fnListTiles(aPresetData, jqTileContainer, fnClickTile);
                $(modal.modalElem()).find('#uxonPresetSearch').focus().on('input', function(){
                    var sSearch = $(this).val();
                    fnListTiles(aPresetData, jqTileContainer, fnClickTile, sSearch);
                });

            }) // done
            .fail( function (jqXHR, textStatus, errorThrown) {
                if (!refreshServerAvailability()) {
                    showServerUnavailable(modal);
                    return;
                }
                if (textStatus !== "abort" && !bDestroyed && !modal._exfClosed) {
                    modal.modalElem().querySelector('.spinner').style.display = 'none';
                    if (!reportAjaxError(jqXHR, modal)) {
                        reportError(new Error(t("ERROR.SERVER_ERROR") + ": " + errorThrown));
                    }
                }

            } ); // fail

            var exf_replaceNodeValue = function(oNode, mJson, oModal){
               if (oNode.type === 'array' && oNode.childs.length === 0) {
                    mJson = (typeof mJson === 'string' || mJson instanceof String) ? '[' + mJson + ']' : [mJson];
               }
               oNode.update(mJson);
               oNode.expand(true);
               exf_focusFirstChildValue(oNode, true);
               updatePresetHint();
               oModal.close();

            }; // var

            var exf_insertAtPosition = function(oParentNode, iTargetPosition, sJsonPreset,  oModal)
            {
                var aJsonParentNode = oParentNode.getValue();
                aJsonParentNode.splice(iTargetPosition, 0, sJsonPreset);
                exf_replaceNodeValue(oParentNode, aJsonParentNode, oModal);
            };

            var presetReplace = modal.modalElem().querySelector(".uxoneditor-preset-replace");
            presetReplace.onclick = function() {
                exf_replaceNodeValue(node, oPreviewEditor.get(), modal);
            };

            var presetPrepend = modal.modalElem().querySelector(".uxoneditor-preset-prepend");
            presetPrepend.onclick = function(){
                exf_insertAtPosition(node.parent, node.getIndex(), oPreviewEditor.get(), modal);
            };

            var presetAppend = modal.modalElem().querySelector(".uxoneditor-preset-append");
            presetAppend.onclick = function(){
                exf_insertAtPosition(node.parent, node.getIndex()+1, oPreviewEditor.get(), modal);
            };

            var presetWrap = modal.modalElem().querySelector(".uxoneditor-preset-wrap");
            presetWrap.onclick = function(){
               var jsonPath = wrapData['WRAP_PATH'];
               var oWrapTargetNode = oPreviewEditor.node.findNodeByPath(exf_convertToArrayPath(jsonPath));
               if (oWrapTargetNode === undefined){
                   alert("Wrapping target node within preset not found - please check preset configuration.");
                   return;
               }

              var val = node.getValue();
              oWrapTargetNode.setValue(val, (Array.isArray(val) ? 'array' : 'object'));
              exf_replaceNodeValue(node, oPreviewEditor.get(), modal);
            };

            var presetCancel = modal.modalElem().querySelector(".uxoneditor-preset-cancel");
            presetCancel.onclick = function() {
                modal.close();
            };
        }

        function exf_getDetailsBtnContent(node){

            return  '   <p class="uxoneditor-object-details-title" style="display:none"></p>' +
                    '   <div class="uxoneditor-object-details-description markdown-body" style="display:none"></div>' +
                    '   <table class="uxoneditor-table">' +
                    '       <thead>' +
                    '           <tr>' +
                    '               <th style="text-align: center"><i class="fa fa-eye"></i></th>' +
                    '               <th> </th>' +
                    '               <th>' + t("DETAILS.PROPERTY") + '</th>' +
                    '               <th>' + t("DETAILS.VALUE") + '</th>' +
                    '               <th>' + t("DETAILS.DEFAULT") + '</th>' +
                    '               <th>' + t("DETAILS.DESCRIPTION") + '</th>' +
                    '               <th>' + t("DETAILS.REQUIRED") + '</th>' +
                    '           </tr>' +
                    '       </thead>' +
                    '       <tbody>' +
                    '       </tbody>' +
                    '   </table>' +
                    '   <div style="width: calc(100% - 20px); padding: 0 0 20px 0; text-align: center;">' +
                    '       <div class="spinner" style="width: 32px; height: 32px"></div>' +
                    '   </div>' +
                    '   <div class="jsoneditor-jmespath-block jsoneditor-modal-actions">' +
                    '      <input class="uxoneditor-input" id="uxonPresetPath" style="margin-right: 4px;" readonly></input>' +
                    '      <div class="action-buttons">' +
                    '          <input class="uxoneditor-input uxoneditor-btn-ok" autofocus type="submit" value="' + t("BUTTON_OK") + '"/>' +
                    '          <input class="uxoneditor-input uxoneditor-btn-cancel" type="submit" value="' + t("BUTTON_CANCEL") + '" />' +
                    '      </div>' +
                    '   </div>';
        }

        function exf_loadDetails(modal, node){

            var path = node.getPath();

            var oNodePathElem = modal.modalElem().querySelector('#uxonPresetPath');
            exf_autoWidth(oNodePathElem);
            oNodePathElem.value = exf_convertToJsonPath(path);
            oNodePathElem.title = node.editor.options.name + (path.length > 0 ? ' > ' : '') + path.join(' > ');

            var jqTableBody = $(modal.modalElem().querySelector('.uxoneditor-table > tbody'));
            request({
                    action: 'exface.Core.UxonAutosuggest',
                    path: JSON.stringify(path),
                    input: 'details',
                    schema: oConfig.schema(),
                    prototype: oConfig.rootPrototype(),
                    uxon: node.editor.getText()
                }, modal)
            .done(function(oResponse, sTextStatus, jqXHR) {
                if (bDestroyed || modal._exfClosed) return;
                var aData = oResponse.properties || [];
                var sObjectTitle = oResponse.title;
                var sObjectDescription = oResponse.description;
                var oCurrentValues = [];
                var val, sVal, oFieldData, iPos = 0;

                modal.modalElem().querySelector('.pico-modal-header').innerHTML = oResponse.prototype_schema.charAt(0).toUpperCase() + oResponse.prototype_schema.slice(1) + ' "' + oResponse.alias + '"';
                modal.modalElem().querySelector('.spinner').parentNode.style.display = 'none';

                node.childs.forEach(function(oChildNode){
                    if (oCurrentValues[oChildNode.getField()] !== undefined) return;

                    val = oChildNode.getValue();
                    if (Array.isArray(val) === true) {
                        sVal = '[' + val.length + ' ' + t("DETAILS.VALUE_ARRAY_ITEMS") + ']';
                    } else if (typeof val === 'object') {
                        sVal = '{' + t("DETAILS.VALUE_OBJECT") + '}'
                    } else {
                        sVal = val;
                    }
                    oCurrentValues[oChildNode.getField()] = sVal;

                    // Make explicitly set properties appear at the top of the list
                    // in the same order as in the editor.
                    aData.forEach(function(oRow, i){
                        if (oRow['PROPERTY'] == oChildNode.getField()) {
                            aData.splice(iPos, 0, aData.splice(i, 1)[0]);
                            return;
                        }
                    });
                    iPos++;
                });


                var sMoreLink;
                if (sObjectTitle){
                    $(modal.modalElem()).find('.uxoneditor-object-details-title').append(sObjectTitle);
                    $(modal.modalElem()).find('.uxoneditor-object-details-title').toggle();
                }

                if (sObjectDescription){
                    $(modal.modalElem()).find('.uxoneditor-object-details-title').append(' <a class="uxoneditor-button-show-more" href="javascript:;">[' + t("DETAILS.SHOW_MORE") + ']</a>');
                    $(modal.modalElem()).find('.uxoneditor-object-details-description').append(sObjectDescription);
                    $(modal.modalElem()).find('.uxoneditor-button-show-more').on('click', function(oEvent){
                        $(modal.modalElem()).find('.uxoneditor-object-details-description').toggle();
        			});
                }



                var sBtnRowDetails = "";
                var iLength = aData.length;
                for(var i = 0; i < iLength; i++){
                    var oRow = aData[i];

                    if(oRow['DESCRIPTION'] == ""){
                        sBtnRowDetails = '   <td></td>';
                    } else {
                        sBtnRowDetails =        '   <td><button class="btn-row-description"><i class="fa fa-info-circle" aria-hidden="true"></i></button></td>';
                    }



                    jqTableBody.append($(
                        '<tr>' +
                        '   <td style="text-align: center"><input class="uxoneditor-checkbox" type="checkbox" name="' + oRow['PROPERTY'] + '" ' + (oCurrentValues[oRow['PROPERTY']] !== undefined ? 'checked ' : '') + '></input></td>' +
                        sBtnRowDetails +
                        '   <td>' + oRow['PROPERTY'] + '</td>' +
                        '   <td style="font-style: italic;">' + (oCurrentValues[oRow['PROPERTY']] || '') + '</td>' +
                        '   <td>' + (oRow['DEFAULT'] || '') + '</td>' +
                        '   <td>' + (oRow['TITLE'] || '') + '</td>' +
                        '   <td style="text-align: center;">' + (oRow['REQUIRED'] ? '<i class="fa fa-check" aria-hidden="true"></i>' : '') + '</td>' +
                        '</tr>' +
                        '<tr style="display: none;" class="row-details">' +
                        '   <td></td>' +
                        '   <td colspan="6" class="row-description markdown-body">' + oRow['DESCRIPTION'] + ' </td>' +
                        '</tr>'
                    ));
                }


            }) // done
            .fail( function (jqXHR, textStatus, errorThrown) {
                if (!refreshServerAvailability()) {
                    showServerUnavailable(modal);
                    return;
                }
                if (textStatus !== "abort" && !bDestroyed && !modal._exfClosed &&
                    !reportAjaxError(jqXHR, modal)) {
                    reportError(new Error(t("ERROR.SERVER_ERROR") + ": " + errorThrown));
                }

            } ); // fail

            modal.modalElem().querySelector(".uxoneditor-btn-ok").onclick = function() {
                var oContentOld = node.getValue(), oContentNew = {};
                jqTableBody.find('.uxoneditor-checkbox:checked').each(function(){
                    if (oContentOld[this.name] === undefined) {
                        oContentNew[this.name] = '';
                    } else {
                        oContentNew[this.name] = oContentOld[this.name];
                    }
                });
                node.setValue(oContentNew);
                node.expand(true);
                exf_focusFirstChildValue(node, true);
                modal.close();
            };

            modal.modalElem().querySelector(".uxoneditor-btn-cancel").onclick = function() {
                modal.close();
            };

            jqTableBody.on('click', '.btn-row-description', function(oEvent){
                $(oEvent.target).closest('tr').next("tr").toggle();
			});

        }

        function exf_openModelModal(node){
            if (!refreshServerAvailability()) return;
            return exf_openModal(
                t("MODELBROWSER.TITLE"),
                '   <div class="uxoneditor-btn-group">' +
                        '<div class="uxoneditor-input" id="uxonModelBrowserBase" style="border-right: none;"></div>' +
                        '<div class="uxoneditor-input" id="uxonModelBrowserPath" style="border-left: none;"></div>' +
                        '<button class="uxoneditor-btn" id="uxonModelBrowserBack" title="' + t("MODELBROWSER.RELATION_BACK") + '" /><i class="fa fa-step-backward" aria-hidden="true"></i></button>' +
                    '</div>' +
                '   <input class="uxoneditor-input" id="uxonModelBrowserSearch" style="width: initial;"></input>' +
                '   <div class="uxoneditor-model-error" role="alert" hidden></div>' +
                '   <table class="uxoneditor-table uxoneditor-table-selectable">' +
                '       <thead>' +
                '           <tr>' +
                '               <th>' + t("MODELBROWSER.NAME") + '</th>' +
                '               <th>' + t("MODELBROWSER.DESCRIPTION") + '</th>' +
                '               <th>' + t("MODELBROWSER.SUGGESTION") + '</th>' +
                '           </tr>' +
                '       </thead>' +
                '       <tbody>' +
                '       </tbody>' +
                '   </table>' +
                '   <div style="width: calc(100% - 20px); padding: 0 0 20px 0; text-align: center;">' +
                '       <div class="spinner" style="width: 32px; height: 32px"></div>' +
                '   </div>' +
                '   <div class="jsoneditor-jmespath-block jsoneditor-modal-actions">' +
                '      <input class="uxoneditor-input" id="uxonPresetPath" style="margin-right: 4px;" readonly></input>' +
                '      <div class="action-buttons">' +
                '          <input class="uxoneditor-input uxoneditor-btn-ok" autofocus type="submit" value="' + t("BUTTON_OK") + '"/>' +
                '          <input class="uxoneditor-input uxoneditor-btn-cancel" type="submit" value="' + t("BUTTON_CANCEL") + '" />' +
                '      </div>' +
                '   </div>',
                false,
                'jsoneditor-modal jsoneditor-modal-maximized',
                function(modal) {
                    return exf_loadModelBrowser(modal, node);
                }
            );
        }

        function exf_loadModelBrowser(modal, node) {
            var path = node.getPath();
            var sPath = node.getValue();
            var sSearch = '';
            var sDelim = '__';
            var iSplitPos = -1;
            var oNodePathElem = modal.modalElem().querySelector('#uxonPresetPath');
            var jqTableBody = $(modal.modalElem().querySelector('.uxoneditor-table > tbody'));
            var domError = modal.modalElem().querySelector('.uxoneditor-model-error');
            var jqInfo = createNoResultsMessage().prop('hidden', true).insertBefore(jqTableBody.closest('table'));
            var oCache = {};

            function showError(sMessage, oResponse) {
                if (bDestroyed || modal._exfClosed) return;
                console.error(sMessage, oResponse);
                domError.textContent = sMessage;
                domError.hidden = false;
                modal.modalElem().querySelector('.spinner').parentNode.style.display = 'none';
            }

            function responseError(oResponse, sFallback) {
                var oError = oResponse && oResponse.error;
                if (!oError || typeof oError !== 'object') {
                    return sFallback;
                }
                return [oError.title, oError.message, oError.hint, oError.logid]
                    .filter(function (sPart) { return typeof sPart === 'string' && sPart !== ''; })
                    .join('\n') || sFallback;
            }

            iSplitPos = sPath.lastIndexOf(sDelim);
            if (iSplitPos > -1) {
                sPath = sPath.substring(0, iSplitPos + sDelim.length);
            } else {
                sPath = '';
            }

            iSplitPos = sSearch.lastIndexOf(':');
            if (iSplitPos > -1) {
                sSearch = sSearch.substring(0, iSplitPos);
            }

            exf_autoWidth(oNodePathElem);

            oNodePathElem.value = exf_convertToJsonPath(path);
            oNodePathElem.title = node.editor.options.name + (path.length > 0 ? ' > ' : '') + path.join(' > ');

            $(modal.modalElem()).find('#uxonModelBrowserBack').click(function(){
                var sPath = $(modal.modalElem()).find('#uxonModelBrowserPath').text();
                var aPath = sPath.split(sDelim);
                if (aPath.length > 0) {
                    sPath = aPath.slice(0, -2).join(sDelim);
                    if (sPath !== '') {
                        sPath += sDelim;
                    }
                    fnSearch(jqTableBody, '',  sPath);
                }
            });

            var fnLoad = function(sPath) {
                return request({
                        action: 'exface.Core.UxonAutosuggest',
                        path: JSON.stringify(path),
                        input: 'modelbrowser',
                        text: sPath,
                        schema: oConfig.schema(),
                        prototype: oConfig.rootPrototype(),
                        uxon: node.editor.getText()
                    }, modal)
                .done(function(oResponse, sTextStatus, jqXHR) {
                if (bDestroyed || modal._exfClosed) return;
                    var oData = oResponse || {};
                    var aRows = [];
                    var sTitle = '';
                    var sDescription = '';

                    if (oData.error || oData._TYPE !== 'object' ||
                        (oData.ATTRIBUTES !== undefined && !Array.isArray(oData.ATTRIBUTES))) {
                        showError(responseError(oData, t("ERROR.MALFORMED_RESPONSE")), oData);
                        return;
                    }
                    switch (oData['_TYPE']) {
                        case 'object':
                            sTitle = oData._BASE_OBJECT_NAME + ' (' + oData._BASE_OBJECT_ALIAS_NS + ')';
                            sDescription = oResponse.DESCRIPTION;
                            aRows = oData.ATTRIBUTES === undefined ? [] : oData.ATTRIBUTES;
                            oCache[sPath] = aRows;
                            break;
                    }

                    modal.modalElem().querySelector('.pico-modal-header').innerHTML = sTitle;
                    $(modal.modalElem()).find('#uxonModelBrowserBase').html(oData._BASE_OBJECT_ALIAS + ':');

                    return aRows;
                }) // done
                .fail( function (jqXHR, textStatus, errorThrown) {
                    if (!refreshServerAvailability()) {
                        showServerUnavailable(modal);
                        return;
                    }
                    if (textStatus === "abort") return;
                    var sStatus = [jqXHR.status, errorThrown || jqXHR.statusText || textStatus].filter(Boolean).join(' ');
                    showError(responseError(jqXHR.responseJSON, sStatus), jqXHR);
                    reportAjaxError(jqXHR, modal);
                } ); // fail
            };

            var fnSearch = function(jqTableBody, sSearch, sPath) {
                if (bDestroyed || modal._exfClosed) return;
                var aData = oCache[sPath];
                domError.hidden = true;
                domError.textContent = '';
                jqInfo.prop('hidden', true);
                jqTableBody.empty();
                $(modal.modalElem()).find('#uxonModelBrowserSearch').val(sSearch);
                $(modal.modalElem()).find('#uxonModelBrowserPath').text(sPath);
                if (aData === undefined) {
                    modal.modalElem().querySelector('.spinner').parentNode.style.display = 'block';
                    fnLoad(sPath).then(function() {
                        if (bDestroyed || modal._exfClosed) return;
                        modal.modalElem().querySelector('.spinner').parentNode.style.display = 'none';
                        if (oCache[sPath] !== undefined) {
                            fnSearch(jqTableBody, sSearch, sPath);
                        }
                    });
                } else {
                    aData.forEach(function(oRow) {
                        if (sSearch !== '') {
                            sSearch = sSearch.toLowerCase();
                            if (! oRow['NAME'].toLowerCase().includes(sSearch) && ! oRow['ALIAS'].toLowerCase().includes(sSearch)) {
                                return;
                            }
                        }

                        jqTableBody.append($(
                            '<tr data-suggest="' + oRow['_SUGGEST'] + '">' +
                            '   <td>' + oRow['NAME'] + (oRow['_RELATION'] ? ' <button class="uxoneditor-follow-relation" title="' + t("MODELBROWSER.RELATION_FOLLOW") + '" data-relation="' + oRow['_SUGGEST'] + '"/><i class="fa fa-step-forward" aria-hidden="true"></i></button>' : '') + '</td>' +
                            '   <td>' + (oRow['DESCRIPTION'] || '') + '</td>' +
                            '   <td>' + oRow['_SUGGEST'] + '</td>' +
                            '</tr>'
                        ));
                    });

                    jqTableBody.find('button.uxoneditor-follow-relation').click(function() {
                        var sPath = $(this).data('relation');
                        fnSearch(jqTableBody, '', sPath);
                    });

                    jqTableBody.find('tr').click(function() {
                        jqTableBody.find('tr').removeClass('selected');
                        $(this).addClass('selected');
                    });
                    jqInfo.prop('hidden', jqTableBody.children().length !== 0);
                }
            }

            fnSearch(jqTableBody, sSearch, sPath);

            $(modal.modalElem()).find('#uxonModelBrowserSearch').focus().on('input', function(){
                fnSearch(jqTableBody, $(this).val(), $(modal.modalElem()).find('#uxonModelBrowserPath').text());
            });

            modal.modalElem().querySelector(".uxoneditor-btn-ok").onclick = function() {
                var sSelected = jqTableBody.find('tr.selected').data('suggest');
                if (sSelected) {
                    node.setValue(sSelected);
                }
                modal.close();
            };

            modal.modalElem().querySelector(".uxoneditor-btn-cancel").onclick = function() {
                modal.close();
            };

        }
        var fnCreateMenu = function (items, node){
            var path = node.path;
            var rootNode = exf_getNodeFromTarget( $('#' + domEditor.id + ' .jsoneditor-tree tr:first-of-type td:last-of-type .jsoneditor-readonly').get()[0]);
            var menuNode = path.length > 0 ? rootNode.findNodeByPath(node.path) : rootNode;

            var val = menuNode.getValue();
            var menuNodeType = exf_getNodeType(menuNode);

            var editMenu;

            // Change append/insert behavoir
            // - Append/insert object will result in {"": ""} instead of an empty object
            // - Append/insert auto inside an array - also
            items.forEach(function(oItem){
                var fnClick = oItem.click;
                if (oItem.className === "jsoneditor-insert" && oItem.submenu) {
                    var fnOnAppend = menuNode._onAppend;
                    var fnOnInsertBefore = menuNode._onInsertBefore;
                    var bAutoObject = (menuNode.parent && menuNode.parent.type === 'array');
                    menuNode._onAppend = function(field, value, type) {
                        if (value && typeof value === 'object' && JSON.stringify(value) === '{}') {
                            value = {"":""};
                        }
                        if (bAutoObject === true && ! value && type === 'auto') {
                            value = {"":""};
                            type = 'object';
                        }
                        fnOnAppend.call(menuNode, field, value, type);
                        exf_focusFirstChildValue((menuNode.parent ? menuNode.parent : menuNode), true);
                    }
                    menuNode._onInsertBefore = function(field, value, type) {
                        if (value && typeof value === 'object' && JSON.stringify(value) === '{}') {
                            value = {"":""};
                        }
                        if (bAutoObject === true && ! value && type === 'auto') {
                            value = {"":""};
                            type = 'object';
                        }
                        fnOnInsertBefore.call(menuNode, field, value, type);
                        exf_focusFirstChildValue((menuNode.parent ? menuNode.parent : menuNode), true);
                    }
                }
            });

            // Add preset button if applicable
            // ist objekt oder wert === leer
            if(menuNodeType === "object" || menuNodeType === "root" || (menuNodeType === 'array' && Array.isArray(val) && val.length === 0)) {
                items.unshift(
                {
                    text : t("PRESETS.TITLE"),   // the text for the menu item
                    title : t("PRESETS.TITLE"),  // the HTML title attribute
                    className : "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-magic", // the css class name(s) for the menu item
                    click: function(){
                        return exf_openPresetsModal(menuNode);
                    }
                });
            }

            // Add details button if applicable
            if(menuNodeType === "object" || menuNodeType === "root") {
                items.unshift(
                {
                    text : t("DETAILS.TITLE"),   // the text for the menu item
                    title : t("DETAILS.TITLE"),  // the HTML title attribute
                    className : "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-th-list", // the css class name(s) for the menu item
                    click: function(){
                        return exf_openDetailsModal(menuNode);
                    }
                });
            }

            // Add item to open model browser
            if (menuNode.type === 'auto' || menuNode.type === 'string') {
                items.push({
                    text: t("MODELBROWSER.TITLE"),
                    title: t("MODELBROWSER.HINT"),
                    className: "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-search" ,
                    click : function() {
                        return exf_openModelModal(menuNode);
                    }
                });
            }

            // Add item to open larger editor for string nodes
            if (menuNode.type === 'auto' || menuNode.type === 'string') {
                items.push({
                    text: t("CONTEXT_MENU.EDITOR.TITLE"),
                    title: t("CONTEXT_MENU.EDITOR.HINT"),
                    className: "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-pencil" ,
                    click : function() {
                        return exf_openAceModal(menuNode);
                    }
                });
            }

            // Add menu item for copying current JSON path always
            items.push({
                text: t("JSON_PATH"),   // the text for the menu item
                title: t("JSON_PATH"),  // the HTML title attribute
                className: "jsoneditor-type-object active-button" ,     // the css class name(s) for the menu item
                click : function() {
                    return exf_openJsonPathViewModal(menuNode);
                }
            });

            // Add clipboard-submenu
            editMenu = {
                text: t("CONTEXT_MENU.CLIPBOARD.TITLE"),   // the text for the menu item
                title: t("CONTEXT_MENU.CLIPBOARD.TITLE"),  // the HTML title attribute
                className: "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-clipboard",
                submenuTitle: t("CONTEXT_MENU.CLIPBOARD.HINT"),
                submenu: [
                    {
                        text: t("CONTEXT_MENU.CLIPBOARD.COPY"),
                        title: t("CONTEXT_MENU.CLIPBOARD.COPY_HINT"),
                        className: "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-files-o",
                        click: function() {
                            exfTools.clipboard.copyText(JSON.stringify(menuNode.getValue()));
                        }
                    },
                    {
                        text: t("CONTEXT_MENU.CLIPBOARD.PASTE"),
                        title: t("CONTEXT_MENU.CLIPBOARD.PASTE_HINT"),
                        className: "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-clipboard",
                        click: function() {
                            var sPasted, oJson;
                            try {
                                sPasted = exfTools.clipboard.pasteText();
                                try {
                                    oJson = JSON.parse(sPasted);
                                } catch (e) {
                                    // ignore errors
                                }
                                menuNode.setValue((oJson || sPasted));
                            } catch (e) {
                                exf_openPasteModal(menuNode);
                            }
                        }
                    }
                ]
            };
            items.push(editMenu);

            // Add clear button if applicable
            if(menuNodeType === "object" || menuNodeType === "root" || menuNodeType === "array") {
                items.push({
                    text : t("CONTEXT_MENU.CLEAR.TITLE"),   // the text for the menu item
                    title : t("CONTEXT_MENU.CLEAR.HINT"),  // the HTML title attribute
                    className : "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-eraser",
                    click: function(){
                        switch (menuNodeType) {
                            case "root":
                                menuNode.editor.set({"":""});
                                break;
                            case "array":
                                menuNode.setValue([]);
                                break;
                            case "object":
                                menuNode.setValue({"":""});
                                break;
                        }
                    }
                });
            }

            // Add comment toggle button
            if(menuNodeType !== "array" && menuNode.type !== 'root') {
                items.push({
                    text : t("CONTEXT_MENU.COMMENT.TITLE"),   // the text for the menu item
                    title : t("CONTEXT_MENU.COMMMENT.HINT"),  // the HTML title attribute
                    className : "jsoneditor-fa-menuicon jsoneditor-type-object active-button fa-quote-left",
                    click: function(){
                        var sFld;
                        switch (menuNodeType) {
                            case "object":
                                if (menuNode.childs[0].field === '/*' && menuNode.childs[menuNode.childs.length-1].field === '*/') {
                                    menuNode.removeChild(menuNode.childs[menuNode.childs.length-1]);
                                    menuNode.removeChild(menuNode.childs[0]);
                                } else {
                                    menuNode.childs[0]._onInsertBefore('/*', '');
                                    menuNode.childs[menuNode.childs.length-1]._onInsertAfter('*/', '');
                                }
                                break;
                            default:
                                sFld = menuNode.getField();
                                if (sFld.substring(0, 2) === '//') {
                                    menuNode.setField(sFld.substring(2), menuNode.editable.field);
                                    menuNode.recreateDom();
                                } else {
                                    menuNode.setField('//' + sFld, menuNode.editable.field);
                                    menuNode.recreateDom();
                                }
                        }
                    }
                });
            }

            return items;
        };
        var fnRenderErrors = function (errorNodes) {
            // clear all current errors
            if (this.errorNodes) {
                this.errorNodes.forEach(function (node) {
                    node.setError(null);
                });
            }

            // render the new errors
            var parentPairs = errorNodes.reduce(function (all, entry) {
                return entry.node.findParents().filter(function (parent) {
                    return !all.some(function (pair) {
                        return pair[0] === parent;
                    });
                }).map(function (parent) {
                    return [parent, entry.node];
                }).concat(all);
            }, []);

            // We cache the raw error nodes, so we can easily toggle their error indicators later.
            errorNodes = this.__cachedErrors = parentPairs.map(function (pair) {
                return {
                    node: pair[0],
                    child: pair[1],
                    error: {
                        message: pair[0].type === 'object' ?
                            'Contains invalid properties!' :
                            'Contains invalid items!' // array
                    }
                };
            }).concat(errorNodes);

            errorNodes = toggleValidationErrors(errorNodes);

            this.errorNodes = errorNodes;
        };
        function toggleValidationErrors(errorNodes) {
            // Reduce breadcrumbs.
            var aHidden = [];
            errorNodes = errorNodes.map(function setError(entry) {
                var node = entry.node;
                var child = entry.child;

                var bCollapsed = !node.expanded;
                var bVisible = !aHidden.includes(node.parent);
                var bEndPoint = child === undefined || child === null;

                if (bCollapsed || !bVisible) {
                    aHidden.push(node);
                }

                // If the node is either an endpoint or collapsed but visible (i.e. a visual end-point)
                // we should render the error indicator.
                if((bCollapsed && bVisible) || bEndPoint)
                {
                    // This call makes the little warning triangle appear in the editor.
                    node.setError(entry.error, entry.child);
                }

                return entry.node;
        });
        return errorNodes;
        }
        var oOptions = Object.assign({}, oConfig.options, {
            mode: bDisabled ? 'view' : 'tree',
            modes: bDisabled ? ['code', 'view'] : ['code', 'tree'],
            onEditable: function () { return !bDisabled; },
            onError: reportError,
            onChange: scheduleChange,
            onModeChange: function () {
                later(function () {
                    applyDisabled();
                    addToolbar();
                    restoreCollapsedNodes();
                    restoreScroll();
                }, 0);
            },
            onExpand: function () {
                rememberCollapsedNodes();
                if (oEditor && oEditor.__cachedErrors) {
                    (oEditor.errorNodes || []).forEach(function (oNode) { oNode.setError(null); });
                    oEditor.errorNodes = toggleValidationErrors(oEditor.__cachedErrors);
                }
            }
        });

        function addToolbar() {
            if (oConfig.uxon && oConfig.helpUrl && jqEditor.find('.exf-jsoneditor-help').length === 0) {
                exf_addHelpButton($, domEditor.id, oConfig.helpUrl, t('HELP'));
            }
            refreshServerAvailability();
        }

        if (oConfig.uxon && oConfig.autosuggest) {
            Object.assign(oOptions, {
                name: oConfig.name || oConfig.schema(),
                enableTransform: false,
                enableSort: false,
                history: true,
                onCreateMenu: function (aItems, oNode) {
                    return bDisabled ? aItems : fnCreateMenu(aItems, oNode);
                },
                autocomplete: {
                    applyTo: ['value'],
                    caseSensitive: false,
                    filter: function (sToken, sMatch) {
                        if (sMatch.startsWith('// ERROR:')) return true;
                        sToken = sToken.replace(/^\s(?=\S)/, '').replace(/__\s+$/, '__');
                        return !sToken.trim() || sMatch.toLowerCase().indexOf(sToken.toLowerCase()) !== -1;
                    },
                    getOptions: function (sText, aPath, sInput, oJsonEditor) {
                        if (oAutosuggestAbort) {
                            oAutosuggestAbort.abort();
                        }
                        if (!refreshServerAvailability()) {
                            oAutosuggestAbort = null;
                            return Promise.resolve([]);
                        }
                        var oRequestAbort = oAutosuggestAbort = new AbortController();
                        return exf_fetchAutosuggest(sText, aPath, sInput, JSON.stringify(oJsonEditor.get()), oRequestAbort.signal)
                            .then(function (oResponse) {
                                if (oRequestAbort.signal.aborted) {
                                    // Superseded: never settle, so this cannot replace the newer dropdown
                                    return new Promise(function () {});
                                }
                                if (!oResponse || !Array.isArray(oResponse.values)) {
                                    throw new Error(t('ERROR.MALFORMED_RESPONSE'));
                                }
                                if (!refreshServerAvailability()) return [];
                                oJsonEditor._autosuggestLastResult = oResponse;
                                return exf_filterAutosuggest(oResponse.values, sText);
                            }).catch(function (oError) {
                                if (oRequestAbort.signal.aborted) {
                                    return new Promise(function () {});
                                }
                                if (!refreshServerAvailability()) return [];
                                reportError(oError);
                                throw oError;
                            }).finally(function () {
                                if (oAutosuggestAbort === oRequestAbort) {
                                    oAutosuggestAbort = null;
                                }
                            });
                    }
                }
            });
        }
        if (oConfig.uxon && oConfig.validate !== false) {
            oOptions.onValidate = function (oJson) {
                if (!refreshServerAvailability()) {
                    cancelValidation();
                    return Promise.resolve([]);
                }
                return validateLater(oJson);
            };
        }

        var oApi = {
            getEditor: function () { return oEditor; },
            getValue: function () { return emptyValue(oEditor.getText()); },
            setValue: function (mNewValue) {
                var sValue = normalizeValue(mNewValue);
                if (oEditor.getText() === sValue) {
                    sLastValue = oApi.getValue();
                    updatePresetHint();
                    return oApi;
                }
                rememberCollapsedNodes();
                bSettingValue = true;
                try {
                    // Invalid JSON remains editable in code mode instead of being discarded.
                    try {
                        JSON.parse(sValue);
                    } catch (oError) {
                        if (oEditor.getMode() !== 'code') {
                            oEditor.setMode('code');
                        }
                    }
                    oEditor.setText(sValue);
                    if (oEditor.getMode() !== 'code') {
                        oEditor.expandAll();
                        restoreCollapsedNodes();
                    }
                    updatePresetHint();
                    sLastValue = oApi.getValue();
                } finally {
                    bSettingValue = false;
                }
                return oApi;
            },
            setDisabled: function (bValue) {
                bDisabled = bValue;
                oEditor.options.modes = bValue ? ['code', 'view'] : ['code', 'tree'];
                // Rebuild the mode switcher as JSONEditor caches its menu.
                oEditor.setMode(oEditor.getMode() === 'code' ? 'code' : (bValue ? 'view' : 'tree'));
                applyDisabled();
                return oApi;
            },
            setRequired: function (bValue) { oConfig.required = bValue; return oApi; },
            getRequired: function () { return oConfig.required === true; },
            restoreScroll: restoreScroll,
            refreshServerAvailability: refreshServerAvailability,
            destroy: function () {
                if (bDestroyed) {
                    return;
                }
                rememberCollapsedNodes();
                bDestroyed = true;
                oGlobal.removeEventListener('online', refreshServerAvailability);
                oGlobal.removeEventListener('offline', refreshServerAvailability);
                cancelValidation();
                if (oAutosuggestAbort) {
                    oAutosuggestAbort.abort();
                    oAutosuggestAbort = null;
                }
                aTimers.forEach(clearTimeout);
                aRequests.slice().forEach(function (oRequest) { oRequest.abort(); });
                aModals.slice().forEach(function (oModal) { oModal.close(); });
                jqEditor.off('.exfJsonEditor');
                jqEditor.find('.jsoneditor-tree').off('.exfJsonEditor');
                if (jqHint) {
                    jqHint.remove();
                }
                if (jqUnavailable) {
                    jqUnavailable.remove();
                }
                oEditor.destroy();
                if (domEditor.exfJsonEditor === oApi) {
                    delete domEditor.exfJsonEditor;
                }
            }
        };

        if (domEditor.exfJsonEditor) {
            domEditor.exfJsonEditor.destroy();
        }
        var mInitialJson;
        try {
            mInitialJson = JSON.parse(normalizeValue(mValue));
        } catch (oError) {
            oOptions.mode = 'code';
        }
        bSettingValue = true;
        oEditor = new JSONEditor(domEditor, oOptions, mInitialJson);
        domEditor.exfJsonEditor = oApi;
        oEditor._renderValidationErrors = fnRenderErrors;
        if (oConfig.uxon) {
            jqHint = $('<div class="uxoneditor-preset-hint"><a href="#"><i class="fa fa-magic preset-hint-pulse"></i><span></span></a></div>');
            jqHint.find('i').attr('title', t('PRESETS.HINT'));
            jqHint.find('span').text(t('PRESETS.HINT'));
            jqHint.insertAfter(jqEditor).on('click.exfJsonEditor', 'a', function (oEvent) {
                oEvent.preventDefault();
                if (!bDisabled && oConfig.autosuggest) {
                    exf_openPresetsModal(oEditor.node);
                }
            });
            if (!oConfig.autosuggest) {
                jqHint.remove();
                jqHint = null;
            }
        }
        if (oConfig.uxon && oConfig.autosuggest) {
            jqEditor.on('blur.exfJsonEditor', 'div.jsoneditor-field[contenteditable="true"]', function (oEvent) {
                if (!bDisabled && refreshServerAvailability()) {
                    oEvent.data = {jsonEditor: oEditor};
                    exf_onBlur.call(this, oEvent);
                    notifyChange();
                }
            });
            jqEditor.on('click.exfJsonEditor', 'div.jsoneditor-value[contenteditable="true"].jsoneditor-string', function (oEvent) {
                if (!bDisabled) {
                    oEvent.data = {jsonEditor: oEditor};
                    exf_onClickValue.call(this, oEvent);
                }
            });
        }
        oApi.setValue(mValue);
        sLastValue = oApi.getValue();
        bSettingValue = false;
        if (oEditor.getMode() !== 'code') {
            oEditor.expandAll();
            restoreCollapsedNodes();
        }
        applyDisabled();
        addToolbar();
        oGlobal.addEventListener('online', refreshServerAvailability);
        oGlobal.addEventListener('offline', refreshServerAvailability);
        jqEditor.on('focusin.exfJsonEditor pointerdown.exfJsonEditor', function () {
            refreshServerAvailability();
        });
        restoreScroll();
        return oApi;
    }

    oGlobal.exfJsonEditor = {
        create: create,
        normalizeValue: normalizeValue,
        emptyValue: emptyValue
    };
})(window);