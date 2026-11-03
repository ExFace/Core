/* Browser test: load jQuery, jSuites, jspreadsheet and this file, then await
 * testDataSpreadSheetDropdown(traitSource). No application data or writes used.
 */
async function testDataSpreadSheetDropdown(source, delimiter) {
    if (delimiter === undefined) {
        var total = 0;
        for (var separator of [';', ',', '|', '::']) {
            total += await testDataSpreadSheetDropdown(source, separator);
        }
        return total;
    }
    var checks = 0;
    function check(label, condition) {
        checks++;
        if (!condition) throw new Error(label + ' (delimiter ' + delimiter + ')');
    }
    var start = source.indexOf('            // Track whether the gesture started in an open dropdown.');
    if (start < 0) start = source.indexOf("            jqSelf.off('click', '.jexcel_dropdown')");
    var end = source.indexOf('            // single-click logic for filter row dropdown cells', start);
    if (start < 0 || end < 0) throw new Error('Dropdown opener not found');
    var root = document.createElement('div');
    var outside = document.createElement('button');
    var sheet = document.createElement('div');
    outside.textContent = 'Outside';
    root.appendChild(outside);
    root.appendChild(sheet);
    document.body.appendChild(root);
    var locks = 0;
    var helperStart = source.indexOf('        getSubsheet: function(');
    var helperEnd = source.indexOf('        convertArrayToData: function(', helperStart);
    var widget = new Function('return ({' + source.slice(helperStart, helperEnd) + '});')();
    var binding = {delimiter: delimiter, multiple: true};
    widget.lockDropdownColumnWidth = function() { locks++; };
    widget.getColumnModel = function(index) { return Number(index) === 0 ? {subsheet: binding} : {}; };
    sheet.exfWidget = widget;
    var options = [{id: 8, name: 'First'}, {id: 9, name: 'Second'}, {id: 10, name: 'Third'}];
    var table = jspreadsheet(sheet, {
        data: [['', '']],
        columns: [
            {type: 'autocomplete', multiple: true, width: 120, source: options},
            {type: 'autocomplete', multiple: true, width: 120, source: options}
        ]
    });
    widget.getJExcel = function() { return table; };
    widget.initSubsheetEditors();
    widget.initSubsheetEditors(); // Initialization must be idempotent.
    table.setData([['8', '8;9']]);
    new Function('$', 'jqSelf', 'instance', source.slice(start, end))($, $(sheet), sheet);
    function click(target, offsetX) {
        target.dispatchEvent(new PointerEvent('pointerdown', {bubbles: true, pointerType: 'mouse'}));
        ['mousedown', 'mouseup', 'click'].forEach(function(type) {
            var event = new MouseEvent(type, {bubbles: true, cancelable: true});
            if (offsetX !== undefined) Object.defineProperty(event, 'offsetX', {value: offsetX});
            target.dispatchEvent(event);
        });
    }
    function closeWithRetargetedClick(target, cell, pointerType) {
        // The native mouseup closes/removes the editor. Browsers may deliver
        // the ensuing click to the surviving cell rather than the removed input.
        var rect = target.getBoundingClientRect();
        var options = {bubbles: true, cancelable: true, clientX: rect.right - 2, clientY: rect.top + 2};
        target.dispatchEvent(new PointerEvent('pointerdown', Object.assign({pointerType: pointerType}, options)));
        target.dispatchEvent(new MouseEvent('mousedown', options));
        target.dispatchEvent(new MouseEvent('mouseup', options));
        check('Native close finishes before click', table.edition === null);
        var event = new MouseEvent('click', options);
        Object.defineProperty(event, 'offsetX', {value: cell.offsetWidth});
        cell.dispatchEvent(event);
    }
    function tick() { return new Promise(function(resolve) { setTimeout(resolve, 10); }); }
    try {
        var cell = table.getCellFromCoords(0, 0);
        click(cell, cell.offsetWidth);
        await tick();
        check('Arrow click opens editor', !!table.edition);
        var dropdown = cell.children[0].dropdown;
        click(dropdown.items[1].element.querySelector('.jdropdown-description'), cell.offsetWidth);
        check('Second option must not recreate editor', cell.children[0].dropdown === dropdown);
        check('Second selection retained', dropdown.getValue() === '8;9');
        click(dropdown.items[2].element.querySelector('.jdropdown-description'), cell.offsetWidth);
        check('Third option must not recreate editor', cell.children[0].dropdown === dropdown);
        check('Third selection retained', dropdown.getValue() === '8;9;10');
        click(dropdown.header, cell.offsetWidth);
        check('Input click must not recreate editor', cell.children[0].dropdown === dropdown);
        check('Only initial arrow click locks column', locks === 1);
        await tick();
        click(outside);
        check('Outside click closes editor', table.edition === null);
        check('Outside click commits all keys', table.getValueFromCoords(0, 0) === ['8', '9', '10'].join(delimiter));
        check('Cell displays all labels', cell.textContent === ['First', 'Second', 'Third'].join(delimiter));
        check('Ordinary dropdown labels unchanged', table.getDropDownValue(1, '8;9') === 'First; Second');
        click(cell, cell.offsetWidth);
        await tick();
        dropdown = cell.children[0].dropdown;
        check('Reopen selects all stored keys', dropdown.getValue() === '8;9;10');
        check('Opening does not change stored delimiter', table.getValueFromCoords(0, 0) === ['8', '9', '10'].join(delimiter));
        click(dropdown.items[1].element.querySelector('.jdropdown-description'), cell.offsetWidth);
        click(outside);
        check('Deselecting commits remaining keys', table.getValueFromCoords(0, 0) === ['8', '10'].join(delimiter));
        check('Deselecting leaves editor usable', table.edition === null);
        table.undo();
        check('Undo retains attribute delimiter', table.getValueFromCoords(0, 0) === ['8', '9', '10'].join(delimiter));
        table.redo();
        check('Redo retains attribute delimiter', table.getValueFromCoords(0, 0) === ['8', '10'].join(delimiter));
        click(cell, cell.offsetWidth);
        await tick();
        dropdown = cell.children[0].dropdown;
        click(dropdown.items[1].element.querySelector('.jdropdown-description'), cell.offsetWidth);
        table.closeEditor(cell, false);
        check('Cancel preserves previous keys', table.getValueFromCoords(0, 0) === ['8', '10'].join(delimiter));
        check('Cancel restores previous labels', cell.textContent === ['First', 'Third'].join(delimiter));
        click(cell, cell.offsetWidth);
        await tick();
        dropdown = cell.children[0].dropdown;
        click(dropdown.items[0].element.querySelector('.jdropdown-description'), cell.offsetWidth);
        click(dropdown.items[2].element.querySelector('.jdropdown-description'), cell.offsetWidth);
        click(outside);
        check('Clearing commits empty value', table.getValueFromCoords(0, 0) === '');
        check('Clearing removes all labels', cell.textContent === '');
        table.setValueFromCoords(0, 0, ['8', '9'].join(delimiter));
        click(cell, cell.offsetWidth);
        await tick();
        dropdown = cell.children[0].dropdown;
        dropdown.header.closest('.jdropdown').classList.add('jdropdown-default');
        closeWithRetargetedClick(dropdown.header, cell, 'mouse');
        check('Native arrow closing click must not reopen editor', table.edition === null);
        check('Native arrow preserves selection', table.getValueFromCoords(0, 0) === ['8', '9'].join(delimiter));
        click(cell, cell.offsetWidth);
        await tick();
        check('Next arrow gesture can reopen editor', !!table.edition);
        dropdown = cell.children[0].dropdown;
        closeWithRetargetedClick(dropdown.header.parentNode.querySelector('.jdropdown-close'), cell, 'touch');
        check('Mobile Done closing click must not reopen editor', table.edition === null);
        check('Mobile Done preserves selection', table.getValueFromCoords(0, 0) === ['8', '9'].join(delimiter));
        click(cell, cell.offsetWidth);
        await tick();
        click(cell, cell.offsetWidth);
        check('Cell arrow toggles an active editor closed', table.edition === null);
        click(cell, cell.offsetWidth);
        await tick();
        check('Cell arrow can reopen after toggling closed', !!table.edition);
        click(outside);
        return checks;
    } finally {
        if (table.edition) table.closeEditor(table.edition[0], false);
        jspreadsheet.destroy(sheet);
        root.remove();
    }
}


