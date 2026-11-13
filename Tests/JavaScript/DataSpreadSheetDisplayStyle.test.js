/* Browser test: load the playground assets, then call testDataSpreadSheetDisplayStyle().
 * Uses a disposable real CE grid; never invokes application actions.
 */
function testDataSpreadSheetDisplayStyle() {
    'use strict';
    var iChecks = 0;
    function check(sName, bResult) {
        iChecks++;
        if (!bResult) throw new Error(sName);
    }
    function config(oOverrides) {
        return Object.assign({
            numeric: false, colorNumeric: false, color: null, colorColumn: null,
            colorScale: [], colorRange: false, semanticColors: {'~ERROR': 'black'},
            hintScale: [], hintRange: false, hint: '', fill: true, colorOnly: false,
            min: 0, max: 100, text: null, textColumn: null, textScale: [],
            textColorPreference: 0.5, format: function (mValue) { return mValue; }
        }, oOverrides);
    }
    var domRoot = document.createElement('div');
    domRoot.style.width = '320px';
    document.body.appendChild(domRoot);
    var oWidget;
    var oModule = exfJExcelDisplayRenderers;
    var oTable = jspreadsheet(domRoot, {
        data: [[]], minSpareRows: 0,
        columns: [
            {title: 'Badge', width: 120, type: 'text', readOnly: true,
                editor: oModule.createColorIndicator(config({colorColumn: 'Color'}), function () { return oWidget; })},
            {title: 'Progress', width: 120, type: 'numeric', readOnly: true,
                editor: oModule.createProgressBar(config({
                    colorScale: [[50, 'black'], [100, 'white']], colorRange: true,
                    colorNumeric: true, text: 'Progress label'
                }), function () { return oWidget; })},
            {title: 'Color', type: 'hidden'}
        ],
        onevent: function (sEvent, domElement, domCell, iColumn, iRow) {
            if (oWidget) oModule.onEvent(oWidget, sEvent, iRow);
        }
    });
    oWidget = {
        getJExcel: function () { return oTable; },
        getColumnIndex: function (sName) { return ['Badge', 'Progress', 'Color'].indexOf(sName); },
        getColumnModel: function () { return {}; }
    };
    oModule.install(oWidget);
    function badge(iRow) { return oTable.records[iRow][0].firstChild; }
    function progress(iRow) { return oTable.records[iRow][1].firstChild; }
    try {
        oModule.setData(oWidget, [
            ['Short', 0, 'black'],
            ['A label with several words that wraps', 50, 'white'],
            ['AnUnbrokenLabelThatMustNotOverflowTheColoredField', 100, 'transparent'],
            ['Alpha', 50, 'rgba(0, 0, 0, 0.1)'],
            ['Semantic', 50, '~ERROR'],
            ['Named', 50, 'steelblue']
        ]);
        check('Dark badge has white text', badge(0).style.color === 'rgb(255, 255, 255)');
        check('Light badge has black text', badge(1).style.color === 'rgb(0, 0, 0)');
        check('Semantic color resolves before contrast', badge(4).style.color === 'rgb(255, 255, 255)');
        [2, 3, 5].forEach(function (iRow) {
            var domExpected = document.createElement('span');
            domExpected.style.color = exfColorTools.pickTextColorForBackgroundColor(
                badge(iRow).style.backgroundColor, 0.5);
            check('Transparent/named color uses shared contrast: ' + iRow, badge(iRow).style.color === domExpected.style.color);
        });
        for (var i = 0; i < 6; i++) {
            var domBadge = badge(i);
            var oStyle = getComputedStyle(domBadge);
            var oCellStyle = getComputedStyle(domBadge.parentElement);
            var fAvailable = domBadge.parentElement.clientWidth - parseFloat(oCellStyle.paddingLeft)
                - parseFloat(oCellStyle.paddingRight);
            check('Badge fills available cell width: ' + i, Math.abs(domBadge.getBoundingClientRect().width - fAvailable) < 1);
            check('Badge has horizontal and vertical padding: ' + i,
                parseFloat(oStyle.paddingRight) >= 3 && parseFloat(oStyle.paddingTop) > 0);
            check('Badge text stays inside its box: ' + i, domBadge.scrollWidth <= domBadge.clientWidth + 1);
        }
        var domLongBadge = badge(2);
        var oRange = document.createRange();
        oRange.selectNodeContents(domLongBadge);
        var oBounds = domLongBadge.getBoundingClientRect();
        var oLongStyle = getComputedStyle(domLongBadge);
        check('Every wrapped line retains left and right padding', Array.from(oRange.getClientRects()).every(function (oRect) {
            return oRect.left >= oBounds.left + parseFloat(oLongStyle.paddingLeft) - 1
                && oRect.right <= oBounds.right - parseFloat(oLongStyle.paddingRight) + 1;
        }));
        var domProgress = progress(1);
        var domFill = domProgress.querySelector('.exf-jexcel-progress-fill');
        var domLabel = domProgress.querySelector('.exf-jexcel-progress-label');
        check('Partial progress fill has white text', domFill.style.color === 'rgb(255, 255, 255)');
        check('Progress has one DOM text label', domProgress.textContent === 'Progress label');
        check('Filled label is presentational', domFill.getAttribute('aria-hidden') === 'true');
        check('Filled label is safely CSS-generated', domFill.getAttribute('data-label') === 'Progress label');
        if (document.body.classList.contains('sapUiBody')) {
            check('UI5 hides the overlaid fill label', getComputedStyle(domFill, '::after').display === 'none');
            check('UI5 half-progress label uses the remaining track',
                Math.abs(domLabel.getBoundingClientRect().width - domFill.getBoundingClientRect().width) < 1);
        } else {
            check('Filled label uses the full track width',
                Math.abs(parseFloat(getComputedStyle(domFill, '::after').width) - domLabel.getBoundingClientRect().width) < 1);
        }
        check('Filled label is clipped', getComputedStyle(domFill).overflow === 'hidden');
        check('Zero fill stays zero', progress(0).firstChild.style.width === '0%');
        check('Full fill stays full', progress(2).firstChild.style.width === '100%');
        check('Light progress fill has black text', progress(2).firstChild.style.color === 'rgb(0, 0, 0)');
        oTable.setValueFromCoords(2, 0, 'white', true);
        check('Bound background change refreshes text contrast', badge(0).style.color === 'rgb(0, 0, 0)');
        check('Contrast leaves stored numbers unchanged', oTable.getData(false, true)[1][1] === 50);
        check('Contrast leaves stored labels unchanged', oTable.getData(false, true)[0][0] === 'Short');
        var oWhiteEditor = oModule.createColorIndicator(config({color: 'white', textColorPreference: 0}), function () {});
        var oBlackEditor = oModule.createColorIndicator(config({color: 'black', textColorPreference: 1}), function () {});
        var domCell = document.createElement('td');
        oWhiteEditor.updateCell(domCell, 'White preference');
        check('Configured white preference is honored', domCell.firstChild.style.color === 'rgb(255, 255, 255)');
        oBlackEditor.updateCell(domCell, 'Black preference');
        check('Configured black preference is honored', domCell.firstChild.style.color === 'rgb(0, 0, 0)');
        var oTextEditor = oModule.createColorIndicator(config({color: 'steelblue', fill: false}), function () {});
        oTextEditor.updateCell(domCell, 'Unfilled');
        check('Unfilled text keeps its configured color', domCell.firstChild.style.color === 'steelblue');
        var oRootStyle = document.documentElement.style;
        var sBackdrop = oRootStyle.getPropertyValue('--sapBackgroundColor');
        var sPriority = oRootStyle.getPropertyPriority('--sapBackgroundColor');
        try {
            oRootStyle.setProperty('--sapBackgroundColor', '#000', 'important');
            ['transparent', 'rgba(255, 255, 255, 0.1)'].forEach(function (sColor) {
                var oDarkEditor = oModule.createColorIndicator(config({color: sColor}), function () {});
                oDarkEditor.updateCell(domCell, 'Dark backdrop');
                check('Transparent background honors dark theme: ' + sColor,
                    domCell.firstChild.style.color === 'rgb(255, 255, 255)');
            });
        } finally {
            if (sBackdrop) {
                oRootStyle.setProperty('--sapBackgroundColor', sBackdrop, sPriority);
            } else {
                oRootStyle.removeProperty('--sapBackgroundColor');
            }
        }
        if (document.body.classList.contains('sapUiBody')) {
            check('UI5 badge matches ObjectStatus corners', getComputedStyle(badge(0)).borderRadius === '4px');
            check('UI5 progress has pill corners',
                parseFloat(getComputedStyle(domProgress).borderRadius) >= domProgress.getBoundingClientRect().height / 2);
        }
        return iChecks;
    } finally {
        domRoot.remove();
    }
}
