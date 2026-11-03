/* Browser entry point: runJExcelDisplayTests(contentsOfJExcelDisplayTraitPhp). */
function runJExcelDisplayTests(sSource) {
    var aMatch = sSource.match(/return <<<'JS'\r?\n([\s\S]*?)\r?\nJS;/);
    if (!aMatch) throw new Error('Display renderer source not found');
    var oRenderer = new Function('return ' + aMatch[1])();
    var iChecks = 0;
    function check(sLabel, mActual, mExpected) {
        iChecks++;
        if (mActual !== mExpected) throw new Error(sLabel + ': expected ' + mExpected + ', got ' + mActual);
    }
    function color(sValue) {
        var dom = document.createElement('div');
        dom.style.backgroundColor = sValue;
        return dom.style.backgroundColor;
    }
    var oScale = {'100': 'green', '10': 'red', '50': 'yellow'};
    check('numeric threshold', oRenderer.resolveDisplayScale('9', oScale, true), 'red');
    check('numeric string comparison', oRenderer.resolveDisplayScale('100', oScale, true), 'green');
    check('upper threshold inclusive', oRenderer.resolveDisplayScale(50, oScale, true), 'yellow');
    check('above last threshold', oRenderer.resolveDisplayScale(200, oScale, true), 'green');
    check('zero is a value', oRenderer.resolveDisplayScale(0, oScale, true), 'red');
    [null, undefined, ''].forEach(function(mValue) {
        check('empty scale value', oRenderer.resolveDisplayScale(mValue, oScale, true), '');
    });
    check('case insensitive key', oRenderer.resolveDisplayScale('URGENT', {urgent: '~ERROR'}, false), '~ERROR');
    check('unknown key fallback', oRenderer.resolveDisplayScale('other', {'': 'gray'}, false), 'gray');
    check('date threshold', oRenderer.resolveDisplayScale('2026-10-22', {'2026-10-31': 'orange'}, true), 'orange');

    var oDisplay = {
        type: 'color', colorScale: {}, colorRange: true, hintScale: {}, hintRange: true,
        hint: '', color: '~OK', colorColumn: null, colorOnly: false, fill: true,
        format: function(mValue) { return mValue; }, formatText: function(mValue) { return mValue; }
    };
    var aData = [[25, 'red', '<b>Label</b>'], [75, 'blue', 'Second']];
    var oWidget = {
        _displayRenderer: oRenderer,
        _displaySemanticColors: {'~OK': 'green'},
        getColumnModel: function(iCol) { return iCol === 0 ? {display: oDisplay} : {parser: function(mValue) { return mValue; }}; },
        getColumnIndex: function(sName) { return {COLOR: 1, LABEL: 2}[sName]; },
        getJExcel: function() { return {options: {data: aData}}; }
    };
    var domCell = document.createElement('td');
    domCell.className = 'readonly exf-spreadsheet-change';
    domCell.title = 'Validation hint';
    function render(mValue, iRow) {
        oRenderer.renderDisplayCell.call(oWidget, domCell, 0, iRow || 0, mValue);
        return domCell.firstElementChild;
    }
    var domDisplay = render('<img src=x onerror=alert(1)>');
    check('safe text', domDisplay.textContent, '<img src=x onerror=alert(1)>');
    check('no injected image', domDisplay.querySelector('img'), null);
    check('semantic color', domDisplay.style.backgroundColor, color('green'));
    check('validation title preserved', domCell.title, 'Validation hint');
    check('change marker preserved', domCell.className, 'readonly exf-spreadsheet-change');
    oDisplay.color = null;
    oDisplay.colorColumn = 'COLOR';
    check('separate color binding', render(25).style.backgroundColor, color('red'));
    check('second row binding', render(75, 1).style.backgroundColor, color('blue'));
    aData.reverse();
    check('binding follows reordered row', render(75).style.backgroundColor, color('blue'));
    aData.reverse();
    aData[0][1] = 'orange';
    check('edited color dependency', render(25).style.backgroundColor, color('orange'));
    oDisplay.colorOnly = true;
    check('color only hides text', render(25).textContent, '\u00a0');
    oDisplay.colorOnly = false;
    oDisplay.fill = false;
    check('text coloring', render(25).style.color, color('orange'));
    aData[0][1] = null;
    check('cleared dependency resets color', render(25).style.color, color('transparent'));

    Object.assign(oDisplay, {
        type: 'progress', colorColumn: null, color: '~OK', min: 20, max: 80,
        text: null, textColumn: null, textScale: {}
    });
    domDisplay = render(50);
    check('normalized progress', domDisplay.firstElementChild.style.width, '50%');
    check('progress accessibility', domDisplay.getAttribute('role'), 'progressbar');
    check('underflow clamped', render(-5).firstElementChild.style.width, '0%');
    check('overflow clamped', render(90).firstElementChild.style.width, '100%');
    oDisplay.min = 0;
    oDisplay.max = 100;
    check('zero progress visible', render(0).textContent, '0');
    [null, undefined, '', 'invalid'].forEach(function(mValue) {
        render(mValue);
        check('empty or invalid progress clears decoration', domCell.children.length, 0);
    });
    oDisplay.max = 0;
    check('zero range has finite width', render(10).firstElementChild.style.width, '0%');
    oDisplay.max = 100;
    oDisplay.textColumn = 'LABEL';
    check('bound label remains safe text', render(25).textContent, '<b>Label</b>');
    check('bound label not interpreted', domCell.querySelector('b'), null);
    oDisplay.textColumn = null;
    oDisplay.text = '0';
    check('static zero label', render(25).textContent, '0');
    oDisplay.text = null;
    oDisplay.textScale = {'10': 'Pending', '100': 'Working'};
    check('scaled label', render(25).textContent, 'Working');
    check('stored numeric value unchanged', aData[0][0], 25);
    oDisplay.hintScale = {'100': 'Progress "hint"'};
    check('scaled hint', render(25).title, 'Progress "hint"');
    Object.assign(oDisplay, {
        colorScale: {'10': '~ERROR', '99': '~WARNING', '100': '~OK', '': 'lightgray'},
        colorRange: true, color: '#FFEF9C', textScale: {}, hintScale: {}
    });
    oWidget._displaySemanticColors = {'~ERROR': 'orangered', '~WARNING': 'yellow', '~OK': 'lightgreen'};
    [[0, 'orangered'], [10, 'orangered'], [11, 'yellow'], [50, 'yellow'], [99, 'yellow'], [100, 'lightgreen'], [101, 'lightgreen']].forEach(function(test) {
        check('progress scale uses numeric row value ' + test[0], render(test[0]).firstElementChild.style.backgroundColor, color(test[1]));
    });
    [null, undefined, ''].forEach(function(value) {
        check('empty key is fallback, not a zero threshold', oRenderer.resolveDisplayScale(value, oDisplay.colorScale, true), 'lightgray');
    });
    check('nonnumeric progress scale input uses fallback', oRenderer.resolveDisplayScale('unknown', oDisplay.colorScale, true), 'lightgray');
    Object.assign(oDisplay, {
        type: 'color', colorColumn: null, color: null, colorOnly: false, fill: true,
        colorScale: ['lightsteelblue', 'steelblue'], hintScale: {},
        format: function(mValue) { return Number(mValue) === 1 ? 'Ja' : 'Nein'; }
    });
    [0, 1, '0', '1', false, true].forEach(function(mValue) {
        aData[0][0] = mValue;
        var sLabel = Number(mValue) === 1 ? 'Ja' : 'Nein';
        domDisplay = render(mValue);
        check('localized Boolean label', domDisplay.textContent, sLabel);
        check('localized Boolean hint', domDisplay.title, sLabel);
        check('Boolean scale uses raw value', domDisplay.style.backgroundColor,
            color(Number(mValue) === 1 ? 'steelblue' : 'lightsteelblue'));
        check('Boolean label contains no markup', domDisplay.children.length, 0);
        check('stored Boolean scalar unchanged', aData[0][0], mValue);
    });
    check('true string uses numeric Boolean scale', oRenderer.resolveDisplayScale('true', oDisplay.colorScale, false), 'steelblue');
    check('false string uses numeric Boolean scale', oRenderer.resolveDisplayScale('false', oDisplay.colorScale, false), 'lightsteelblue');
    check('explicit Boolean key wins', oRenderer.resolveDisplayScale(true, {'1': 'blue', 'true': 'green'}, false), 'green');
    aData[0][0] = null;
    check('self scale uses callback value before row data is available', render(1).style.backgroundColor, color('steelblue'));
    return iChecks + ' display renderer checks passed';
}


