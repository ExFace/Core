/* Browser test: load jQuery, jSuites, installed jspreadsheet CE 4.15,
 * exfJExcelDisplayRenderers.js and this file, then call testDataSpreadSheetDisplay().
 * Fixtures are local DOM only; no application actions or writes.
 */
function testDataSpreadSheetDisplay() {
    'use strict';
    var iChecks = 0;
    function check(sName, mActual, mExpected) {
        iChecks++;
        if (!Object.is(mActual, mExpected)) {
            throw new Error(sName + ': expected ' + String(mExpected) + ', got ' + String(mActual));
        }
    }
    function config(oOverrides) {
        return Object.assign({
            numeric: true, colorNumeric: true, decimalSeparator: ',', groupSeparator: '.',
            color: null, colorColumn: null, colorScale: [], colorRange: true,
            semanticColors: {'~OK': 'green', '~ERROR': 'red'},
            hint: '', hintScale: [], hintRange: true,
            colorOnly: false, fill: true, min: 10, max: 110,
            text: null, textColumn: null, textScale: [],
            format: function (mValue) { return mValue; }
        }, oOverrides);
    }
    var domRoot = document.createElement('div');
    document.body.appendChild(domRoot);
    var oWidget;
    var oModule = exfJExcelDisplayRenderers;
    var oProgress = oModule.createProgressBar(config({
        color: 'blue', colorScale: [[60, '~ERROR'], [110, '~OK']],
        textScale: [[60, 'Half <done>'], [110, 'Finished']],
        hintScale: [[60, 'Low "hint"'], [110, 'High']]
    }), function () { return oWidget; });
    var oColor = oModule.createColorIndicator(config({
        numeric: false, colorColumn: 'Color', colorRange: false, colorNumeric: false,
        format: function (mValue) { return mValue === 'A' ? '<img src=x onerror=alert(1)>' : mValue; }
    }), function () { return oWidget; });
    var oText = oModule.createProgressBar(config({
        textColumn: 'Label', color: 'gold', text: 'Static ignored',
        formatText: function (mValue) { return mValue; }
    }), function () { return oWidget; });
    var oTable = jspreadsheet(domRoot, {
        data: [[]], minSpareRows: 0, allowDeletingAllRows: true,
        columns: [
            {title: 'Value', type: 'numeric', readOnly: true, editor: oProgress},
            {title: 'Name', type: 'text', readOnly: true, editor: oColor},
            {title: 'Color', type: 'text'},
            {title: 'Labelled', type: 'numeric', readOnly: true, editor: oText},
            {title: 'Dropdown', type: 'dropdown', source: [{id: 'key', name: 'Dropdown label'}]}
        ],
        onevent: function (sEvent, domElement, domCell, iColumn, iRow) {
            if (oWidget) {
                oModule.onEvent(oWidget, sEvent, iRow);
            }
        }
    });
    oWidget = {
        getJExcel: function () { return oTable; },
        getColumnIndex: function (sName) { return ['Value', 'Name', 'Color', 'Labelled', 'Dropdown'].indexOf(sName); },
        getColumnModel: function () { return {}; }
    };
    oModule.install(oWidget);
    function cell(iColumn, iRow) { return oTable.records[iRow || 0][iColumn]; }
    function fill(iColumn, iRow) { return cell(iColumn, iRow).querySelector('.exf-jexcel-progress-fill'); }
    function load(aRows, aSource) { oModule.setData(oWidget, aRows, aSource); }
    try {
        check('Installed v4 version', jspreadsheet.version().version, '4.15.0');
        load([[60, 'A', 'purple', 35, 'key'], [110, 'B', 'orange', 70, 'key']],
            [{Label: '<script>safe</script>'}, {Label: 'Second'}]);
        check('Progress subtracts min', fill(0).style.width, '50%');
        check('Progress scale uses value, not static base color', fill(0).style.backgroundColor, 'red');
        check('Threshold text', cell(0).textContent, 'Half <done>');
        check('Hint scale', cell(0).firstChild.title, 'Low "hint"');
        check('Label is text, not markup', cell(1).querySelector('img'), null);
        check('Safe formatted label', cell(1).textContent, '<img src=x onerror=alert(1)>');
        check('Editable dependency', cell(1).firstChild.style.backgroundColor, 'purple');
        check('Auxiliary label binding', cell(3).textContent, '<script>safe</script>');
        check('No auxiliary script element', cell(3).querySelector('script'), null);
        var domPrevious = cell(0).firstChild;
        oModule.refresh(oWidget);
        oModule.refresh(oWidget);
        check('Refresh is idempotent', cell(0).firstChild, domPrevious);
        check('Refresh does not mark changes', domRoot.querySelector('.exf-spreadsheet-change'), null);
        check('Typed number preserved', oTable.getData(false, true)[0][0], 60);
        check('Enum key preserved', oTable.getData(false, true)[0][1], 'A');

        oTable.setValueFromCoords(2, 0, 'blue', true);
        check('Dependency edit refresh', cell(1).firstChild.style.backgroundColor, 'blue');
        oTable.undo();
        check('Dependency undo refresh', cell(1).firstChild.style.backgroundColor, 'purple');
        oTable.redo();
        check('Dependency redo refresh', cell(1).firstChild.style.backgroundColor, 'blue');
        oTable.orderBy(0, 1);
        check('Sorted auxiliary fields follow row', cell(3).textContent, 'Second');
        oTable.moveRow(0, 1);
        check('Moved auxiliary fields follow row', cell(3).textContent, '<script>safe</script>');
        oTable.deleteRow(0, 1);
        check('Delete retains remaining row context', cell(3).textContent, 'Second');
        oTable.undo();
        check('Delete undo retains row context', cell(3).textContent, '<script>safe</script>');
        oTable.insertRow(1, 0, true);
        check('Inserted row does not inherit auxiliary fields', cell(3).textContent, '');
        oTable.undo();
        check('Insertion undo restores context', cell(3).textContent, '<script>safe</script>');

        [0, -25, 200, '60,25', '60.25', '9007199254740993', null, '', 'not numeric', Infinity].forEach(function (mValue) {
            load([[mValue, 'A', 'red', 60, 'key']], [{Label: 'Label'}]);
            check('Load preserves exact type/value ' + mValue, oTable.getData(false, true)[0][0], mValue);
            check('updateCell return identity ' + mValue, oProgress.updateCell(cell(0), mValue, true), mValue);
            check('setValue return identity ' + mValue, oProgress.setValue(cell(0), mValue), mValue);
            check('createCell returns same cell ' + mValue, oProgress.createCell(cell(0)), cell(0));
            oModule.refresh(oWidget);
            check('Lifecycle preserves exact stored value ' + mValue, oTable.options.data[0][0], mValue);
            var sWidth = mValue === 200 || mValue === '9007199254740993' ? '100%' :
                (mValue === '60,25' || mValue === '60.25' ? '50.25%' : '0%');
            check('Visual width ' + mValue, fill(0).style.width, sWidth);
        });
        load([[60, '<b>raw</b>', 'red', 70, 'key']], [{Label: 'Rendered label'}]);
        [false, true].forEach(function (bCompatibility) {
            oTable.options.copyCompatibility = bCompatibility;
            var sExport = oTable.copy(false, '\t', true);
            check('Export retains raw value, not threshold label', sExport.includes('60\t<b>raw</b>\tred\t70\t'), true);
            check('Export does not contain renderer HTML', sExport.includes('exf-jexcel'), false);
            check('Unrelated compatibility dropdown preserved', sExport.includes(bCompatibility ? 'Dropdown label' : 'key'), true);
            check('Export restores real DOM cells', cell(0).firstChild.classList.contains('exf-jexcel-progress'), true);
        });
        load([[null, 'A', '', null, 'key']], [{}]);
        check('Null export does not throw or produce markup', oTable.copy(false, '\t', true).includes('exf-jexcel'), false);
        check('Copy does not normalize stored null', oTable.options.data[0][0], null);
        oTable.options.copyCompatibility = false;
        load([[60, 'A', 'green', 50, 'key']], [{Label: 'Reloaded'}]);
        check('Reload replaces auxiliary context', cell(3).textContent, 'Reloaded');
        oTable.setValueFromCoords(0, 0, '75.125', true);
        check('Programmatic update preserves numeric string', oTable.options.data[0][0], '75.125');
        check('Programmatic update refreshes progress', fill(0).style.width, '65.125%');
        oTable.undo();
        check('Programmatic update undo preserves number', oTable.options.data[0][0], 60);
        oTable.redo();
        check('Programmatic update redo preserves string', oTable.options.data[0][0], '75.125');
        oTable.openEditor(cell(0), true);
        check('Read-only editor does not open', oTable.edition, null);
        return iChecks;
    } finally {
        jspreadsheet.destroy(domRoot);
        domRoot.remove();
    }
}
