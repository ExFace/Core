(function (oWindow) {
    'use strict';
    if (oWindow.MarkianAdapter) return;
    var oMermaidFactories = new WeakSet();

    oWindow.MarkianAdapter = {
        mount: function (domHost, oOptions, bViewer, sMermaidSource, bAllowImages) {
            if (!domHost) throw new Error('Markian mount is missing.');
            if (domHost._exfMarkian) domHost._exfMarkian.destroy();
            var sValue = oOptions.markdown || '';
            var bReady = false;
            var bDestroyed = false;
            var bPendingValue = false;
            var oInstance;
            var oResize;
            var oRemoval;
            var oHandle;
            var domMount = bViewer ? domHost : domHost.ownerDocument.createElement('div');
            var report = function (oError) {
                if (bDestroyed) return;
                domHost.setAttribute('data-markian-error', oError.message || String(oError));
                domHost.dispatchEvent(new CustomEvent('exf-markian-error', {detail: oError, bubbles: true}));
                oWindow.console.error(oError);
            };
            var oSettings = Object.assign({}, oOptions);
            if (!bViewer && bAllowImages === false) {
                oSettings.onImageInsert = function () { throw new Error('Image insertion is disabled for this widget.'); };
            }
            if (sMermaidSource) {
                if (bViewer) {
                    oSettings.mermaid = true;
                    oSettings.renderers = {mermaid: oWindow.MarkianViewerMermaid.createRenderer(domHost.ownerDocument, {source: sMermaidSource})};
                } else {
                    if (!oMermaidFactories.has(oWindow.Markian)) {
                        oWindow.Markian.registerExtension('mermaid', oWindow.MarkianMermaid.mermaidExtension);
                        oMermaidFactories.add(oWindow.Markian);
                    }
                    oSettings.extensions = {mermaid: {enabled: true, config: {source: sMermaidSource}}};
                }
            }
            if (bViewer) oSettings.onError = report;
            if (!bViewer) domHost.appendChild(domMount);
            try {
                oInstance = (bViewer ? oWindow.MarkianViewer : oWindow.Markian).create(domMount, oSettings);
            } catch (oError) {
                if (!bViewer) domMount.remove();
                throw oError;
            }
            if (!bViewer && bAllowImages === false) oInstance.getToolbar().remove('image');
            oHandle = {
                get: function () { return bReady ? oInstance.get() : sValue; },
                set: function (mValue) {
                    var sNext = String(mValue == null ? '' : mValue);
                    if (sNext === oHandle.get()) return;
                    if (!bReady) {
                        sValue = sNext;
                        bPendingValue = true;
                        return;
                    }
                    if (bViewer) {
                        oInstance.set(sNext).catch(report);
                    } else {
                        oInstance.set(sNext, {origin: 'host-load', resetHistory: true, pending: 'discard'});
                    }
                },
                setDisabled: function (bDisabled) {
                    oSettings.disabled = bDisabled;
                    if (bReady && !bViewer) oInstance.setDisabled(bDisabled);
                },
                resize: function () { if (bReady && !bViewer) oInstance.resize(); },
                focus: function () { if (bReady && !bViewer) oInstance.focus(); },
                destroy: function () {
                    if (bDestroyed) return;
                    bDestroyed = true;
                    if (oResize) oResize.disconnect();
                    if (oRemoval) oRemoval.disconnect();
                    oInstance.destroy();
                    if (!bViewer) domMount.remove();
                    if (domHost._exfMarkian === oHandle) delete domHost._exfMarkian;
                },
                instance: oInstance,
                ready: null
            };
            domHost._exfMarkian = oHandle;
            if (!bViewer) {
                oInstance.on('error', function (oEvent) { report(oEvent.error); });
                oInstance.on('change', function (oEvent) {
                    if (bDestroyed || !bReady || oEvent.origin === 'host-load') return;
                    domHost.dispatchEvent(new Event('change', {bubbles: true}));
                });
            }
            oRemoval = new MutationObserver(function () {
                if (!domHost.isConnected) oHandle.destroy();
            });
            oRemoval.observe(domHost.ownerDocument.documentElement, {childList: true, subtree: true});
            oHandle.ready = oInstance.ready.then(function () {
                if (bDestroyed) return;
                bReady = true;
                if (!bViewer) oInstance.setDisabled(oSettings.disabled === true);
                if (bPendingValue) oHandle.set(sValue);
                if (!bViewer && oWindow.ResizeObserver) {
                    oResize = new ResizeObserver(function () { oHandle.resize(); });
                    oResize.observe(domHost);
                }
                oHandle.resize();
            });
            oHandle.ready.catch(report);
            return oHandle;
        }
    };
})(window);