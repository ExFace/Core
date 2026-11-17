/**
 * Run in Fixtures/JsonEditor.html with the real JSONEditor and mocked transports.
 */
export async function runJsonEditorAvailabilityRegression() {
    const fnFetch = window.fetch;
    const fnAjax = jQuery.ajax;
    const oOnlineDescriptor = Object.getOwnPropertyDescriptor(navigator, 'onLine');
    const oLauncherDescriptor = Object.getOwnPropertyDescriptor(window, 'exfLauncher');
    const oDictionary = await (await fnFetch('../../../Translations/exface.Core.en.json')).json();
    const oTerms = Object.fromEntries(Object.entries(oDictionary)
        .filter(([sKey]) => sKey.startsWith('WIDGET.UXONEDITOR.'))
        .map(([sKey, sValue]) => [sKey.substring('WIDGET.UXONEDITOR.'.length), sValue]));
    let bOnline = false;
    let bLauncherOnline = true;
    let bCompatible = true;
    let iRequests = 0;
    const aErrors = [];
    const domHost = document.createElement('div');
    domHost.id = 'json-availability';
    domHost.style.cssText = 'height:300px;position:relative';
    document.getElementById('fixtures').appendChild(domHost);
    let oAdapter;
    function check(bValue, sMessage) {
        if (!bValue) throw new Error(sMessage);
    }
    function wait() {
        return new Promise(fnResolve => setTimeout(fnResolve, 350));
    }
    function respond(oParams) {
        if (oParams.action === 'exface.Core.UxonValidate') return [];
        if (oParams.input === 'modelbrowser') {
            return {_TYPE: 'object', _BASE_OBJECT_NAME: 'Object', _BASE_OBJECT_ALIAS: 'OBJECT',
                _BASE_OBJECT_ALIAS_NS: 'exface.Core.OBJECT',
                ATTRIBUTES: [{NAME: 'Relation', ALIAS: 'REL', _RELATION: true, _SUGGEST: 'REL__'}]};
        }
        return {values: ['NAME'], templates: {}, _TYPE: 'object', ATTRIBUTES: []};
    }
    function badge() {
        return domHost.querySelector('.exf-jsoneditor-unavailable');
    }
    try {
        Object.defineProperty(navigator, 'onLine', {configurable: true, get: () => bOnline});
        Object.defineProperty(window, 'exfLauncher', {configurable: true, value: {isOnline: () => bLauncherOnline}});
        window.fetch = async (sUrl, oOptions) => {
            iRequests++;
            return new Response(JSON.stringify(respond(Object.fromEntries(oOptions.body))), {
                headers: {'Content-Type': 'application/json'}
            });
        };
        jQuery.ajax = oOptions => {
            iRequests++;
            const oDeferred = jQuery.Deferred();
            const oRequest = oDeferred.promise();
            oRequest.abort = () => oDeferred.reject({}, 'abort');
            queueMicrotask(() => oDeferred.resolve(respond(oOptions.data)));
            return oRequest;
        };
        oAdapter = exfJsonEditor.create(domHost, {
            uxon: true, autosuggest: true, validate: true, translations: oTerms,
            schema: () => 'widget', rootPrototype: () => '', rootObject: () => '',
            ajaxUrl: '/unused', serverAvailable: () => bCompatible,
            onError: oError => aErrors.push(oError)
        }, {value: 'NAME'});
        const oEditor = oAdapter.getEditor();
        const fnOptions = () => oEditor.options.autocomplete.getOptions('', [], 'value', oEditor);
        const aRootItems = oEditor.options.onCreateMenu([], {path: []});
        const aLeafItems = oEditor.options.onCreateMenu([], {path: ['value']});
        await wait();
        check(iRequests === 0, 'Initial offline validation must not send requests');
        check(badge() && !badge().hidden && badge().title === oTerms.SERVER_UNAVAILABLE, 'Offline state must show the translated, unobtrusive warning');
        await fnOptions();
        await oEditor.options.onValidate(oEditor.get());
        aRootItems.find(oItem => oItem.text === oTerms['PRESETS.TITLE']).click();
        aRootItems.find(oItem => oItem.text === oTerms['DETAILS.TITLE']).click();
        aLeafItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        check(!document.querySelector('.uxoneditor-modal') && iRequests === 0, 'Offline tools must not open loading dialogs or call AJAX');
        aRootItems.find(oItem => oItem.text === oTerms.JSON_PATH).click();
        check(document.querySelector('.uxoneditor-modal'), 'Local JSON-path tools must remain usable');
        document.querySelector('.uxoneditor-modal .pico-close').click();
        oAdapter.setValue('{"unfinished":');
        await wait();
        check(oAdapter.getValue() === '{"unfinished":', 'Offline editing must retain invalid text');
        check((await oEditor.validate()).length > 0, 'Local JSON syntax validation must still work offline');
        check(domHost.querySelectorAll('.exf-jsoneditor-unavailable').length === 1 && !badge().hidden, 'Code mode must keep one availability warning');
        oAdapter.setValue({value: 'NAME'});
        oEditor.setMode('tree');
        await wait();
        const oPointer = jQuery.Event('pointerdown');
        jQuery(domHost).trigger(oPointer);
        check(!oPointer.isDefaultPrevented(), 'The availability check must not block editing interactions');

        bOnline = true;
        window.dispatchEvent(new Event('online'));
        check(badge().hidden, 'Reconnection must clear the warning');
        check((await fnOptions())[0] === 'NAME', 'Reconnection must restore autosuggest without recreating the editor');
        await oEditor.options.onValidate(oEditor.get());
        check(iRequests >= 2, 'The compatible online backend must receive server requests');
        const fnMockFetch = window.fetch;
        let fnReject;
        window.fetch = () => new Promise((fnResolve, fnRejectFetch) => { fnReject = fnRejectFetch; });
        const oPendingSuggestion = fnOptions();
        bOnline = false;
        window.dispatchEvent(new Event('offline'));
        fnReject(new TypeError('Network disconnected'));
        check((await oPendingSuggestion).length === 0 && aErrors.length === 0,
            'Disconnecting during a request must show availability information, not an error popup');
        window.fetch = fnMockFetch;
        bOnline = true;
        window.dispatchEvent(new Event('online'));
        const iBeforeOffline = iRequests;
        bLauncherOnline = false;
        await fnOptions();
        await oEditor.options.onValidate(oEditor.get());
        check(iRequests === iBeforeOffline && !badge().hidden, 'Launcher forced-offline state must block custom requests');
        check(!oEditor._autosuggestLastResult, 'Offline state must discard stale suggestion templates');

        bLauncherOnline = true;
        bCompatible = false;
        window.dispatchEvent(new Event('online'));
        await fnOptions();
        await oEditor.options.onValidate(oEditor.get());
        aLeafItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        check(iRequests === iBeforeOffline && !badge().hidden, 'An incompatible backend must remain blocked even online');

        bCompatible = true;
        aLeafItems.find(oItem => oItem.text === oTerms['MODELBROWSER.TITLE']).click();
        await wait();
        check(document.querySelector('.uxoneditor-modal'), 'Online model search must recover');
        const iBeforeSearch = iRequests;
        bOnline = false;
        window.dispatchEvent(new Event('offline'));
        jQuery(document.querySelector('.uxoneditor-follow-relation')).trigger('click');
        check(iRequests === iBeforeSearch, 'A dialog opened online must not send new requests after disconnecting');
        check(document.querySelector('.uxoneditor-server-unavailable').textContent === oTerms.SERVER_UNAVAILABLE,
            'An already-open dialog must explain why its request is unavailable');
        document.querySelector('.uxoneditor-modal .pico-close').click();
        check(aErrors.length === 0, 'Unavailable server features must not show error popups');
        oAdapter.destroy();
        window.dispatchEvent(new Event('online'));
        check(!domHost.querySelector('.exf-jsoneditor-unavailable'), 'Destroy must remove the warning and connectivity listeners');
        return ['offline and incompatible adapters block all custom requests', 'local editing, syntax checks and tools remain available', 'live reconnect and forced-offline transitions update assistance and warning'];
    } finally {
        if (oAdapter) oAdapter.destroy();
        domHost.remove();
        window.fetch = fnFetch;
        jQuery.ajax = fnAjax;
        if (oOnlineDescriptor) Object.defineProperty(navigator, 'onLine', oOnlineDescriptor);
        else delete navigator.onLine;
        if (oLauncherDescriptor) Object.defineProperty(window, 'exfLauncher', oLauncherDescriptor);
        else delete window.exfLauncher;
    }
}
