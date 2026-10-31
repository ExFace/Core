/* Requires jSuites, jSpreadsheet CE and the PHP renderer source supplied by the browser runner. */
function runJExcelDisplayGridTests(sSource) {
    var aMatch = sSource.match(/return <<<'JS'\r?\n([\s\S]*?)\r?\nJS;/);
    var oRenderer = new Function('return ' + aMatch[1])();
    var domGrid = document.createElement('div');
    document.body.appendChild(domGrid);
    var iChecks = 0;
    function check(sLabel, mActual, mExpected) {
        iChecks++;
        if (mActual !== mExpected) throw new Error(sLabel + ': expected ' + mExpected + ', got ' + mActual);
    }
    var oDisplay = {
        type: 'progress', min: 0, max: 100, color: null, colorScale: {'50': 'red', '100': 'green'},
        colorRange: true, hintScale: {}, hintRange: true, hint: '', text: null,
        textColumn: 'LABEL', textScale: {}, format: function(mValue) { return mValue; },
        formatText: function(mValue) { return mValue; }
    };
    var oExcel = jspreadsheet(domGrid, {
        data: [[]], minDimensions: [3, 1], allowInsertColumn: false,
        columns: [
            {type: 'numeric', title: 'Progress', readOnly: true},
            {type: 'text', title: 'Editable'},
            {type: 'hidden', title: 'Label'}
        ],
        updateTable: function(domInstance, domCell, iCol, iRow, mValue) {
            if (domInstance.exfWidget) {
                oRenderer.renderDisplayCell.call(domInstance.exfWidget, domCell, iCol, iRow, mValue);
            }
        }
    });
    domGrid.exfWidget = {
        _displayRenderer: oRenderer, _displaySemanticColors: {},
        getJExcel: function() { return oExcel; },
        getColumnIndex: function(sName) { return sName === 'LABEL' ? 2 : -1; },
        getColumnModel: function(iCol) { return iCol === 0 ? {display: oDisplay} : {}; }
    };
    try {
        oExcel.setData([[25, 'A', 'First'], [75, 'B', 'Second']]);
        check('load progress', oExcel.getCellFromCoords(0, 0).textContent, 'First');
        check('load width', oExcel.getCellFromCoords(0, 0).firstElementChild.firstElementChild.style.width, '25%');
        check('ordinary editable cell', oExcel.getCellFromCoords(1, 0).textContent, 'A');
        oExcel.setValueFromCoords(1, 0, 'Changed');
        check('ordinary editing unaffected', oExcel.getValueFromCoords(1, 0), 'Changed');
        check('decoration survives unrelated edit', oExcel.getCellFromCoords(0, 0).textContent, 'First');
        oExcel.setValueFromCoords(2, 0, 'New label', true);
        check('auxiliary edit updates decoration', oExcel.getCellFromCoords(0, 0).textContent, 'New label');
        oExcel.undo();
        check('undo refreshes label', oExcel.getCellFromCoords(0, 0).textContent, 'First');
        oExcel.redo();
        check('redo refreshes label', oExcel.getCellFromCoords(0, 0).textContent, 'New label');
        oExcel.orderBy(0, 1);
        check('sorting moves values', Number(oExcel.getData(false)[0][0]), 75);
        check('sorting moves label with row', oExcel.getCellFromCoords(0, 0).textContent, 'Second');
        check('sorting moves bar with row', oExcel.getCellFromCoords(0, 0).firstElementChild.firstElementChild.style.width, '75%');
        var sCopy = oExcel.copy(false, '\t', true);
        check('clipboard excludes markup', sCopy.indexOf('<div'), -1);
        check('clipboard retains numeric value', sCopy.indexOf('75\tB') >= 0, true);
        check('getData retains raw number', Number(oExcel.getData(false)[0][0]), 75);
        oExcel.setData([[null, '', null]]);
        check('reload clears progress', oExcel.getCellFromCoords(0, 0).children.length, 0);
        Object.assign(oDisplay, {
            type: 'color', color: null, colorColumn: null, colorRange: false,
            colorScale: ['lightsteelblue', 'steelblue'], colorOnly: false, fill: true,
            format: function(value) { return Number(value) === 1 ? 'Yes' : 'No'; }
        });
        oExcel.setData([[0, 'A', null], [1, 'B', null]]);
        check('Boolean zero label', oExcel.getCellFromCoords(0, 0).textContent, 'No');
        check('Boolean zero background', oExcel.getCellFromCoords(0, 0).firstElementChild.style.backgroundColor, 'lightsteelblue');
        check('Boolean one label', oExcel.getCellFromCoords(0, 1).textContent, 'Yes');
        check('Boolean one background', oExcel.getCellFromCoords(0, 1).firstElementChild.style.backgroundColor, 'steelblue');
        oExcel.setValueFromCoords(0, 0, 1, true);
        check('Boolean update uses new value', oExcel.getCellFromCoords(0, 0).firstElementChild.style.backgroundColor, 'steelblue');
        check('Boolean data remains scalar', Number(oExcel.getData(false)[0][0]), 1);
        return iChecks + ' grid integration checks passed';
    } finally {
        jspreadsheet.destroy(domGrid);
        domGrid.remove();
    }
}
