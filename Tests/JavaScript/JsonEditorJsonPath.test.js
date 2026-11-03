/* Run: node Tests/JavaScript/JsonEditorJsonPath.test.js (from the Core directory). */
function testJsonEditorJsonPath(source) {
    var start = source.indexOf('        function {$funcPrefix}_buildJsonPath(');
    var end = source.indexOf('        function {$funcPrefix}_convertToArrayPath(', start);
    if (start < 0 || end < 0) throw new Error('JSONPath helper not found');
    var helper = source.slice(start, end).replace(/\{\$funcPrefix}/g, 'editor');
    var buildPath = new Function(helper + '; return editor_buildJsonPath;')();
    var checks = 0;

    function check(label, actual, expected) {
        checks++;
        if (actual !== expected) {
            throw new Error(label + ': expected ' + expected + ', got ' + actual);
        }
    }

    function path(items, index, filters) {
        return buildPath(['widgets', index, 'caption'], { widgets: items }, filters !== false);
    }

    check('root', buildPath([], {}, true), '$');
    check('prefer id', path([{ id: 'main', attribute_alias: 'NAME' }], 0),
        '$.widgets[?(@.id=="main")].caption');
    check('indexed field', path([{ id: 'main' }], 0, false), '$.widgets[0].caption');
    check('attribute alias', path([{ attribute_alias: 'NAME' }, { attribute_alias: 'TITLE' }], 1),
        '$.widgets[?(@.attribute_alias=="TITLE")].caption');
    check('duplicate id falls back to alias', path([
        { id: 'same', attribute_alias: 'NAME' }, { id: 'same', attribute_alias: 'TITLE' }
    ], 1), '$.widgets[?(@.attribute_alias=="TITLE")].caption');
    check('duplicates retain index', path([
        { id: 'same', attribute_alias: 'NAME' }, { id: 'same', attribute_alias: 'NAME' }
    ], 1), '$.widgets[1].caption');
    check('never generate widget_type or caption', path([{ widget_type: 'Map', caption: 'Unique' }], 0),
        '$.widgets[0].caption');
    [null, false, true, '', '  ', {}, []].forEach(function(value) {
        check('invalid id ' + JSON.stringify(value), path([{ id: value }], 0), '$.widgets[0].caption');
    });
    check('numeric id', path([{ id: 0 }, { id: 1 }], 0), '$.widgets[?(@.id==0)].caption');
    check('coerced duplicates', path([{ id: 1 }, { id: '1' }], 0), '$.widgets[0].caption');
    check('empty id falls back', path([{ id: '', attribute_alias: 'NAME' }], 0),
        '$.widgets[?(@.attribute_alias=="NAME")].caption');
    check('primitive array', buildPath([1], ['first', 'second'], true), '$[1]');
    check('null array element', buildPath(['widgets', 0], { widgets: [null] }, true), '$.widgets[0]');
    check('numeric object property', buildPath(['2026', 'field2'], { '2026': { field2: 1 } }, true),
        '$["2026"].field2');
    check('special property', buildPath(['a.b', 'a"b'], { 'a.b': { 'a"b': 1 } }, true),
        '$["a.b"]["a\\"b"]');
    ['a"b', 'a\\b', 'a\nb', 'a(b)', 'a[b]', 'a and b', 'a or b', 'a==b'].forEach(function(value) {
        check('unsafe filter value ' + JSON.stringify(value), path([{ id: value }], 0), '$.widgets[0].caption');
    });
    check('unsafe id falls back to alias', path([{ id: 'a"b', attribute_alias: 'NAME' }], 0),
        '$.widgets[?(@.attribute_alias=="NAME")].caption');
    check('unicode value', path([{ id: 'Kartenanzeige Tätigkeiten' }], 0),
        '$.widgets[?(@.id=="Kartenanzeige Tätigkeiten")].caption');

    var first = { id: 'first' };
    var second = { id: 'second' };
    check('reordering', path([first, second], 1), path([second, first], 0));
    var nested = { widgets: [{ id: 'orders', columns: [{ attribute_alias: 'NAME' }] }] };
    var before = JSON.stringify(nested);
    check('nested filters', buildPath(['widgets', 0, 'columns', 0, 'caption'], nested, true),
        '$.widgets[?(@.id=="orders")].columns[?(@.attribute_alias=="NAME")].caption');
    check('no mutation', JSON.stringify(nested), before);
    var details = {};
    var complexAlias = 'ORDER__CUSTOMER__COUNTRY__NAME';
    var complexRoot = { widgets: [{ attribute_alias: complexAlias }] };
    check('complex alias retained', buildPath(['widgets', 0], complexRoot, true, details),
        '$.widgets[?(@.attribute_alias=="' + complexAlias + '")]');
    check('three relation hops warn', details.complexFilters.length, 1);
    check('warning locates object', details.complexFilters[0].location, '$.widgets[0]');
    check('warning includes alias', details.complexFilters[0].alias, complexAlias);
    check('filter count', details.filterCount, 1);
    check('index count', details.indexCount, 0);
    complexRoot.widgets[0].id = 'short';
    check('prefer short id to deep alias', buildPath(['widgets', 0], complexRoot, true, details),
        '$.widgets[?(@.id=="short")]');
    check('unused alias does not warn', details.complexFilters.length, 0);
    [63, 64, 65].forEach(function(length) {
        buildPath(['widgets', 0], { widgets: [{ attribute_alias: 'A'.repeat(length) }] }, true, details);
        check('length threshold ' + length, details.complexFilters.length, length > 64 ? 1 : 0);
    });
    buildPath(['widgets', 0], { widgets: [{ attribute_alias: 'A__B__C' }] }, true, details);
    check('two relation hops do not warn', details.complexFilters.length, 0);
    buildPath(['widgets', 0], { widgets: [
        { attribute_alias: complexAlias }, { attribute_alias: complexAlias }
    ] }, true, details);
    check('rejected complex alias does not warn', details.complexFilters.length, 0);
    check('positional status count', details.indexCount, 1);
    check('metadata reset', details.filterCount, 0);
    buildPath([], {}, true, details);
    check('root metadata reset', details.indexCount, 0);
    return checks;
}

/* Also exercised by the browser harness, where a real DOM is available. */
function testJsonEditorJsonPathView(source) {
    var names = ['getJsonPathViewContent', 'initJsonPathCopy', 'buildJsonPath', 'loadJsonPathView'];
    var functions = names.map(function(name) {
        var start = source.indexOf('        function {$funcPrefix}_' + name + '(');
        var end = source.indexOf('\n        function ', start + 1);
        if (start < 0 || end < 0) throw new Error('Missing function: ' + name);
        return source.slice(start, end);
    }).join('\n').replace(/\{\$funcPrefix}/g, 'editor')
        .replace(/\{\$jsonPathHelpJs}/g, JSON.stringify({
            indexed: 'Indexed', suggested: 'Suggested', help: 'About filters',
            explanation: 'Filters select by value.', readMore: 'Guide', url: '/docs/JSONPath/index.md',
            indexedDescription: 'Position now.', suggestedDescription: 'Identifiers where possible.',
            copyIndexed: 'Copy indexed path', copySuggested: 'Copy suggested path',
            helpIdentifiers: 'Prefer id, then alias.', helpFallback: 'Otherwise keep indices.',
            helpAvoid: 'Do not generate widget types or captions.', helpReview: 'Review uniqueness.',
            helpNested: 'Nested filters are not portable.', warningTitle: 'Complex filter',
            warningText: 'Consider a stable id.', warningLocations: 'Affected objects:',
            statusIndices: 'Contains indices', statusFilters: 'Uses filters', statusDirect: 'Direct path'
        })).replace(/\{\$trans\['[^']+']}/g, 'Translated');
    var copied = [];
    var api = new Function('exfTools', '$', functions +
        '; return {content: editor_getJsonPathViewContent, load: editor_loadJsonPathView, copy: editor_initJsonPathCopy};')(
        { clipboard: { copyText: function(text) { copied.push(text); } } },
        { messager: { show: function() {} } }
    );
    var checks = 0;
    function check(condition, label) {
        checks++;
        if (!condition) throw new Error(label);
    }
    var containers = [document.createElement('div'), document.createElement('div')];
    containers.forEach(function(container, index) {
        container.className = 'jsoneditor-modal jsonPathView';
        container.innerHTML = api.content();
        document.body.appendChild(container);
        var modal = { modalElem: function() { return container; } };
        var root = { widgets: [{ id: 'widget' + index }] };
        api.load(modal, {
            getPath: function() { return ['widgets', 0, 'id']; },
            editor: { get: function() { return root; }, options: { name: 'UXON' } }
        });
        api.copy(modal);
        check(container.querySelectorAll('textarea[readonly]').length === 2, 'Two read-only fields');
        check(container.querySelector('#jsonPathView').value === '$.widgets[0].id', 'Indexed field');
        var suggested = '$.widgets[?(@.id=="widget' + index + '")].id';
        check(container.querySelector('#jsonPathSuggestedView').value === suggested, 'Modal-scoped suggestion');
        var buttons = container.querySelectorAll('[data-jsonpath-copy]');
        buttons[0].click();
        check(copied[copied.length - 1] === '$.widgets[0].id', 'Copy indexed field');
        buttons[1].click();
        check(copied[copied.length - 1] === suggested, 'Copy suggested field');
        var details = container.querySelector('details');
        check(!details.open, 'Help starts collapsed');
        details.querySelector('summary').click();
        check(details.open, 'Information icon expands help');
        check(details.querySelector('a').getAttribute('href') === '/docs/JSONPath/index.md', 'Documentation link');
        check(details.querySelectorAll('li').length === 5, 'Help is split into short bullets');
        check(buttons[1].classList.contains('jsonpath-copy-primary'), 'Suggested copy is the primary action');
        check(container.querySelector('#jsonPathStatus').textContent === 'Uses filters', 'Filter status');
        var warning = container.querySelector('#jsonPathWarning');
        var suggestedField = container.querySelector('#jsonPathSuggestedView');
        check(warning.hidden, 'No warning for short id');
        check(suggestedField.getAttribute('aria-describedby').indexOf('jsonPathWarning') === -1,
            'Hidden warning is not announced for a short id');
        root.widgets[0] = { attribute_alias: 'ORDER__CUSTOMER__COUNTRY__NAME' };
        var node = {
            getPath: function() { return ['widgets', 0, 'id']; },
            editor: { get: function() { return root; }, options: { name: 'UXON' } }
        };
        api.load(modal, node);
        check(!warning.hidden, 'Complex alias shows warning');
        check(suggestedField.getAttribute('aria-describedby').indexOf('jsonPathWarning') !== -1,
            'Complex filter warning is included in the accessible description');
        check(suggestedField.classList.contains('jsonpath-complex'), 'Complex alias highlights the field');
        check(warning.querySelector('code').textContent === '$.widgets[0]', 'Warning identifies object for an id');
        check(!suggestedField.hasAttribute('aria-invalid'), 'Readability warning is not a syntax error');
        buttons[1].click();
        check(copied[copied.length - 1] === suggestedField.value, 'Complex path remains copyable');
        root.widgets[0].id = 'widget' + index;
        api.load(modal, node);
        check(warning.hidden && !suggestedField.classList.contains('jsonpath-complex'), 'Adding id clears warning');
        check(suggestedField.getAttribute('aria-describedby').indexOf('jsonPathWarning') === -1,
            'Adding id also clears the warning description');
        check(warning.querySelectorAll('li').length === 0, 'Old warning locations cleared');
        root.widgets[0] = { widget_type: 'Map', caption: 'Unique' };
        api.load(modal, node);
        check(suggestedField.value === '$.widgets[0].id', 'Fragile filters remain excluded');
        check(container.querySelector('#jsonPathStatus').textContent === 'Contains indices', 'Positional status');
        root.widgets[0].id = 'widget' + index;
        api.load(modal, node);
    });
    check(containers[0].querySelector('#jsonPathSuggestedView').value.indexOf('widget0') !== -1,
        'Second dialog does not overwrite the first');
    containers.forEach(function(container) { container.remove(); });
    return checks;
}

if (typeof module !== 'undefined' && module.exports) {
    var fs = require('node:fs');
    var path = require('node:path');
    var source = fs.readFileSync(path.join(__dirname,
        '../../Facades/AbstractAjaxFacade/Elements/JsonEditorTrait.php'), 'utf8');
    console.log('Passed ' + testJsonEditorJsonPath(source) + ' JSONPath checks.');
}



