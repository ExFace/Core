(function (oWindow) {
    'use strict';
    if (oWindow.MarkianAdapter) return;
    var oMermaidFactories = new WeakSet();
    var oCodeFactories = new WeakSet();

    oWindow.MarkianAdapter = {
        /**
         * Translates widget presentation settings without importing Ace into Markian.
         *
         * @param {Object} oModel Widget language, colorize and resolved languageBase.
         * @returns {Object} Markian Code configuration.
         */
        buildCodeConfig: function (oModel) {
            var oConfig = {languageBase: oModel.languageBase};
            if (!oModel.colorize) {
                oConfig.languages = [];
            } else if (oModel.language) {
                var sLanguage = oModel.language.toLowerCase();
                oConfig.languages = oWindow.MarkianCode.supportedLanguages.filter(function (oLanguage) {
                    return oLanguage.id === sLanguage || (oLanguage.aliases || []).indexOf(sLanguage) !== -1;
                });
            }
            return oConfig;
        },
        /**
         * Reuses Markian's token loader for rendering-only widgets without creating editor controls.
         *
         * @param {Document} domDocument Viewer document.
         * @param {Object} oModel Widget highlighting settings.
         * @returns {Object} Viewer fence-language callbacks.
         */
        buildCodeRenderers: function (domDocument, oModel) {
            var oConfig = oWindow.MarkianAdapter.buildCodeConfig(oModel);
            var aLanguages = oConfig.languages || oWindow.MarkianCode.supportedLanguages;
            var oRenderers = {};
            aLanguages.forEach(function (oLanguage) {
                var oBase = new URL(oConfig.languageBase, domDocument.baseURI);
                var oUrl = new URL(oLanguage.id + '.min.js', oBase);
                oUrl.search = oBase.search;
                var oResolved = Object.assign({}, oLanguage, {source: oUrl.href});
                var render = async function (oContext) {
                    var domPre = domDocument.createElement('pre');
                    var domCode = domDocument.createElement('code');
                    domCode.className = 'language-' + oContext.language;
                    domPre.appendChild(domCode);
                    if (oContext.source.length > 100000 || oContext.source.split('\n').length > 5000) {
                        domCode.textContent = oContext.source;
                        return domPre.outerHTML;
                    }
                    var oHighlighter = await oWindow.MarkianCode.loadCodeLanguage(domDocument, oResolved);
                    if (oContext.signal.aborted) return '';
                    var aTokens = await oHighlighter.highlight(oContext.source);
                    if (oContext.signal.aborted) return '';
                    if (!Array.isArray(aTokens) || aTokens.length > 100000) throw new TypeError('Invalid code highlighting tokens.');
                    var iOffset = 0;
                    aTokens.forEach(function (oToken) {
                        if (!oToken || !Number.isInteger(oToken.from) || !Number.isInteger(oToken.to)
                            || oToken.from < iOffset || oToken.to <= oToken.from || oToken.to > oContext.source.length
                            || !/^tok-[a-z-]+(?: tok-[a-z-]+)*$/.test(oToken.className)) throw new TypeError('Invalid code highlighting token.');
                        domCode.appendChild(domDocument.createTextNode(oContext.source.slice(iOffset, oToken.from)));
                        var domToken = domDocument.createElement('span');
                        domToken.className = oToken.className;
                        domToken.textContent = oContext.source.slice(oToken.from, oToken.to);
                        domCode.appendChild(domToken);
                        iOffset = oToken.to;
                    });
                    domCode.appendChild(domDocument.createTextNode(oContext.source.slice(iOffset)));
                    return domPre.outerHTML;
                };
                [oLanguage.id].concat(oLanguage.aliases || []).forEach(function (sName) { oRenderers[sName] = render; });
            });
            return oRenderers;
        },
        /**
         * Creates source-neutral tag rules backed by cancellable facade requests.
         *
         * @param {Object[]} aModels Server-generated mention metadata.
         * @returns {Object[]} Markian tag configuration.
         */
        buildTags: function (aModels) {
            return aModels.map(function (oModel) {
                var oTag = {id: oModel.id, prefix: oModel.prefix, textRegex: oModel.textRegex, legacyLinks: true, suggestOnClick: true};
                if (oModel.color) oTag.color = oModel.color;
                oTag.onHover = function (oContext) { return {title: (oModel.caption ? oModel.caption + ': ' : '') + oContext.value}; };
                oTag.onSuggest = function (oContext) {
                    return new Promise(function (resolve, reject) {
                        if (oContext.signal.aborted) { reject(new DOMException('Mention lookup cancelled.', 'AbortError')); return; }
                        var aColumns = Array.from(new Set([oModel.filterAttribute, oModel.tagAttribute, oModel.suggestionAttribute]));
                        var aFilterAttributes = [
                            {alias: oModel.filterAttribute, numeric: oModel.filterAttributeIsNumeric},
                            {alias: oModel.tagAttribute, numeric: oModel.tagAttributeIsNumeric}
                        ].filter(function (oAttribute) {
                            return !oAttribute.numeric || /^-?\d+(?:[.,]\d+)?$/.test(oContext.query);
                        }).map(function (oAttribute) { return oAttribute.alias; });
                        if (oContext.query !== '' && aFilterAttributes.length === 0) { resolve([]); return; }
                        var oData = Object.assign({}, oModel.request, {page: 1, rows: oModel.limit, data: {
                            oId: oModel.request.object,
                            columns: aColumns,
                            filters: {operator: 'OR', conditions: oContext.query === '' ? [] : Array.from(new Set(aFilterAttributes)).map(function (sAlias) {
                                return {expression: sAlias, comparator: '=', value: oContext.query};
                            })}
                        }});
                        var oRequest = oWindow.jQuery.ajax({type: 'POST', url: oModel.url, headers: oModel.headers, data: oData, dataType: 'json'});
                        var abort = function () { oRequest.abort(); };
                        oContext.signal.addEventListener('abort', abort, {once: true});
                        oRequest.done(function (oResponse) {
                            try {
                                if (!oResponse || !Array.isArray(oResponse.rows)) throw new Error('Invalid mention autosuggest response.');
                                resolve(oResponse.rows.map(function (oRow) {
                                    if (oRow[oModel.suggestionAttribute] == null || oRow[oModel.tagAttribute] == null) throw new Error('Mention autosuggest response is missing configured attributes.');
                                    return {label: String(oRow[oModel.suggestionAttribute]), value: oModel.prefix + String(oRow[oModel.tagAttribute])};
                                }));
                            } catch (oError) { reject(oError); }
                        }).fail(function (oResponse, sStatus, sError) {
                            reject(sStatus === 'abort' ? new DOMException('Mention lookup cancelled.', 'AbortError') : new Error(sError || 'Mention lookup failed.'));
                        }).always(function () { oContext.signal.removeEventListener('abort', abort); });
                    });
                };
                return oTag;
            });
        },
        mount: function (domHost, oOptions, bViewer, sMermaidSource, bAllowImages) {
            if (!domHost) throw new Error('Markian mount is missing.');
            if (domHost._exfMarkian) domHost._exfMarkian.destroy();
            var sValue = oOptions.markdown || '';
            var bReady = false;
            var bDestroyed = false;
            var bPendingValue = false;
            var oInstance;
            var oResize;
            var oRemoval;
            var oHandle;
            var domMount = bViewer ? domHost : domHost.ownerDocument.createElement('div');
            var report = function (oError) {
                if (bDestroyed) return;
                domHost.setAttribute('data-markian-error', oError.message || String(oError));
                domHost.dispatchEvent(new CustomEvent('exf-markian-error', {detail: oError, bubbles: true}));
                oWindow.console.error(oError);
            };
            var oSettings = Object.assign({}, oOptions);
            if (!bViewer && oSettings.extensions && oSettings.extensions.code && oSettings.extensions.code.enabled) {
                if (!oCodeFactories.has(oWindow.Markian)) {
                    oWindow.Markian.registerExtension('code', oWindow.MarkianCode.codeExtension);
                    oCodeFactories.add(oWindow.Markian);
                }
            }
            if (!bViewer && bAllowImages === false) {
                oSettings.onImageInsert = function () { throw new Error('Image insertion is disabled for this widget.'); };
            }
            if (sMermaidSource) {
                if (bViewer) {
                    oSettings.mermaid = true;
                    oSettings.renderers = Object.assign({}, oSettings.renderers, {mermaid: oWindow.MarkianViewerMermaid.createRenderer(domHost.ownerDocument, {source: sMermaidSource})});
                } else {
                    if (!oMermaidFactories.has(oWindow.Markian)) {
                        oWindow.Markian.registerExtension('mermaid', oWindow.MarkianMermaid.mermaidExtension);
                        oMermaidFactories.add(oWindow.Markian);
                    }
                    oSettings.extensions = Object.assign({}, oSettings.extensions, {mermaid: {enabled: true, config: {source: sMermaidSource}}});
                }
            }
            if (bViewer) oSettings.onError = report;
            if (!bViewer) domHost.appendChild(domMount);
            try {
                oInstance = (bViewer ? oWindow.MarkianViewer : oWindow.Markian).create(domMount, oSettings);
            } catch (oError) {
                if (!bViewer) domMount.remove();
                throw oError;
            }
            if (!bViewer && bAllowImages === false) oInstance.getToolbar().remove('image');
            oHandle = {
                get: function () { return bReady ? oInstance.get() : sValue; },
                set: function (mValue) {
                    var sNext = String(mValue == null ? '' : mValue);
                    if (sNext === oHandle.get()) return;
                    if (!bReady) {
                        sValue = sNext;
                        bPendingValue = true;
                        return;
                    }
                    if (bViewer) {
                        oInstance.set(sNext).catch(report);
                    } else {
                        oInstance.set(sNext, {origin: 'host-load', resetHistory: true, pending: 'discard'});
                    }
                },
                setDisabled: function (bDisabled) {
                    oSettings.disabled = bDisabled;
                    if (bReady && !bViewer) oInstance.setDisabled(bDisabled);
                },
                resize: function () { if (bReady && !bViewer) oInstance.resize(); },
                focus: function () { if (bReady && !bViewer) oInstance.focus(); },
                destroy: function () {
                    if (bDestroyed) return;
                    bDestroyed = true;
                    if (oResize) oResize.disconnect();
                    if (oRemoval) oRemoval.disconnect();
                    oInstance.destroy();
                    if (!bViewer) domMount.remove();
                    if (domHost._exfMarkian === oHandle) delete domHost._exfMarkian;
                },
                instance: oInstance,
                ready: null
            };
            domHost._exfMarkian = oHandle;
            if (!bViewer) {
                oInstance.on('error', function (oEvent) { report(oEvent.error); });
                oInstance.on('tag-click', function (oEvent) {
                    if (!bDestroyed) domHost.dispatchEvent(new CustomEvent('exf-markian-tag-click', {detail: oEvent.tag, bubbles: true}));
                });
                oInstance.on('change', function (oEvent) {
                    if (bDestroyed || !bReady || oEvent.origin === 'host-load') return;
                    domHost.dispatchEvent(new Event('change', {bubbles: true}));
                });
            }
            oRemoval = new MutationObserver(function () {
                if (!domHost.isConnected) oHandle.destroy();
            });
            oRemoval.observe(domHost.ownerDocument.documentElement, {childList: true, subtree: true});
            oHandle.ready = oInstance.ready.then(function () {
                if (bDestroyed) return;
                bReady = true;
                if (!bViewer) oInstance.setDisabled(oSettings.disabled === true);
                if (bPendingValue) oHandle.set(sValue);
                if (!bViewer && oWindow.ResizeObserver) {
                    oResize = new ResizeObserver(function () { oHandle.resize(); });
                    oResize.observe(domHost);
                }
                oHandle.resize();
            });
            oHandle.ready.catch(report);
            return oHandle;
        }
    };
})(window);