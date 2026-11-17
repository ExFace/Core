/**
 * Core-owned Pele rendering for Markdown widgets and documentation.
 * Requires the host-supplied pele global, not Markian or Codiware.
 */
(function (oGlobal) {
    'use strict';
    if (oGlobal.exfMermaid) {
        return;
    }
    var iSequence = 0;

    /**
     * Adds the same folded paper corners as the Markdown editor without moving labels.
     */
    function foldNotes(domSvg) {
        domSvg.querySelectorAll('.pele-note > rect, .pele-shape-note > rect').forEach(function (domRect) {
            var fX = Number(domRect.getAttribute('x') || 0);
            var fY = Number(domRect.getAttribute('y') || 0);
            var fWidth = Number(domRect.getAttribute('width'));
            var fHeight = Number(domRect.getAttribute('height'));
            if (![fX, fY, fWidth, fHeight].every(Number.isFinite) || fWidth <= 0 || fHeight <= 0) {
                throw new Error('Pele returned invalid note dimensions.');
            }
            var fRight = fX + fWidth;
            var fBottom = fY + fHeight;
            var fFold = Math.min(10, fWidth / 4, fHeight / 4);
            var domBody = domSvg.ownerDocument.createElementNS('http://www.w3.org/2000/svg', 'path');
            Array.from(domRect.attributes).forEach(function (oAttribute) {
                if (['x', 'y', 'width', 'height', 'rx', 'ry'].indexOf(oAttribute.name) === -1) {
                    domBody.setAttribute(oAttribute.name, oAttribute.value);
                }
            });
            domBody.setAttribute('d', 'M' + fX + ',' + fY + 'H' + (fRight - fFold) + 'L' + fRight + ',' + (fY + fFold) + 'V' + fBottom + 'H' + fX + 'Z');
            var domCorner = domSvg.ownerDocument.createElementNS('http://www.w3.org/2000/svg', 'path');
            domCorner.setAttribute('class', 'exf-diagram-note-fold');
            domCorner.setAttribute('d', 'M' + (fRight - fFold) + ',' + fY + 'V' + (fY + fFold) + 'H' + fRight + 'Z');
            domCorner.setAttribute('fill', 'var(--exf-diagram-note-fold)');
            domCorner.setAttribute('stroke', domRect.getAttribute('stroke') || 'var(--_b)');
            domRect.replaceWith(domBody, domCorner);
        });
    }

    /**
     * Renders one bounded Mermaid source as static SVG, preserving custom colors and IDs.
     */
    function render(sSource, domDocument) {
        if (!oGlobal.pele || typeof oGlobal.pele.render !== 'function') {
            throw new Error('Pele is not loaded. Check LIBS.PELE.JS.');
        }
        if (typeof sSource !== 'string' || !sSource.trim() || sSource.length > 20000 || sSource.split('\n').length > 500) {
            throw new Error('Diagram must contain text within 20000 characters and 500 lines.');
        }
        var sId = 'exf-pele-' + (++iSequence);
        var oResult = oGlobal.pele.render(sSource, {
            idPrefix: sId,
            limit: 20000,
            outputLimit: 2000000,
            maxEdges: 500,
            links: false,
            images: false,
            autoDirection: false
        });
        if (!oResult || typeof oResult.svg !== 'string' || oResult.svg.length > 2000000) {
            throw new Error('Pele returned invalid or oversized SVG.');
        }
        var domParsed = new DOMParser().parseFromString(oResult.svg, 'image/svg+xml');
        if (domParsed.querySelector('parsererror') || domParsed.documentElement.localName !== 'svg' || domParsed.querySelectorAll('*').length > 10000) {
            throw new Error('Pele returned invalid or oversized SVG.');
        }
        if (domParsed.querySelector('script, foreignObject, image, use, a, animate, set')) {
            throw new Error('Pele returned non-static SVG.');
        }
        var domSvg = domParsed.documentElement;
        Array.from(domSvg.querySelectorAll('*')).concat([domSvg]).forEach(function (domElement) {
            Array.from(domElement.attributes).forEach(function (oAttribute) {
                if (/^on/i.test(oAttribute.name) || (/^(?:href|xlink:href)$/i.test(oAttribute.name) && oAttribute.value.charAt(0) !== '#')) {
                    throw new Error('Pele returned active SVG attributes.');
                }
            });
        });
        foldNotes(domSvg);
        domSvg.setAttribute('id', sId);
        return (domDocument || oGlobal.document).importNode(domSvg, true);
    }

    /**
     * Enables the existing documentation zoom controls while allowing text selection on nodes.
     */
    function enablePanZoom(domSvg) {
        if (typeof oGlobal.svgPanZoom !== 'function') {
            throw new Error('SVG pan/zoom is not loaded.');
        }
        var oViewBox = domSvg.viewBox.baseVal;
        var fWidth = domSvg.parentElement.clientWidth || oViewBox.width;
        domSvg.style.width = '100%';
        domSvg.style.height = Math.max(120, Math.min(500, oViewBox.height * Math.min(1, fWidth / oViewBox.width))) + 'px';
        var oPanZoom;
        var oMouse = null;
        oPanZoom = oGlobal.svgPanZoom(domSvg, {
            zoomEnabled: true,
            controlIconsEnabled: true,
            fit: true,
            center: true,
            preventMouseEventsDefault: false,
            customEventsHandler: {
                haltEventListeners: ['mousedown', 'mousemove', 'mouseup'],
                mouseDownHandler: function (oEvent) {
                    if (oEvent.target === domSvg) {
                        oMouse = {x: oEvent.clientX, y: oEvent.clientY};
                    }
                },
                mouseMoveHandler: function (oEvent) {
                    if (oMouse) {
                        oPanZoom.panBy({x: oEvent.clientX - oMouse.x, y: oEvent.clientY - oMouse.y});
                        oMouse = {x: oEvent.clientX, y: oEvent.clientY};
                        oGlobal.getSelection().removeAllRanges();
                    }
                },
                mouseUpHandler: function () {
                    oMouse = null;
                },
                init: function () {
                    domSvg.addEventListener('mousedown', this.mouseDownHandler);
                    domSvg.addEventListener('mousemove', this.mouseMoveHandler);
                    domSvg.addEventListener('mouseup', this.mouseUpHandler);
                    domSvg.addEventListener('mouseleave', this.mouseUpHandler);
                },
                destroy: function () {
                    domSvg.removeEventListener('mousedown', this.mouseDownHandler);
                    domSvg.removeEventListener('mousemove', this.mouseMoveHandler);
                    domSvg.removeEventListener('mouseup', this.mouseUpHandler);
                    domSvg.removeEventListener('mouseleave', this.mouseUpHandler);
                }
            }
        });
    }

    /**
     * Replaces unrendered code fences inside one host. Failures retain source and show a diagnostic.
     */
    function renderAll(domRoot, oOptions) {
        domRoot = domRoot || oGlobal.document;
        oOptions = oOptions || {};
        var aRendered = [];
        domRoot.querySelectorAll('.language-mermaid, code[data-language="mermaid"]').forEach(function (domCode) {
            if (domCode.querySelector('svg.pele')) {
                return;
            }
            var domError = domCode.nextElementSibling;
            if (domError && domError.classList.contains('exf-diagram-error')) {
                domError.remove();
            }
            var sSource = domCode.textContent;
            try {
                var domSvg = render(sSource, domCode.ownerDocument);
                domCode.classList.add('exf-diagram');
                domCode.replaceChildren(domSvg);
                if (oOptions.panZoom) {
                    enablePanZoom(domSvg);
                }
                aRendered.push(domSvg);
            } catch (oError) {
                domCode.textContent = sSource;
                domCode.classList.remove('exf-diagram');
                domError = domCode.ownerDocument.createElement('span');
                domError.className = 'exf-diagram-error';
                domError.setAttribute('role', 'alert');
                domError.textContent = 'Diagram could not be rendered: ' + oError.message;
                domCode.after(domError);
                oGlobal.console.error('Pele diagram rendering failed:', oError);
            }
        });
        return aRendered;
    }

    oGlobal.exfMermaid = {render: render, renderAll: renderAll};
})(window);
