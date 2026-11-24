const {test} = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {join} = require('node:path');
const exfTools = require(join(__dirname, '..', '..', 'Facades', 'AbstractAjaxFacade', 'js', 'exfTools.js'));
const snippets = JSON.parse(execFileSync('php', [
    '-d', 'display_errors=stderr',
    join(__dirname, '..', 'PHPUnit', 'Support', 'WidgetCoordinateJs.php')
], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit']}));

for (const [facade, getters] of Object.entries(snippets)) {
    test(facade + ' evaluates generated coordinate getters', function () {
        let rows = [
            {Col1: 'A', Col2: 4},
            {Col1: 'B', Col2: false},
            {Col1: 'A', Col2: 0}
        ];
        let selectedIndexes = [0, 2];
        const widget = {
            bLoaded: true,
            getData: () => rows,
            getValueGetterRow: () => null
        };
        const jqSpreadsheet = {
            0: {exfWidget: widget},
            jspreadsheet: () => selectedIndexes
        };
        const fixture_table = {
            rows: selector => ({
                data: () => ({
                    toArray: () => selector
                        ? selectedIndexes.map(index => rows[index])
                        : rows
                })
            })
        };
        function value(coordinate) {
            return new Function('exfTools', 'fixture_table', 'jqSpreadsheet',
                'return (' + getters[coordinate] + ');'
            )(exfTools, fixture_table, jqSpreadsheet);
        }

        assert.equal(value('Col1$~loaded:LIST'), 'A,B,A');
        assert.equal(value('Col1$~selected:LIST'), 'A,A');
        assert.equal(value('Col2$~loaded:SUM'), 4);
        assert.equal(value('Col2$~selected:SUM'), 4);
        assert.equal(value('Col2$2'), 0);
        assert.equal(value('Col2$1'), false);
        assert.equal(value('Col1$99'), '');
        assert.equal(value('Col1$~selected:LIST_DISTINCT'), 'A');
        assert.equal(value('default'), 'A,A');

        selectedIndexes = [1];
        assert.equal(value('Col1$~selected:LIST'), 'B');
        assert.equal(value('Col2$~selected:SUM'), 0);
        rows[0].Col2 = 7;
        assert.equal(value('Col2$~loaded:SUM'), 7);

        rows = [];
        selectedIndexes = [];
        assert.equal(value('Col1$~loaded:LIST'), '');
        assert.equal(value('Col2$~loaded:SUM'), 0);
        assert.equal(value('Col2$~loaded:AVG'), null);
    });
}
