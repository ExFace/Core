/**
 * Run runJsonEditorRegression() in Fixtures/JsonEditor.html.
 * Exercises the real JSONEditor and picoModal with only HTTP mocked.
 */
export async function runJsonEditorRegression() {
    const aResults = [];
    const aEditors = [];
    const aHosts = [];
    const aErrors = [];
    const fnFetch = window.fetch;
    const fnAjax = jQuery.ajax;
    let sSchema = 'widget';
    let oResponse = {values: ['foo', 'x.foo', 'other'], templates: {child: '{"widget_type":""}'}};
    let bFail = false;
    let sErrorWidget;
    const aRequests = [];
    const oTranslations = await (await fnFetch('../../../Translations/exface.Core.en.json')).json();
    const oTerms = {};
    for (const [sKey, sValue] of Object.entries(oTranslations)) {
        if (sKey.startsWith('WIDGET.UXONEDITOR.')) {
            oTerms[sKey.substring('WIDGET.UXONEDITOR.'.length)] = sValue;
        }
    }
    function check(bCondition, sMessage) {
        if (!bCondition) throw new Error(sMessage);
    }
    function wait() {
        return new Promise(fnResolve => setTimeout(fnResolve, 350));
    }
    function create(bUxon, mValue, oConfig = {}) {
        const domHost = document.createElement('div');
        domHost.style.cssText = 'height:300px;position:relative;margin:10px';
        const domEditor = document.createElement('div');
        domEditor.id = 'json-regression-' + aEditors.length;
        domEditor.style.height = '100%';
        domHost.appendChild(domEditor);
        document.getElementById('fixtures').appendChild(domHost);
        aHosts.push(domHost);
        const aChanges = [];
        const oAdapter = exfJsonEditor.create(domEditor, Object.assign({
            uxon: bUxon, autosuggest: bUxon, validate: false,
            ajaxUrl: '/json-regression-api',
            translations: oTerms,
            schema: () => sSchema,
            rootPrototype: () => '\\exface\\Core\\Widgets\\InputJson',
            rootObject: () => 'exface.Core.OBJECT',
            helpUrl: 'about:blank',
            onChange: sValue => aChanges.push(sValue),
            onError: oError => aErrors.push(oError)
        }, oConfig), mValue);
        aEditors.push(oAdapter);
        return {oAdapter, domHost, aChanges};
    }
    window.fetch = async (sUrl, oOptions) => {
        aRequests.push(Object.fromEntries(oOptions.body));
        if (sErrorWidget !== undefined) {
            return new Response(sErrorWidget, {status: 500, headers: {'Content-Type': 'text/html'}});
        }
        if (bFail) throw new Error('transport failed');
        return new Response(JSON.stringify(oResponse), {headers: {'Content-Type': 'application/json; charset=utf-8'}});
    };
    jQuery.ajax = oOptions => {
        aRequests.push(oOptions.data);
        const oDeferred = jQuery.Deferred();
        const oPromise = oDeferred.promise();
        oPromise.abort = () => oDeferred.reject({}, 'abort', 'abort');
        queueMicrotask(() => bFail
            ? oDeferred.reject({status: 500, statusText: 'Internal Server Error', responseJSON: oResponse, responseText: sErrorWidget}, 'error', 'Internal Server Error')
            : oDeferred.resolve(oResponse));
        return oPromise;
    };
    try {
        const oPlain = create(false, '{"name":"initial"}');
        check(oPlain.oAdapter.getEditor().get().name === 'initial', 'Initial JSON was lost');
        oPlain.oAdapter.setValue({count: 0, enabled: false});
        check(oPlain.oAdapter.getEditor().get().enabled === false, 'Object values were not normalized');
        oPlain.oAdapter.setValue('[1,2]');
        check(oPlain.oAdapter.getEditor().get().length === 2, 'Arrays were not retained');
        oPlain.oAdapter.setValue('{"unfinished":');
        check(oPlain.oAdapter.getEditor().getMode() === 'code', 'Invalid JSON must switch to code mode');
        check(oPlain.oAdapter.getValue() === '{"unfinished":', 'Invalid JSON text was discarded');
        oPlain.oAdapter.setDisabled(true);
        check(oPlain.oAdapter.getEditor().aceEditor.getReadOnly(), 'Code mode is not read-only');
        oPlain.oAdapter.setDisabled(false);
        check(!oPlain.oAdapter.getEditor().aceEditor.getReadOnly(), 'Code mode did not reenable');
        oPlain.oAdapter.getEditor().aceEditor.setValue('{"edited":true}', -1);
        await wait();
        check(JSON.parse(oPlain.aChanges.at(-1)).edited === true, 'User edits did not emit changes');
        oPlain.oAdapter.setValue(null);
        check(oPlain.oAdapter.getValue() === '', 'Empty objects must become empty widget values');
        oPlain.oAdapter.setValue(' [ ] ');
        check(oPlain.oAdapter.getValue() === '', 'Empty arrays must become empty widget values');
        aResults.push('value normalization, invalid text, changes and disabled code mode');

        const oUxon = create(true, {});
        const oJsonEditor = oUxon.oAdapter.getEditor();
        check(oUxon.domHost.querySelector('.uxoneditor-preset-hint').style.display !== 'none', 'Empty UXON hint is hidden');
        const fnOptions = oJsonEditor.options.autocomplete.getOptions;
        const aValues = await fnOptions('foo', ['child'], 'value', oJsonEditor);
        check(aValues.join(',') === 'foo,x.foo,other', 'Suggestions were not ranked');
        check(aRequests.at(-1).schema === 'widget', 'Schema was not sent');
        sSchema = 'action';
        await fnOptions('', ['child'], 'value', oJsonEditor);
        check(aRequests.at(-1).schema === 'action', 'Linked schema getter was frozen');
        bFail = true;
        let bRejected = false;
        try { await fnOptions('', [], 'value', oJsonEditor); } catch (oError) { bRejected = true; }
        check(bRejected && aErrors.length === 1, 'Failed requests must reject and report, not hang');
        bFail = false;
        aErrors.length = 0;
        oResponse = {};
        bRejected = false;
        try { await fnOptions('', [], 'value', oJsonEditor); } catch (oError) { bRejected = true; }
        check(bRejected && aErrors.length === 1, 'Malformed responses must reject and report');
        aErrors.length = 0;
        aResults.push('live UXON context, autosuggest and explicit transport errors');

        oResponse = [{
            NAME: 'Test preset', PROTOTYPE__LABEL: 'General',
            UXON: '{"widget_type":"InputJson"}', DESCRIPTION: 'Test preset', WRAP_FLAG: '0'
        }];
        oUxon.domHost.querySelector('.uxoneditor-preset-hint a').click();
        await wait();
        const domModal = document.querySelector('.uxoneditor-modal');
        check(domModal !== null, 'Preset popup did not open');
        check(!domModal.querySelector('[role="status"]'), 'Preset results must not show a no-results message');
        jQuery(domModal.querySelector('#uxonPresetSearch')).val('no-matching-preset').trigger('input');
        check(domModal.querySelector('[role="status"]').textContent === oTerms['SEARCH.NO_RESULTS'], 'Unmatched preset search must show an info message');
        jQuery(domModal.querySelector('#uxonPresetSearch')).val('').trigger('input');
        check(!domModal.querySelector('[role="status"]'), 'Restoring preset results must clear the info message');
        domModal.querySelector('.uxoneditor-preset-card').click();
        domModal.querySelector('.uxoneditor-preset-replace').click();
        check(oJsonEditor.get().widget_type === 'InputJson', 'Preset did not replace root value');
        check(JSON.parse(oUxon.aChanges.at(-1)).widget_type === 'InputJson', 'Preset did not notify the facade');
        aResults.push('preset popup, preview and value propagation');

        const aItems = oJsonEditor.options.onCreateMenu([], {path: []});
        oResponse = [];
        aItems.find(oItem => oItem.text === oTerms['PRESETS.TITLE']).click();
        await wait();
        const domEmptyPresets = document.querySelector('.uxoneditor-modal');
        check(domEmptyPresets.querySelector('[role="status"]').textContent === oTerms['SEARCH.NO_RESULTS'], 'Empty preset response must show an info message');
        check(domEmptyPresets.querySelector('.spinner').style.display === 'none', 'Empty presets must finish loading');
        domEmptyPresets.querySelector('.pico-close').click();
        aResults.push('preset empty and filtered results show inline information');

        aItems.find(oItem => oItem.text === oTerms.JSON_PATH).click();
        check(document.querySelector('#jsonPathView').value === '$', 'Root JSON path is incorrect');
        document.querySelector('.pico-close').click();
        oResponse = {
            properties: [{PROPERTY: 'widget_type', TITLE: 'Widget', DESCRIPTION: '', REQUIRED: true}],
            title: 'Widget', description: 'Details', prototype_schema: 'widget', alias: 'InputJson'
        };
        aItems.find(oItem => oItem.text === oTerms['DETAILS.TITLE']).click();
        await wait();
        check(document.querySelector('.uxoneditor-table tbody .uxoneditor-checkbox').checked, 'Details lost existing properties');
        document.querySelector('.uxoneditor-btn-ok').click();
        aResults.push('JSON path and property details');

        const aLeafItems = oJsonEditor.options.onCreateMenu([], {path: ['widget_type']});
        aLeafItems.find(oItem => oItem.text === oTerms['CONTEXT_MENU.EDITOR.TITLE']).click();
        const oAce = JSONEditor.ace.edit(document.querySelector('.uxoneditor-value-editor'));
        oAce.setValue('InputText', -1);
        document.querySelector('.uxoneditor-btn-ok').click();
        check(oJsonEditor.get().widget_type === 'InputText', 'String editor did not save its value');
        aResults.push('large string editor and popup cleanup');

        oResponse = {
            _TYPE: 'object', _BASE_OBJECT_NAME: 'Object', _BASE_OBJECT_ALIAS_NS: 'exface.Core.OBJECT',
            _BASE_OBJECT_ALIAS: 'OBJECT',
            ATTRIBUTES: [{NAME: 'Name', ALIAS: 'NAME', DESCRIPTION: 'Name', _SUGGEST: 'NAME'}]
        };
        aLeafItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        await wait();
        const domSearchModel = document.querySelector('.uxoneditor-modal');
        check(domSearchModel.querySelector('[role="status"]').hidden, 'Model results must not show the empty-result message');
        jQuery(domSearchModel.querySelector('#uxonModelBrowserSearch')).val('no-matching-attribute').trigger('input');
        check(!domSearchModel.querySelector('[role="status"]').hidden, 'Unmatched model search must show an info message');
        jQuery(domSearchModel.querySelector('#uxonModelBrowserSearch')).val('').trigger('input');
        check(domSearchModel.querySelector('[role="status"]').hidden, 'Restoring model results must hide the info message');
        document.querySelector('.uxoneditor-table tbody tr').click();
        document.querySelector('.uxoneditor-btn-ok').click();
        check(oJsonEditor.get().widget_type === 'NAME', 'Model browser did not apply its selection');
        aResults.push('model browser and selection propagation');

        oResponse = {
            _TYPE: 'object', _BASE_OBJECT_NAME: 'Dummy object',
            _BASE_OBJECT_ALIAS_NS: 'exface.Core.DUMMY', _BASE_OBJECT_ALIAS: 'DUMMY'
        };
        aLeafItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        await wait();
        const domEmptyModel = document.querySelector('.uxoneditor-modal');
        check(domEmptyModel.querySelector('[role="alert"]').hidden, 'Objects without suggestions must not show a malformed-response error');
        check(!domEmptyModel.querySelector('[role="status"]').hidden &&
            domEmptyModel.querySelector('[role="status"]').textContent === oTerms['SEARCH.NO_RESULTS'], 'Empty model response must show a translated info message');
        check(domEmptyModel.querySelector('tbody').children.length === 0, 'An empty object response must show an empty suggestion list');
        check(domEmptyModel.querySelector('.pico-modal-header').textContent === 'Dummy object (exface.Core.DUMMY)', 'Empty object response lost its title');
        check(domEmptyModel.querySelector('.spinner').parentNode.style.display === 'none', 'Empty object response left a loading spinner');
        domEmptyModel.querySelector('.pico-close').click();
        aResults.push('valid model responses with omitted attributes');

        bFail = true;
        oResponse = {error: {title: 'Model lookup failed', message: '<img src=x onerror=alert(1)>', hint: 'Check the relation', logid: 'MODEL123'}};
        aLeafItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        await wait();
        const domModelModal = document.querySelector('.uxoneditor-modal');
        const domModelError = domModelModal.querySelector('[role="alert"]');
        check(!domModelError.hidden && domModelError.textContent.includes('MODEL123'), 'Model errors must be visible inside the search dialog');
        check(domModelModal.querySelector('[role="status"]').hidden, 'Failed searches must not show a no-results message');
        check(domModelError.textContent.includes('Check the relation'), 'Model error hint was lost');
        check(!domModelError.querySelector('img'), 'Server error text was interpreted as HTML');
        check(domModelModal.querySelector('.spinner').parentNode.style.display === 'none', 'Failed search left a loading spinner');
        check(aErrors.length === 0, 'Model error opened a separate facade popup');
        bFail = false;
        oResponse = {};
        domModelModal.querySelector('#uxonModelBrowserSearch').value = 'retry';
        jQuery(domModelModal.querySelector('#uxonModelBrowserSearch')).trigger('input');
        await wait();
        check(!domModelError.hidden && domModelError.textContent === oTerms['ERROR.MALFORMED_RESPONSE'], 'Malformed model response was not shown inline');
        oResponse = {_TYPE: 'object', ATTRIBUTES: {}};
        jQuery(domModelModal.querySelector('#uxonModelBrowserSearch')).trigger('input');
        await wait();
        check(!domModelError.hidden, 'Non-array attributes must still be rejected');
        oResponse = {
            _TYPE: 'object', _BASE_OBJECT_NAME: 'Object', _BASE_OBJECT_ALIAS_NS: 'exface.Core.OBJECT',
            _BASE_OBJECT_ALIAS: 'OBJECT',
            ATTRIBUTES: [{NAME: 'Name', ALIAS: 'NAME', DESCRIPTION: 'Name', _SUGGEST: 'NAME'}]
        };
        jQuery(domModelModal.querySelector('#uxonModelBrowserSearch')).val('').trigger('input');
        await wait();
        check(domModelError.hidden && domModelError.textContent === '', 'Successful retry did not clear the inline error');
        check(domModelModal.querySelector('tbody tr'), 'Model search did not recover after an error');
        domModelModal.querySelector('.pico-close').click();
        aResults.push('inline model-search errors, safe server details and retry recovery');

        const aAjaxErrors = [];
        const oServerErrors = create(true, {value: 'NAME'}, {
            onAjaxError: (oRequest, domModal) => aAjaxErrors.push({oRequest, domModal})
        });
        const oServerEditor = oServerErrors.oAdapter.getEditor();
        const aServerItems = oServerEditor.options.onCreateMenu([], {path: ['value']});
        sErrorWidget = '<div class="server-error-widget"><button>Open error details</button></div>';
        oResponse = undefined;
        bFail = true;
        aServerItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        await wait();
        const domServerModel = document.querySelector('.uxoneditor-modal');
        check(aAjaxErrors.length === 1 && aAjaxErrors[0].oRequest.responseText === sErrorWidget, 'Model-search error widget must reach the facade intact');
        check(aAjaxErrors[0].domModal === domServerModel, 'Facade callback must receive the current popup for stacking');
        check(!domServerModel.querySelector('[role="alert"]').hidden, 'Server widgets must retain the inline model-search error');
        check(domServerModel.querySelector('[role="status"]').hidden, 'Server widgets must not show an empty-result message');
        check(!domServerModel.querySelector('.server-error-widget'), 'Shared adapter must not interpret facade error HTML itself');
        domServerModel.querySelector('.pico-close').click();

        const aServerRootItems = oServerEditor.options.onCreateMenu([], {path: []});
        aServerRootItems.find(oItem => oItem.text === oTerms['PRESETS.TITLE']).click();
        await wait();
        const domServerPresets = document.querySelector('.uxoneditor-modal');
        check(aAjaxErrors.length === 2 && aAjaxErrors[1].domModal === domServerPresets, 'Preset error widget must use the facade callback');
        check(domServerPresets.querySelector('.spinner').style.display === 'none', 'Preset error widget must stop the loading spinner');
        domServerPresets.querySelector('.pico-close').click();

        aServerRootItems.find(oItem => oItem.text === oTerms['DETAILS.TITLE']).click();
        await wait();
        check(aAjaxErrors.length === 3, 'Property-details error widget must use the facade callback');
        document.querySelector('.uxoneditor-modal .pico-close').click();

        let bServerRejected = false;
        try {
            await oServerEditor.options.autocomplete.getOptions('', [], 'value', oServerEditor);
        } catch (oError) {
            bServerRejected = true;
        }
        check(bServerRejected && aAjaxErrors.length === 4 &&
            aAjaxErrors[3].oRequest.responseText === sErrorWidget && aAjaxErrors[3].domModal === null,
            'Fetch error widgets must reject and preserve their body for the facade');
        check(aErrors.length === 0, 'Rich server widgets must not also open generic message popups');
        aServerItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        document.querySelector('.uxoneditor-modal .pico-close').click();
        await wait();
        check(aAjaxErrors.length === 4, 'Closed dialogs must not render late server errors');
        sErrorWidget = undefined;
        bFail = false;
        oServerErrors.oAdapter.destroy();
        aResults.push('server error widgets delegate intact to facade callbacks without duplicate or late popups');

        oUxon.oAdapter.setValue({child: ''});
        oResponse = {values: [], templates: {child: '{"widget_type":""}'}};
        await fnOptions('', ['child'], 'field', oJsonEditor);
        jQuery(oJsonEditor.node.findNodeByPath(['child']).dom.field).trigger('blur');
        await wait();
        check(oJsonEditor.get().child.widget_type === '', 'Field blur did not insert the UXON template');
        aResults.push('UXON property templates');

        oResponse = [{path: ['parent', 'required'], message: 'Required value'}];
        const oValidated = create(true, {parent: {required: ''}}, {validate: true});
        const oValidatedEditor = oValidated.oAdapter.getEditor();
        await oValidatedEditor.validate();
        check(oValidated.domHost.querySelector('[id$="_warning"]') !== null, 'Validation did not show the toolbar warning');
        check(oValidatedEditor.__cachedErrors.some(oError => oError.node === oValidatedEditor.node), 'Validation did not include parent breadcrumbs');
        oValidatedEditor.node.collapse();
        oValidatedEditor.options.onExpand();
        check(oValidatedEditor.errorNodes.includes(oValidatedEditor.node), 'Collapsed validation breadcrumb was lost');
        aResults.push('async UXON validation and collapsed error breadcrumbs');
        oValidated.oAdapter.destroy();

        oUxon.oAdapter.setDisabled(true);
        check(oJsonEditor.getMode() === 'view', 'Tree mode did not become read-only view mode');
        oUxon.oAdapter.setDisabled(false);
        check(oJsonEditor.getMode() === 'tree', 'Tree editing did not reenable');
        const oOther = create(true, {other: true});
        check(oOther.oAdapter !== oUxon.oAdapter, 'Editor instances share state');
        oUxon.oAdapter.destroy();
        check(oOther.oAdapter.getEditor().get().other === true, 'Destroying an editor broke its neighbor');
        check(!oUxon.domHost.querySelector('.uxoneditor-preset-hint'), 'Preset hint was leaked');
        check(!document.querySelector('.uxoneditor-modal'), 'Popup was leaked');
        aResults.push('dynamic disabled state, independent instances and destruction');
        check(aErrors.length === 0, 'Unexpected editor errors');
        return aResults;
    } finally {
        aEditors.forEach(oEditor => oEditor.destroy());
        aHosts.forEach(domHost => domHost.remove());
        window.fetch = fnFetch;
        jQuery.ajax = fnAjax;
    }
}
