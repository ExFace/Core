;(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
        typeof define === 'function' && define.amd ? define(factory()) :
            (global.exfDebugger = factory());
}(this, (function () {
    'use strict';

    // Assumes jQuery 3 is available globally as $
    var $ = window.jQuery || window.$;

    var exfDebugger = (function () {
        var _bHighlightingOn = false;
        var _sCssNS = '.exfwInfoMode'; // namespace for event bindings
        var _sCssHighlight = 'exf-dbg-highlight';
        var _sCssLabel = 'exf-dbg-label';
        var _sCssTagId = 'exfDebuggerHighlightStyles';
        var _oHighlightOptions = {
            shadow_color: 'rgba(0, 149, 255, 0.35)',
            // TODO add more options here
        };

        function ensureStyles() {
            if (document.getElementById(_sCssTagId)) return;

            var css =
                '/* Inner shadow highlight (no size change) */\n' +
                '.' + _sCssHighlight + ' {' +
                '  position: relative;' +
                '  box-shadow: inset 0 0 0 3px rgba(0, 149, 255, 0.8), inset 0 0 12px rgba(0, 149, 255, 0.35);' +
                '  transition: box-shadow 120ms ease-in-out;' +
                '}\n' +

                '/* Floating label in the upper-left corner */\n' +
                '.' + _sCssLabel + ' {' +
                '  position: absolute;' +
                '  top: 6px;' +
                '  left: 6px;' +
                '  z-index: 9999;' +
                '  background: rgba(0, 149, 255, 0.95);' +
                '  color: #fff;' +
                '  font: 600 12px/1.2 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;' +
                '  border-radius: 4px;' +
                '  padding: 6px 8px;' +
                '  display: inline-flex;' +
                '  align-items: center;' +
                '  gap: 6px;' +
                '  box-shadow: 0 1px 6px rgba(0,0,0,0.2);' +
                '  pointer-events: none;' + /* so it doesn’t break hover */
                '}\n' +

                '/* Make only the info button clickable */\n' +
                '.' + _sCssLabel + ' .exfw-info-btn {' +
                '  pointer-events: auto;' +
                '  cursor: pointer;' +
                '  display: inline-flex;' +
                '  align-items: center;' +
                '  justify-content: center;' +
                '  width: 18px;' +
                '  height: 18px;' +
                '  border-radius: 50%;' +
                '  background: rgba(255,255,255,0.2);' +
                '  transition: background 120ms ease-in-out;' +
                '}\n' +

                '.' + _sCssLabel + ' .exfw-info-btn:hover {' +
                '  background: rgba(255,255,255,0.35);' +
                '}\n' +

                '/* Ensure widgets are positioned for absolute label; harmless if already positioned */\n' +
                '.exfw {' +
                '  position: relative;' +
                '}';

            var styleEl = document.createElement('style');
            styleEl.id = _sCssTagId;
            styleEl.type = 'text/css';
            styleEl.appendChild(document.createTextNode(css));
            document.head.appendChild(styleEl);
        }

        // Extract widget type: first class that starts with "exfw-" and is not exactly "exfw"
        function getWidgetType($el) {
            var classes = ($el.attr('class') || '').split(/\s+/);
            for (var i = 0; i < classes.length; i++) {
                var c = classes[i];
                if (c.indexOf('exfw-') === 0 && c !== 'exfw') {
                    return c.replace(/^exfw-/, '') || 'Unknown';
                }
            }
            return 'Unknown';
        }

        function createLabel(typeText) {
            var $label = $('<div/>', {'class': _sCssLabel});
            var $type = $('<span/>', {text: typeText});

            // Font Awesome 4 icon
            var $info = $('<span/>', {'class': 'exfw-info-btn', title: 'More info'})
                .append($('<i/>', {'class': 'fa fa-info'}));

            $info.on('click' + _sCssNS, function (ev) {
                ev.stopPropagation();
                ev.preventDefault();
                alert('Widget type: ' + typeText + '\n(More info coming soon…)');
            });

            $label.append($type, $info);
            return $label;
        }

        function onHoverIn() {
            var $w = $(this);
            $w.addClass(_sCssHighlight);

            // Add label once
            if ($w.children('.' + _sCssLabel).length === 0) {
                var typeText = getWidgetType($w);
                var $label = createLabel(typeText);
                $w.append($label);
            }
        }

        function onHoverOut() {
            var $w = $(this);
            $w.removeClass(_sCssHighlight);
            $w.children('.' + _sCssLabel).remove();
        }

        function attachHandlers() {
            ensureStyles();

            // Attach hover handlers to all current widgets
            $('.exfw')
                .off('mouseenter' + _sCssNS + ' mouseleave' + _sCssNS) // avoid duplicates
                .on('mouseenter' + _sCssNS, onHoverIn)
                .on('mouseleave' + _sCssNS, onHoverOut);
        }

        function detachHandlersAndCleanup() {
            // Remove hover handlers from all widgets
            $('.exfw').off('mouseenter' + _sCssNS + ' mouseleave' + _sCssNS);

            // Remove any residual labels and highlighting
            $('.' + _sCssHighlight).removeClass(_sCssHighlight);
            $('.' + _sCssLabel).remove();
        }

        function startHighlighting(oOptions) {
            _oHighlightOptions = oOptions;
            if (_bHighlightingOn) return;
            _bHighlightingOn = true;
            attachHandlers();
        }

        function stopHighlighting() {
            if (!_bHighlightingOn) return;
            _bHighlightingOn = false;
            detachHandlersAndCleanup();
        }

        return {
            startHighlighting: startHighlighting,
            stopHighlighting: stopHighlighting,
            isHighlighting: function () {
                return _bHighlightingOn;
            }
        };
    })();

    return exfDebugger;

})));