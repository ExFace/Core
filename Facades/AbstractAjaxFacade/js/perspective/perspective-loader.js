(function (global) {
    "use strict";

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