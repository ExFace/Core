/**
 * Run in Fixtures/JsonEditor.html to check tree state across dialog/library reloads.
 */
export async function runJsonEditorScrollRegression() {
    const fnCreate = window.exfJsonEditor;
    const sSource = await (await fetch('../../../Facades/AbstractAjaxFacade/js/exfJsonEditor.js')).text();
    const domHost = document.createElement('div');
    domHost.id = 'json-scroll-regression-' + Date.now();
    domHost.style.height = '250px';
    document.getElementById('fixtures').appendChild(domHost);
    const oValue = {rows: Array.from({length: 100}, (_, iIndex) => ({
        name: 'Row ' + iIndex, details: {value: iIndex}
    }))};
    let oAdapter;
    function check(bCondition, sMessage) {
        if (!bCondition) throw new Error(sMessage);
    }
    function wait() {
        return new Promise(fnResolve => setTimeout(fnResolve, 200));
    }
    function create(sKey) {
        oAdapter = exfJsonEditor.create(domHost, {scrollKey: sKey, translations: {}}, oValue);
        return domHost.querySelector('div.jsoneditor-tree');
    }
    function reloadLibrary() {
        const domScript = document.createElement('script');
        domScript.textContent = sSource;
        document.head.appendChild(domScript);
        domScript.remove();
    }
    try {
        let domScroller = create('record-a');
        await wait();
        check(domScroller.scrollHeight > domScroller.clientHeight + 400, 'Fixture must have real scrollable content');
        oAdapter.getEditor().node.findNodeByPath(['rows', 0]).dom.expand.click();
        oAdapter.getEditor().node.findNodeByPath(['rows', 1, 'details']).dom.expand.click();
        oAdapter.getEditor().node.findNodeByPath(['rows', 1]).dom.expand.click();
        domScroller.scrollTop = 400;
        domScroller.dispatchEvent(new Event('scroll'));
        const iSaved = domScroller.scrollTop;
        oAdapter.destroy();
        reloadLibrary();
        domScroller = create('record-a');
        await wait();
        check(domScroller.scrollTop === iSaved, 'Reopening the same editor after library reload must restore its scroll position');
        check(!oAdapter.getEditor().node.findNodeByPath(['rows', 0]).expanded, 'Collapsed array entries must survive reopening');
        check(!oAdapter.getEditor().node.findNodeByPath(['rows', 1]).expanded, 'Collapsed parent nodes must survive reopening');
        check(!oAdapter.getEditor().node.findNodeByPath(['rows', 1, 'details']).expanded, 'Hidden descendants must retain their collapsed state');
        check(oAdapter.getEditor().node.findNodeByPath(['rows', 0, 'details']).expanded, 'Descendants of collapsed nodes must open expanded unless remembered');
        oAdapter.getEditor().node.findNodeByPath(['rows', 1]).dom.expand.click();
        check(!oAdapter.getEditor().node.findNodeByPath(['rows', 1, 'details']).expanded, 'Opening a parent must not expand its collapsed descendants');
        domScroller.scrollTop = iSaved;
        domScroller.dispatchEvent(new Event('scroll'));
        oAdapter.getEditor().setMode('code');
        await wait();
        oAdapter.getEditor().setMode('tree');
        await wait();
        check(!oAdapter.getEditor().node.findNodeByPath(['rows', 0]).expanded, 'Switching through code mode must retain collapsed nodes');

        oAdapter.destroy();
        domScroller = create('record-b');
        await wait();
        check(domScroller.scrollTop === 0, 'Different records must not inherit another record\'s scroll position');
        check(oAdapter.getEditor().node.findNodeByPath(['rows', 0]).expanded, 'Different records must not inherit collapsed nodes');
        domScroller.scrollTop = 700;
        domScroller.dispatchEvent(new Event('scroll'));
        oAdapter.destroy();

        domScroller = create('record-a');
        await wait();
        check(domScroller.scrollTop === iSaved, 'Each record must retain its own scroll position');
        oAdapter.getEditor().node.findNodeByPath(['rows', 0]).dom.expand.click();
        oAdapter.setValue({rows: oValue.rows, added: true});
        check(oAdapter.getEditor().node.findNodeByPath(['rows', 0]).expanded, 'Reexpanded nodes must stay expanded when updating data');
        check(!oAdapter.getEditor().node.findNodeByPath(['rows', 1, 'details']).expanded, 'Updating data must retain collapsed paths that still exist');
        domScroller.scrollTop = 0;
        domScroller.dispatchEvent(new Event('scroll'));
        oAdapter.destroy();
        reloadLibrary();
        domScroller = create('record-a');
        await wait();
        check(domScroller.scrollTop === 0, 'An explicitly saved top position must be restored');
        domHost.querySelector('.jsoneditor-collapse-all').click();
        oAdapter.destroy();
        domScroller = create('record-a');
        await wait();
        check(!oAdapter.getEditor().node.expanded, 'Collapse all must survive reopening, including the root');
        domHost.querySelector('.jsoneditor-expand-all').click();
        oAdapter.destroy();
        create('record-a');
        await wait();
        check(oAdapter.getEditor().node.findNodeByPath(['rows', 1, 'details']).expanded, 'Expand all must clear remembered collapsed descendants');
        oAdapter.setValue({replacement: {value: true}});
        check(oAdapter.getEditor().node.findNodeByPath(['replacement']).expanded, 'Removed paths must not affect new nodes');
        return ['scroll and collapsed nodes survive recreation, mode changes and updates; records and expand/collapse all remain independent'];
    } finally {
        if (oAdapter) oAdapter.destroy();
        domHost.remove();
        window.exfJsonEditor = fnCreate;
    }
}
