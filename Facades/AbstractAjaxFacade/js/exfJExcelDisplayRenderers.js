;(function (oGlobal, fnFactory) {
    if (typeof exports === 'object' && typeof module !== 'undefined') {
        module.exports = fnFactory();
    } else if (typeof define === 'function' && define.amd) {
        define(fnFactory);
    } else {
        oGlobal.exfJExcelDisplayRenderers = fnFactory();
    }
}(this, function () {
    'use strict';

    // CE 4.15 retains these arrays in row history as well as sorting/moving them.
    var oRowContexts = new WeakMap();

    function isEmpty(mValue) {
        return mValue === undefined || mValue === null || mValue === '';
    }

    function toText(mValue) {
        return isEmpty(mValue) ? '' : String(mValue);
    }

    function toNumber(mValue, oConfig) {
        if (isEmpty(mValue) || typeof mValue === 'boolean') {
            return NaN;
        }
        if (typeof mValue === 'number') {
            return Number.isFinite(mValue) ? mValue : NaN;
        }
        var sValue = String(mValue).trim();
        // Server numbers use a dot; localized input can use the configured separators.
        if (oConfig.decimalSeparator && oConfig.decimalSeparator !== '.' &&
            sValue.indexOf(oConfig.decimalSeparator) !== -1) {
            if (oConfig.groupSeparator) {
                sValue = sValue.split(oConfig.groupSeparator).join('');
            }
            sValue = sValue.split(oConfig.decimalSeparator).join('.');
        }
        sValue = sValue.replace(/\s/g, '');
        return sValue === '' ? NaN : Number(sValue);
    }

    function resolveScale(mValue, aScale, bRange, oConfig) {
        if (!aScale || !aScale.length) {
            return '';
        }
        if (isEmpty(mValue)) {
            var aEmpty = aScale.find(function (aEntry) { return aEntry[0] === ''; });
            return aEmpty ? aEmpty[1] : '';
        }
        var mComparable = mValue;
        if (bRange && oConfig.numeric) {
            mComparable = toNumber(mValue, oConfig);
            if (!Number.isFinite(mComparable)) {
                return '';
            }
        }
        for (var i = 0; i < aScale.length; i++) {
            if (bRange ? mComparable <= aScale[i][0] :
                String(mValue).toLowerCase() === String(aScale[i][0]).toLowerCase()) {
                return aScale[i][1];
            }
        }
        if (bRange) {
            return aScale[aScale.length - 1][1];
        }
        var aFallback = aScale.find(function (aEntry) { return aEntry[0] === ''; });
        return aFallback ? aFallback[1] : '';
    }

    function getRowValue(oWidget, iRow, sColumn) {
        var aRow = oWidget.getJExcel().options.data[iRow];
        if (!aRow) {
            return undefined;
        }
        var iColumn = oWidget.getColumnIndex(sColumn);
        if (iColumn !== -1) {
            // Raw stored value: numeric conversion is done by the caller via the *Numeric flags
            return aRow[iColumn];
        }
        var oContext = oRowContexts.get(aRow);
        return oContext ? oContext[sColumn] : undefined;
    }

    function createEditor(oConfig, fnWidget, bProgress) {
        if (bProgress && (!Number.isFinite(oConfig.min) || !Number.isFinite(oConfig.max) ||
            oConfig.max <= oConfig.min)) {
            throw new Error('Spreadsheet ProgressBar requires finite min < max.');
        }
        var oRendered = new WeakMap();

        function render(domCell, mValue) {
            var oWidget = fnWidget();
            var iRow = Number(domCell.getAttribute('data-y'));
            // Row-bound sources are unavailable until the widget exists: wait for the next refresh
            if (!oWidget && (oConfig.colorColumn || oConfig.textColumn)) {
                return;
            }
            var mColorValue = bProgress ? mValue : (oConfig.colorColumn && oWidget ?
                getRowValue(oWidget, iRow, oConfig.colorColumn) :
                (oConfig.color !== null && oConfig.color !== undefined ? oConfig.color : mValue));
            var sColor = oConfig.colorScale.length ?
                resolveScale(mColorValue, oConfig.colorScale, oConfig.colorRange,
                    Object.assign({}, oConfig, {
                        numeric: oConfig.colorNumeric,
                        decimalSeparator: oConfig.colorDecimalSeparator === undefined ?
                            oConfig.decimalSeparator : oConfig.colorDecimalSeparator,
                        groupSeparator: oConfig.colorGroupSeparator === undefined ?
                            oConfig.groupSeparator : oConfig.colorGroupSeparator
                    })) :
                toText(bProgress ? oConfig.color :
                    (oConfig.colorColumn || oConfig.color !== null ? mColorValue : ''));
            if (Object.prototype.hasOwnProperty.call(oConfig.semanticColors, sColor)) {
                sColor = oConfig.semanticColors[sColor];
            }
            var mDisplayValue = mValue;
            var bInvalidNumeric = false;
            if (oConfig.formatNumeric && !isEmpty(mValue)) {
                var fDisplayValue = toNumber(mValue, oConfig);
                if (Number.isFinite(fDisplayValue)) {
                    mDisplayValue = fDisplayValue;
                } else {
                    bInvalidNumeric = true;
                }
            }
            var sLabel = toText(oConfig.format && !bInvalidNumeric ? oConfig.format(mDisplayValue) : mDisplayValue);
            var sHint = resolveScale(mValue, oConfig.hintScale, oConfig.hintRange, oConfig) ||
                oConfig.hint || sLabel;
            var fValue = bProgress ? toNumber(mValue, oConfig) : NaN;
            var bValid = bProgress && Number.isFinite(fValue);
            var fWidth = bValid ? Math.max(0, Math.min(100,
                (fValue - oConfig.min) / (oConfig.max - oConfig.min) * 100)) : 0;
            if (bProgress) {
                if (oConfig.textColumn && oWidget) {
                    var mText = getRowValue(oWidget, iRow, oConfig.textColumn);
                    if (oConfig.textNumeric && !isEmpty(mText)) {
                        var fText = toNumber(mText, {
                            decimalSeparator: oConfig.textDecimalSeparator,
                            groupSeparator: oConfig.textGroupSeparator
                        });
                        if (Number.isFinite(fText)) {
                            mText = fText;
                        }
                    }
                    sLabel = toText(oConfig.formatText ? oConfig.formatText(mText) : mText);
                } else if (oConfig.text !== null && oConfig.text !== undefined) {
                    sLabel = toText(oConfig.text);
                } else if (oConfig.textScale.length && bValid) {
                    sLabel = toText(resolveScale(fValue, oConfig.textScale, true, {numeric: true}));
                }
                if (!bValid) {
                    sColor = '';
                }
            }
            var sSignature = JSON.stringify([sLabel, sHint, sColor, fWidth, bValid, isEmpty(mValue)]);
            var oPrevious = oRendered.get(domCell);
            if (oPrevious && oPrevious.signature === sSignature &&
                domCell.firstChild === oPrevious.node) {
                return;
            }

            var domDisplay = document.createElement('span');
            domDisplay.className = 'exf-jexcel-display ' + (bProgress ?
                'exf-jexcel-progress' : (oConfig.colorOnly ? 'exf-jexcel-swatch' :
                    (oConfig.fill ? 'exf-jexcel-badge' : 'exf-jexcel-colored-text')));
            domDisplay.title = toText(sHint);
            if (bProgress) {
                var sTrackColor = 'var(--sapProgress_Background, rgba(127, 127, 127, 0.08))';
                domDisplay.style.setProperty('--exf-jexcel-progress', fWidth + '%');
                domDisplay.classList.toggle('exf-jexcel-progress-filled-label', fWidth > 50);
                domDisplay.style.color = exfColorTools.pickTextColorForBackgroundColor(
                    sTrackColor, oConfig.textColorPreference);
                domDisplay.classList.toggle('exf-jexcel-progress-invalid', !bValid && !isEmpty(mValue));
                if (bValid) {
                    domDisplay.setAttribute('role', 'progressbar');
                    domDisplay.setAttribute('aria-valuemin', oConfig.min);
                    domDisplay.setAttribute('aria-valuemax', oConfig.max);
                    domDisplay.setAttribute('aria-valuenow', Math.max(oConfig.min, Math.min(oConfig.max, fValue)));
                    domDisplay.setAttribute('aria-valuetext', sLabel);
                }
                var domBar = document.createElement('span');
                domBar.className = 'exf-jexcel-progress-fill';
                domBar.style.width = fWidth + '%';
                domBar.style.backgroundColor = sColor;
                domBar.style.color = exfColorTools.pickTextColorForBackgroundColor(
                    sColor || 'transparent', oConfig.textColorPreference);
                domDisplay.style.setProperty('--exf-jexcel-fill-text-color', domBar.style.color);
                // A clipped, presentation-only copy keeps each part of the track readable.
                domBar.setAttribute('data-label', sLabel);
                domBar.setAttribute('aria-hidden', 'true');
                domBar.style.setProperty('--exf-jexcel-label-width', (fWidth > 0 ? 10000 / fWidth : 100) + '%');
                domDisplay.appendChild(domBar);
                var domLabel = document.createElement('span');
                domLabel.className = 'exf-jexcel-progress-label';
                domLabel.textContent = sLabel;
                domDisplay.appendChild(domLabel);
                domDisplay.hidden = isEmpty(mValue);
            } else {
                domDisplay.style[oConfig.fill || oConfig.colorOnly ? 'backgroundColor' : 'color'] = sColor;
                if (oConfig.fill && !oConfig.colorOnly) {
                    domDisplay.style.color = exfColorTools.pickTextColorForBackgroundColor(
                        sColor || 'transparent', oConfig.textColorPreference);
                }
                domDisplay.textContent = oConfig.colorOnly ? '' : sLabel;
                if (oConfig.colorOnly) {
                    domDisplay.setAttribute('role', 'img');
                    domDisplay.setAttribute('aria-label', toText(sHint || sLabel));
                    domDisplay.hidden = !sColor;
                }
            }
            domCell.replaceChildren(domDisplay);
            oRendered.set(domCell, {signature: sSignature, node: domDisplay});
        }

        return {
            _exfDisplay: true,
            createCell: function (domCell) {
                var oWidget = fnWidget();
                if (oWidget) {
                    var aRow = oWidget.getJExcel().options.data[Number(domCell.getAttribute('data-y'))];
                    render(domCell, aRow[Number(domCell.getAttribute('data-x'))]);
                }
                return domCell;
            },
            updateCell: function (domCell, mValue) {
                render(domCell, mValue);
                return mValue;
            },
            setValue: function (domCell, mValue) {
                render(domCell, mValue);
                return mValue;
            }
        };
    }

    function getDisplayColumns(oJExcel) {
        return oJExcel.options.columns.map(function (oColumn, iColumn) {
            return oColumn.editor && oColumn.editor._exfDisplay ? iColumn : -1;
        }).filter(function (iColumn) { return iColumn !== -1; });
    }

    function refresh(oWidget, iRow) {
        var oJExcel = oWidget.getJExcel();
        var aColumns = getDisplayColumns(oJExcel);
        if (!aColumns.length) {
            return;
        }
        oJExcel.options.data.forEach(function (aRow, iIndex) {
            if (iRow !== undefined && Number(iRow) !== iIndex) {
                return;
            }
            aColumns.forEach(function (iColumn) {
                var domCell = oJExcel.records[iIndex] && oJExcel.records[iIndex][iColumn];
                if (domCell) {
                    oJExcel.options.columns[iColumn].editor.setValue(domCell, aRow[iColumn]);
                }
            });
        });
    }

    return {
        createColorIndicator: function (oConfig, fnWidget) {
            return createEditor(oConfig, fnWidget, false);
        },
        createProgressBar: function (oConfig, fnWidget) {
            return createEditor(oConfig, fnWidget, true);
        },
        setData: function (oWidget, aRows, aSource) {
            var oJExcel = oWidget.getJExcel();
            var aColumns = getDisplayColumns(oJExcel);
            if (!aColumns.length) {
                oJExcel.setData(aRows);
                return;
            }
            if (Array.isArray(aSource)) {
                aRows.forEach(function (aRow, iRow) {
                    oRowContexts.set(aRow, Object.assign({}, aSource[iRow]));
                });
            }
            var aValues = aRows.map(function (aRow) {
                return aColumns.map(function (iColumn) { return aRow[iColumn]; });
            });
            oJExcel.setData(aRows);
            // CE normalizes null/undefined to ''. Restore only custom display columns.
            aRows.forEach(function (aRow, iRow) {
                aColumns.forEach(function (iColumn, iIndex) {
                    aRow[iColumn] = aValues[iRow][iIndex];
                });
            });
            refresh(oWidget);
        },
        refresh: refresh,
        onEvent: function (oWidget, sEvent, iRow) {
            if (sEvent === 'onchange') {
                refresh(oWidget, iRow);
            } else if (['onload', 'onundo', 'onredo', 'onmoverow', 'onsort',
                'oninsertrow', 'ondeleterow'].indexOf(sEvent) !== -1) {
                refresh(oWidget);
            }
        },
        install: function (oWidget) {
            var oJExcel = oWidget.getJExcel();
            var aColumns = getDisplayColumns(oJExcel);
            if (!aColumns.length || oJExcel._exfDisplayInstalled) {
                return;
            }
            oJExcel._exfDisplayInstalled = true;
            var bRestoringHistory = false;
            var fnUpdateCell = oJExcel.updateCell;
            oJExcel.updateCell = function (iColumn, iRow, mValue, bForce) {
                return fnUpdateCell.call(this, iColumn, iRow, mValue,
                    bForce || (bRestoringHistory && aColumns.indexOf(Number(iColumn)) !== -1));
            };
            ['undo', 'redo'].forEach(function (sMethod) {
                var fnHistory = oJExcel[sMethod];
                oJExcel[sMethod] = function () {
                    bRestoringHistory = true;
                    try {
                        return fnHistory.apply(this, arguments);
                    } finally {
                        bRestoringHistory = false;
                    }
                };
            });
            var fnCopy = oJExcel.copy;
            oJExcel.copy = function () {
                var aRestore = [];
                oJExcel.options.data.forEach(function (aRow, iRow) {
                    aColumns.forEach(function (iColumn) {
                        var mValue = aRow[iColumn];
                        var domCell = oJExcel.records[iRow][iColumn];
                        aRestore.push([aRow, iColumn, mValue, iRow, domCell]);
                        if (isEmpty(mValue)) {
                            aRow[iColumn] = '';
                        }
                        if (oJExcel.options.copyCompatibility) {
                            var domCopyCell = domCell.cloneNode(false);
                            // Copy reads HTML in compatibility mode. Override only display cells,
                            // keeping the library's existing dropdown/ordinary-cell behavior.
                            Object.defineProperty(domCopyCell, 'innerHTML', {get: function () { return toText(mValue); }});
                            Object.defineProperty(domCopyCell, 'textContent', {get: function () { return toText(mValue); }});
                            oJExcel.records[iRow][iColumn] = domCopyCell;
                        }
                    });
                });
                try {
                    return fnCopy.apply(this, arguments);
                } finally {
                    aRestore.forEach(function (aEntry) {
                        aEntry[0][aEntry[1]] = aEntry[2];
                        oJExcel.records[aEntry[3]][aEntry[1]] = aEntry[4];
                    });
                }
            };
        }
    };
}));
