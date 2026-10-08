(function (global) {
    "use strict";

    /**
     * Adds and activates a view over an editable pivot's shared table.
     *
     * @param {HTMLElement} domElement The pivot widget root.
     */
    global.exfAddPerspectiveView = async function (domElement) {
        const domViewer = domElement?.matches("perspective-viewer") ? domElement : domElement?.querySelector("perspective-viewer");
        if (!domViewer?.exfPerspectiveTable || domViewer.exfPerspectiveAddingView) {
            return;
        }
        domViewer.exfPerspectiveAddingView = true;
        try {
            const sTableName = await domViewer.exfPerspectiveTable.get_name();
            const sPanelId = await domViewer.addPanel({table: sTableName});
            await domViewer.setActivePanel(sPanelId);
            await domViewer.toggleConfig(true);
        } catch (oError) {
            console.error("Cannot add Perspective view", oError);
        } finally {
            domViewer.exfPerspectiveAddingView = false;
        }
    };

    global.exfLoadPerspective = function (urls) {
        if (!global.exfPerspectiveReady) {
            const resolveUrl = function (url) {
                return new URL(url, document.baseURI).href;
            };
            global.exfPerspectiveReady = Promise.all([
                import(resolveUrl(urls.client)),
                import(resolveUrl(urls.viewer))
            ]).then(function (modules) {
                return Promise.all([
                    import(resolveUrl(urls.datagrid)),
                    import(resolveUrl(urls.charts)),
                    customElements.whenDefined("perspective-viewer")
                ]).then(function () {
                    global.exfPerspective = modules[0].default;
                    return global.exfPerspective;
                });
            });
        }

        return global.exfPerspectiveReady;
    };
})(window);