/* Browser test: call testDataSpreadSheetProgressPlacement() in either playground.
 * Uses a disposable real CE grid without application actions or writes.
 */
function testDataSpreadSheetProgressPlacement() {
    'use strict';
    var iChecks = 0;
    function check(sName, bResult) {
        iChecks++;
        if (!bResult) throw new Error(sName);
    }
    function normalizeColor(sColor) {
        var domExpected = document.createElement('span');
        domExpected.style.color = sColor;
        return domExpected.style.color;
    }
    var bUi5 = document.body.classList.contains('sapUiBody');
    var oModule = exfJExcelDisplayRenderers;
    var oWidget;
    var sLabel = 'A long <progress> label with "quotes" that must be truncated';
    var domRoot = document.createElement('div');
    document.body.appendChild(domRoot);
    var oTable = jspreadsheet(domRoot, {
        data: [[]], minSpareRows: 0,
        columns: [{
            title: 'Progress', width: 240, type: 'numeric', readOnly: true,
            editor: oModule.createProgressBar({
                color: 'black', colorScale: [], colorRange: true,
                semanticColors: {}, hintScale: [], hintRange: false, hint: '',
                min: 10, max: 110, text: sLabel, textScale: [],
                textColorPreference: 0.35
            }, function () { return oWidget; })
        }],
        onevent: function (sEvent, domElement, domCell, iColumn, iRow) {
            if (oWidget) oModule.onEvent(oWidget, sEvent, iRow);
        }
    });
    oWidget = {
        getJExcel: function () { return oTable; },
        getColumnIndex: function () { return 0; },
        getColumnModel: function () { return {}; }
    };
    oModule.install(oWidget);
    var aValues = [10, 59.99, 60, 60.01, 110, 0, 120, null, 'invalid'];
    function checkPlacement(iRow) {
        var mValue = aValues[iRow];
        var domProgress = oTable.records[iRow][0].firstChild;
        var domLabel = domProgress.querySelector('.exf-jexcel-progress-label');
        var domFill = domProgress.querySelector('.exf-jexcel-progress-fill');
        var fPercent = typeof mValue === 'number' ? Math.max(0, Math.min(100, mValue - 10)) : 0;
        check('Fill width is preserved: ' + mValue, domFill.style.width === fPercent + '%');
        check('Exactly one safe DOM label: ' + mValue,
            domProgress.textContent === sLabel && !domProgress.querySelector('progress'));
        check('Empty value stays hidden: ' + mValue, domProgress.hidden === (mValue === null));
        if (mValue === null) return;
        var oBox = domProgress.getBoundingClientRect();
        var oLabelBox = domLabel.getBoundingClientRect();
        var oLabelStyle = getComputedStyle(domLabel);
        var fTrackWidth = domProgress.clientWidth;
        var fTrackLeft = oBox.left + domProgress.clientLeft;
        var fLabelPercent = bUi5 ? (fPercent > 50 ? fPercent : 100 - fPercent) : 100;
        var fLeftPercent = bUi5 && fPercent <= 50 ? fPercent : 0;
        check('Label stays in the intended region: ' + mValue,
            Math.abs(oLabelBox.width - fTrackWidth * fLabelPercent / 100) < 1 &&
            Math.abs(oLabelBox.left - fTrackLeft - fTrackWidth * fLeftPercent / 100) < 1);
        var sExpectedColor = bUi5 && fPercent > 50 ? domFill.style.color : domProgress.style.color;
        check('Label contrast follows its background: ' + mValue,
            oLabelStyle.color === normalizeColor(sExpectedColor));
        if (bUi5) {
            check('Real label paints above the fill: ' + mValue,
                oLabelStyle.position === 'relative' &&
                Number(oLabelStyle.zIndex) > Number(getComputedStyle(domFill).zIndex));
        }
        check('Label retains padding and ellipsis: ' + mValue,
            parseFloat(oLabelStyle.paddingLeft) > 0 && parseFloat(oLabelStyle.paddingRight) > 0 &&
            oLabelStyle.textOverflow === 'ellipsis' && oLabelStyle.overflow === 'hidden');
        check('Fill copy visibility is facade-specific: ' + mValue,
            (getComputedStyle(domFill, '::after').display === 'none') === bUi5);
        if (typeof mValue === 'number') {
            check('Progress accessibility retains the label: ' + mValue,
                domProgress.getAttribute('aria-valuetext') === sLabel);
        }
    }
    try {
        oModule.setData(oWidget, aValues.map(function (mValue) { return [mValue]; }));
        [240, 80].forEach(function (iWidth) {
            oTable.setWidth(0, iWidth);
            aValues.forEach(function (mValue, iRow) { checkPlacement(iRow); });
        });
        oTable.setValueFromCoords(0, 2, 61, true);
        aValues[2] = 61;
        checkPlacement(2);
        oTable.setValueFromCoords(0, 2, 60, true);
        aValues[2] = 60;
        checkPlacement(2);
        var aStored = oTable.getData(false, true);
        check('Raw values and types remain unchanged', aValues.every(function (mValue, iRow) {
            return Object.is(aStored[iRow][0], mValue);
        }));
        return iChecks;
    } finally {
        domRoot.remove();
    }
}
