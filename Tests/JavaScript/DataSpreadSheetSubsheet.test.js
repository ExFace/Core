/* Run: node Tests/JavaScript/DataSpreadSheetSubsheet.test.js (from Core).
 * Also callable from a browser as testDataSpreadSheetSubsheet(traitSource).
 */
function testDataSpreadSheetSubsheet(source) {
    var checks = 0;
    function check(label, actual, expected) {
        checks++;
        if (JSON.stringify(actual) !== JSON.stringify(expected)) {
            throw new Error(label + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual));
        }
    }
    function throws(label, callback) {
        var failed = false;
        try { callback(); } catch (error) { failed = true; }
        check(label, failed, true);
    }
    var start = source.indexOf('        getSubsheet: function(');
    var end = source.indexOf('        setDisabled: function(', start);
    if (start < 0 || end < 0) throw new Error('Subsheet methods not found');
    var methods = source.slice(start, end)
        .replace(/\{\$allowEmptyRows\}/g, 'false')
        .replace(/\{\$this->getMinSpareRows\(\)\}/g, '1');
    var widget = new Function('$', 'return ({' + methods + '});')(function() {});
    var binding = {
        relation: 'Links', column: 'Links', attribute: 'Location',
        parentUid: 'Id', object: 'example.Link', multiple: true, delimiter: ';'
    };
    var original = [
        {Id: 'A', Name: 'Event A', Links: {object_alias: 'example.Link', rows: [
            {Id: 'L1', Location: 'X', Event: 'A', Created: 'yesterday'},
            {Id: 'L2', Location: 'Y', Event: 'A'}
        ]}},
        {Id: 'B', Name: 'Event B', Links: {object_alias: 'example.Link', rows: [
            {Id: 'L3', Location: 'Z', Event: 'B'}
        ]}}
    ];
    var snapshot = JSON.stringify(original);
    widget._initData = original;
    widget.getDataLastLoaded = function() { return this._initData; };
    widget._rowNumberColName = null;
    widget._colNames = {Id: 'Id', Name: 'Name', Locations: '__locations'};
    widget._cols = {
        Id: {dataColumnName: 'Id', hidden: true},
        Name: {dataColumnName: 'Name', hidden: false},
        __locations: {dataColumnName: '__locations', hidden: false, subsheet: binding}
    };
    widget.getColumnModel = function(index) { return this._cols[['Id', 'Name', '__locations'][index]]; };
    widget.getColumnName = function(index) { return this.getColumnModel(index).dataColumnName; };
    widget._dom = {jspreadsheet: {getHeaders: function() { return 'Id,Name,Locations'; }}};
    widget.getChangedRowIndexes = function() { return {0: true}; };

    check('project existing children', widget.getSubsheetCellValue(original[0], binding), 'X;Y');
    check('load cells', widget.convertDataToArray(original), [['A', 'Event A', 'X;Y'], ['B', 'Event B', 'Z']]);
    var result = widget.convertArrayToData([['B', 'Event B', 'Z'], ['A', 'Event A', 'X;Y'], []]);
    check('sorted rows retain child UID', result[0].Links.rows[0].Id, 'L3');
    check('second sorted row retains child UID', result[1].Links.rows[0].Id, 'L1');
    check('system fields survive', result[1].Links.rows[0].Created, 'yesterday');
    var initStart = source.indexOf('        getInitValue: function(');
    var initEnd = source.indexOf('        restoreInitValue:', initStart);
    var initGetter = new Function('return ({' + source.slice(initStart, initEnd) + '});')().getInitValue;
    widget.getColumnIndex = function() { return 0; };
    widget.getJExcel = function() { return {getValueFromCoords: function() { return 'B'; }}; };
    check('restoring after sorting uses parent UID', initGetter.call(widget, 2, 0), 'Z');
    widget.getJExcel = function() { return {getValueFromCoords: function() { return ''; }}; };
    check('new row has no initial child selection', initGetter.call(widget, 2, 0), '');
    check('virtual scalar never submitted', Object.prototype.hasOwnProperty.call(result[0], '__locations'), false);
    check('new selection creates a child', widget.buildSubsheet('X;NEW', binding, {Id: 'A'}).rows,
        [original[0].Links.rows[0], {Location: 'NEW'}]);
    check('deselection excludes the removed child', widget.buildSubsheet('Y', binding, {Id: 'A'}).rows,
        [original[0].Links.rows[1]]);
    check('cleared relation is an explicit empty sheet', widget.buildSubsheet('', binding, {Id: 'A'}),
        {object_alias: 'example.Link', rows: []});
    check('new parent does not inherit existing links', widget.buildSubsheet('X', binding, {}).rows, [{Location: 'X'}]);
    check('duplicate selections do not create duplicate children', widget.buildSubsheet('X;X', binding, {Id: 'A'}).rows.length, 1);
    original[0].Links.rows.push({Id: 'L4', Location: 'X', Event: 'A'});
    check('duplicate stored links retain both identities', widget.buildSubsheet('X', binding, {Id: 'A'}).rows.map(function(row) { return row.Id; }), ['L1', 'L4']);
    original[0].Links.rows.pop();
    check('array values accepted', widget.buildSubsheet(['X', 'Y'], binding, {Id: 'A'}).rows.length, 2);
    check('trailing empty selection does not create a child', widget.buildSubsheet('X;', binding, {Id: 'A'}).rows,
        [original[0].Links.rows[0]]);
    check('leading and repeated empty selections ignored', widget.buildSubsheet(';X;;Y;', binding, {Id: 'A'}).rows,
        original[0].Links.rows);
    check('empty array selections ignored without losing zero keys', widget.buildSubsheet(['', null, undefined, 0, '8', '  '], binding, {}).rows,
        [{Location: '0'}, {Location: '8'}]);
    check('only empty tokens still clear the collection', widget.buildSubsheet(';;;', binding, {Id: 'A'}).rows, []);
    check('single selection counts nonempty entries only', widget.buildSubsheet('X;', Object.assign({}, binding, {multiple: false}), {Id: 'A'}).rows,
        [original[0].Links.rows[0]]);
    var emptyParent = {Id: 'C', Name: 'Cleared event', Links: {object_alias: 'example.Link', rows: []}};
    widget._initData = original.concat([emptyParent]);
    check('reopened empty collection projects an empty cell', widget.convertDataToArray([emptyParent]), [['C', 'Cleared event', '']]);
    check('re-adding after clearing submits only the selected link', widget.convertArrayToData([['C', 'Cleared event', '8;'], []])[0].Links.rows,
        [{Location: '8'}]);
    widget._initData = [{Id: 1329, MassnahmeZuArt: {object_alias: 'onelink.BMDB.MassnahmeZuArt', rows: []}}];
    var artBinding = {relation: 'MassnahmeZuArt', column: 'MassnahmeZuArt', attribute: 'Art',
        parentUid: 'Id', object: 'onelink.BMDB.MassnahmeZuArt', multiple: true, delimiter: ';'};
    check('LOG-23AFDADB: Art 8 with an empty token produces one row', widget.buildSubsheet('8;', artBinding, {Id: 1329}),
        {object_alias: 'onelink.BMDB.MassnahmeZuArt', rows: [{Art: '8'}]});
    widget._initData = original;
    check('empty new rows omitted', widget.convertArrayToData([['', '', ''], []]), []);
    check('child-only new row retained', widget.convertArrayToData([['', '', 'X'], []])[0].Links.rows, [{Location: 'X'}]);
    check('changed rows mode retained', widget.convertArrayToData([['A', 'Event A', 'Y'], ['B', 'Event B', 'Z'], []], true).length, 1);
    throws('unloaded existing relation cannot be replaced', function() { widget.buildSubsheet('X', binding, {Id: 'missing'}); });
    throws('single selection refuses multiple existing children', function() {
        widget.getSubsheetCellValue(original[0], Object.assign({}, binding, {multiple: false}));
    });
    throws('single selection refuses pasted multiple values', function() {
        widget.buildSubsheet('X;Y', Object.assign({}, binding, {multiple: false}), {Id: 'A'});
    });
    throws('malformed nested payload rejected', function() { widget.getSubsheet({Links: {rows: null}}, binding); });
    check('stringified subsheet accepted', widget.getSubsheetCellValue({Links: JSON.stringify(original[0].Links)}, binding), 'X;Y');
    check('getter payload accepts unsanitized relation alias', widget.getSubsheetCellValue({'Links[Event]': original[0].Links},
        Object.assign({}, binding, {relation: 'Links[Event]', column: 'Links_Event_'})), 'X;Y');
    original[0].Links.filters = {conditions: [{expression: 'Location', value: 'X'}]};
    check('read filters not submitted as deletion scope', Object.prototype.hasOwnProperty.call(widget.buildSubsheet('Y', binding, {Id: 'A'}), 'filters'), false);
    delete original[0].Links.filters;
    check('loading and saving do not mutate source', JSON.stringify(original), snapshot);

    var validatorStart = source.indexOf('$validatorJs = "(function(fn, delimiter)');
    var validatorEnd = source.indexOf('";', validatorStart);
    if (validatorStart < 0 || validatorEnd < 0) throw new Error('Subsheet validator not found');
    var validatorTemplate = source.slice(validatorStart + '$validatorJs = "'.length, validatorEnd);
    [',', '|', '::', '; '].forEach(function(delimiter) {
        var custom = Object.assign({}, binding, {delimiter: delimiter});
        check('attribute delimiter used when loading: ' + delimiter,
            widget.getSubsheetCellValue(original[0], custom), 'X' + delimiter + 'Y');
        check('attribute delimiter used when saving: ' + delimiter,
            widget.buildSubsheet('X' + delimiter + 'Y', custom, {Id: 'A'}).rows, original[0].Links.rows);
        check('empty tokens ignored with attribute delimiter: ' + delimiter,
            widget.buildSubsheet(delimiter + 'X' + delimiter + delimiter, custom, {Id: 'A'}).rows,
            [original[0].Links.rows[0]]);
        check('delimiter-only value clears children: ' + delimiter,
            widget.buildSubsheet(delimiter + delimiter, custom, {Id: 'A'}).rows, []);
        var seen = [];
        var validator = new Function('fn', 'return ' + validatorTemplate
            .replace('{$validatorJs}', 'fn').replace('{$delimiterJs}', JSON.stringify(delimiter)))(function(value) {
                seen.push(value);
                return value === 'INVALID' ? 'Invalid key' : true;
            });
        check('per-key validation succeeds: ' + delimiter, validator('X' + delimiter + 'Y' + delimiter), true);
        check('validator splits using attribute delimiter: ' + delimiter, seen, ['X', 'Y']);
        check('invalid selection still rejected: ' + delimiter, validator('X' + delimiter + 'INVALID'), 'Invalid key');
        throws('ambiguous key containing attribute delimiter rejected: ' + delimiter, function() {
            widget.getSubsheetCellValue({Links: {rows: [{Location: 'X' + delimiter + 'Y'}]}}, custom);
        });
    });
    check('semicolon in a key is allowed when the attribute delimiter is comma',
        widget.getSubsheetCellValue({Links: {rows: [{Location: 'X;Y'}, {Location: 'Z'}]}},
            Object.assign({}, binding, {delimiter: ','})), 'X;Y,Z');

    var conditionStart = source.indexOf('        refreshConditionalProperties: function(');
    var conditionEnd = source.indexOf('        isDropdownValueValid:', conditionStart);
    var conditionWidget = new Function('return ({' + source.slice(conditionStart, conditionEnd) + '});')();
    var cleared = 0;
    conditionWidget.refreshConditionalColumnVisibility = function() {};
    conditionWidget.getDoNotValidate = function() { return false; };
    conditionWidget._cols = {};
    conditionWidget.getColumnModel = function() { return {subsheet: binding}; };
    conditionWidget.isDropdownValueValid = function() { return false; };
    conditionWidget.getJExcel = function() { return {
        getData: function() { return [['X;Y']]; },
        getConfig: function() { return {columns: [{}]}; },
        getColumnOptions: function() { return {type: 'autocomplete', filter: function() { return []; }}; },
        setValueFromCoords: function() { cleared++; }
    }; };
    conditionWidget.refreshConditionalProperties();
    check('suggestion filter does not clear child selections', cleared, 0);
    conditionWidget.getColumnModel = function() { return {}; };
    conditionWidget.refreshConditionalProperties();
    check('ordinary dropdown behavior unchanged', cleared, 1);
    return checks;
}

if (typeof module !== 'undefined' && module.exports) {
    var fs = require('fs');
    var path = require('path');
    var source = fs.readFileSync(path.join(__dirname, '../../Facades/AbstractAjaxFacade/Elements/JExcelTrait.php'), 'utf8');
    console.log('Passed ' + testDataSpreadSheetSubsheet(source) + ' subsheet checks.');
}



