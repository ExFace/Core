/**
 * Run in Fixtures/JsonEditor.html with real JSONEditor and only HTTP mocked.
 */
export async function runJsonEditorAutosuggestErrorRegression() {
    const fnFetch = window.fetch;
    const domHost = document.createElement('div');
    domHost.style.height = '300px';
    document.getElementById('fixtures').appendChild(domHost);
    const aErrors = [];
    const sDiagnostic = '// ERROR: Cannot read annotations. See Log-ID TEST123';
    let oAdapter;
    try {
        window.fetch = async () => new Response(JSON.stringify({values: [sDiagnostic]}), {
            headers: {'Content-Type': 'application/json'}
        });
        oAdapter = exfJsonEditor.create(domHost, {
            uxon: true,
            autosuggest: true,
            validate: false,
            translations: {},
            ajaxUrl: '/json-regression-api',
            schema: () => 'widget',
            rootPrototype: () => '',
            rootObject: () => '',
            onError: oError => aErrors.push(oError),
            onAjaxError: oError => aErrors.push(oError)
        }, {attribute_alias: 'Massnahme'});
        const oEditor = oAdapter.getEditor();
        const oAutocomplete = oEditor.options.autocomplete;
        const aValues = await oAutocomplete.getOptions('Massnahme', ['attribute_alias'], 'value', oEditor);
        if (aValues.length !== 1 || aValues[0] !== sDiagnostic ||
            !oAutocomplete.filter('Massnahme', aValues[0])) {
            throw new Error('Diagnostic suggestions must survive nonmatching search text');
        }
        if (oAutocomplete.filter('Massnahme', 'unrelated')) {
            throw new Error('Ordinary suggestions must still match the search text');
        }
        if (aErrors.length !== 0) {
            throw new Error('Diagnostic suggestions must not open an error popup');
        }
        return ['diagnostic suggestions remain visible without popups; ordinary filtering is preserved'];
    } finally {
        if (oAdapter) oAdapter.destroy();
        domHost.remove();
        window.fetch = fnFetch;
    }
}
