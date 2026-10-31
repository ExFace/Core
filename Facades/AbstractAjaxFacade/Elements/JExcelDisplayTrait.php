<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsBooleanFormatter;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Widgets\ColorIndicator;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\ProgressBar;

/**
 * Lightweight display cells for all facades using JExcelTrait.
 *
 * Decorations never replace stored cell values. Auxiliary bindings are retained in
 * hidden columns, so sorting and row movement also move the associated display data.
 *
 * @method \exface\Core\Widgets\DataSpreadSheet getWidget()
 * @method \exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade getFacade()
 * @method string escapeString($value)
 */
trait JExcelDisplayTrait
{
    /**
     * Ensure auxiliary color/text bindings survive conversion to indexed grid rows.
     *
     * @return void
     */
    protected function prepareJExcelDisplayColumns() : void
    {
        $widget = $this->getWidget();
        foreach ($widget->getColumns() as $column) {
            $cell = $column->getCellWidget();
            $names = [];
            if ($cell instanceof ColorIndicator) {
                $names[] = $this->getJExcelDisplayColorColumn($column);
            } elseif ($cell instanceof ProgressBar && $cell->isTextBoundToAttribute()) {
                $names[] = $cell->getTextDataColumnName();
            }
            foreach ($names as $name) {
                if (! $name || $widget->getColumnByDataColumnName($name)) {
                    continue;
                }
                // The display widget already requests these values in prepareDataSheetToRead().
                // Do not interpret a custom data column name as an attribute expression.
                $widget->addColumn($widget->createColumnFromUxon(new UxonObject([
                    'data_column_name' => $name,
                    'caption' => $name,
                    'hidden' => true,
                    'editable' => false,
                    'cell_widget' => ['widget_type' => 'Display', 'disable_formatting' => true]
                ])));
            }
        }
    }

    /**
     * Build display metadata, or NULL for cells retaining standard spreadsheet rendering.
     *
     * @param DataColumn $column
     * @return string
     */
    protected function buildJsJExcelDisplayOptions(DataColumn $column) : string
    {
        $cell = $column->getCellWidget();
        if ($column->isHidden() || ! ($cell instanceof ColorIndicator || $cell instanceof ProgressBar)) {
            return 'null';
        }
        $options = [
            'type' => $cell instanceof ProgressBar ? 'progress' : 'color',
            'colorScale' => $cell->getColorScale(),
            'colorRange' => $cell->isColorScaleRangeBased(),
            'hintScale' => $cell->getHintScale()->getScaleValues(),
            'hintRange' => $cell->getHintScale()->isRangeBased(),
            'hint' => $cell->getHint() ?? '',
            'textColorPreference' => $this->getJExcelDisplayTextColorPreference()
        ];
        if ($cell instanceof ColorIndicator) {
            $binding = $cell->getColorBinding();
            $options['colorColumn'] = $this->getJExcelDisplayColorColumn($column);
            $options['color'] = null;
            if ($binding->hasValue() && ! $binding->isBoundToDataColumn()) {
                $expression = $binding->getValueExpression();
                // A literal CSS color can also look like an attribute name. Only
                // evaluate constants here; data-driven formulas use colorColumn.
                $options['color'] = $expression !== null && $expression->isStatic()
                    ? $expression->evaluate() : $binding->getValue();
            }
            $options['colorOnly'] = $cell->getColorOnly($this->getJExcelColorOnlyDefault());
            $options['fill'] = $cell->getFill();
        } else {
            $options['color'] = $cell->getColor();
            $options['min'] = $cell->getMin();
            $options['max'] = $cell->getMax();
            $options['textColumn'] = $cell->isTextBoundToAttribute() ? $cell->getTextDataColumnName() : null;
            $options['text'] = $cell->getText();
            $options['textScale'] = $cell->getTextScale();
        }
        $formatterJs = $cell->getDisableFormatting() ? 'mValue'
            : $this->buildJsJExcelDisplayTextFormatter($cell->getValueDataType());
        $textFormatterJs = $cell instanceof ProgressBar && $cell->isTextBoundToAttribute()
            ? $this->buildJsJExcelDisplayTextFormatter($cell->getTextAttribute()->getDataType())
            : 'mValue';
        return 'Object.assign(' . json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            . ', {format: function(mValue){return ' . $formatterJs . ';}, formatText: function(mValue){return '
            . $textFormatterJs . ';}, style: ' . $this->buildJsJExcelDisplayStyler($column) . '})';
    }

    /**
     * Self-bound scales use the callback's raw value, not a second grid lookup.
     * Attribute aliases need not equal data column names (e.g. related/custom columns).
     * Only genuinely separate color bindings require an auxiliary column.
     */
    protected function getJExcelDisplayColorColumn(DataColumn $column) : ?string
    {
        $cell = $column->getCellWidget();
        $binding = $cell->getColorBinding();
        $name = $binding->getDataColumnName();
        if ($name === $column->getDataColumnName()
            || ($binding->isBoundToAttribute() && $cell->isBoundToAttribute()
                && $binding->getAttributeAlias() === $cell->getAttributeAlias())) {
            return null;
        }
        return $name;
    }

    /**
     * Labels are assigned via textContent, so Boolean formatters must return
     * translated Yes/No text rather than facade-specific HTML checkmark icons.
     */
    protected function buildJsJExcelDisplayTextFormatter(DataTypeInterface $dataType) : string
    {
        $formatter = $this->getFacade()->getDataTypeFormatter($dataType);
        return $formatter instanceof JsBooleanFormatter
            ? $formatter->buildJsFormatter('mValue', false)
            : $formatter->buildJsFormatter('mValue');
    }

    /**
     * Optional facade-specific styling applied after the shared DOM renderer.
     *
     * @param DataColumn $column
     * @return string JavaScript function(domDisplay, oDisplay, sColor, domCell), or null
     */
    protected function buildJsJExcelDisplayStyler(DataColumn $column) : string
    {
        return 'null';
    }

    /**
     * Facades may override the default ColorIndicator presentation.
     *
     * @return bool
     */
    protected function getJExcelColorOnlyDefault() : bool
    {
        return false;
    }

    protected function getJExcelDisplayTextColorPreference() : float
    {
        return 0.5;
    }

    /**
     * Resolve semantic colors through the owning facade, not another facade's controls.
     *
     * @return string
     */
    protected function buildJsJExcelSemanticColors() : string
    {
        return json_encode(array_merge([
            '~OK' => 'green', '~WARNING' => 'orange', '~ERROR' => 'red', '~INFO' => 'steelblue'
        ], $this->getFacade()->getSemanticColors()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Build members of exfWidget. Use DOM properties for untrusted text and CSS colors.
     *
     * @return string
     */
    protected function buildJsJExcelDisplayMethods() : string
    {
        return <<<'JS'
        ({resolveDisplayScale: function(mValue, oScale, bRange) {
            var sFallback = oScale[''] || '';
            if (mValue === null || mValue === undefined || mValue === '') return sFallback;
            var aKeys = Object.keys(oScale).filter(function(sKey) { return sKey !== ''; });
            if (bRange) {
                var bNumericScale = aKeys.length > 0 && aKeys.every(function(sKey) { return isFinite(Number(sKey)); });
                if (bNumericScale && !isFinite(Number(mValue))) return sFallback;
                aKeys.sort(function(sA, sB) {
                    return isNaN(Number(sA)) || isNaN(Number(sB)) ? sA.localeCompare(sB) : Number(sA) - Number(sB);
                });
                for (var i = 0; i < aKeys.length; i++) {
                    var bNumeric = !isNaN(Number(mValue)) && !isNaN(Number(aKeys[i]));
                    if (bNumeric ? Number(mValue) <= Number(aKeys[i]) : mValue <= aKeys[i]) {
                        return oScale[aKeys[i]];
                    }
                }
                return aKeys.length ? oScale[aKeys[aKeys.length - 1]] : sFallback;
            }
            var sValue = String(mValue).toLowerCase();
            for (var j = 0; j < aKeys.length; j++) {
                if (sValue === aKeys[j].toLowerCase()) return oScale[aKeys[j]];
            }
            // Boolean JSON values also use numeric 0/1 scales. Explicit true/false keys win.
            if (sValue === 'true' && Object.prototype.hasOwnProperty.call(oScale, '1')) return oScale['1'];
            if (sValue === 'false' && Object.prototype.hasOwnProperty.call(oScale, '0')) return oScale['0'];
            return sFallback;
        },
        renderDisplayCell: function(domCell, iCol, iRow, mValue) {
            var oColumn = this.getColumnModel(iCol);
            var oDisplay = oColumn && oColumn.display;
            if (!oDisplay) return;
            var oWidget = this;
            var oExcel = this.getJExcel();
            var fnRead = function(sName) {
                var iIndex = oWidget.getColumnIndex(sName);
                if (iIndex === -1 || iIndex === undefined || !oExcel.options.data[iRow]) return null;
                var mData = oExcel.options.data[iRow][iIndex];
                var oModel = oWidget.getColumnModel(iIndex);
                return oModel.parser ? oModel.parser(mData) : mData;
            };
            var bEmpty = mValue === null || mValue === undefined || mValue === '';
            var mColorValue = oDisplay.colorColumn ? fnRead(oDisplay.colorColumn) : mValue;
            var sColor = oDisplay.color;
            if (oDisplay.type === 'color' && (oDisplay.colorColumn || (sColor !== null && sColor !== undefined))) {
                mColorValue = oDisplay.colorColumn ? mColorValue : sColor;
            }
            if (Object.keys(oDisplay.colorScale).length) {
                sColor = this._displayRenderer.resolveDisplayScale(mColorValue, oDisplay.colorScale, oDisplay.colorRange);
            } else if (oDisplay.type === 'color') {
                sColor = mColorValue;
            }
            sColor = this._displaySemanticColors[sColor] || sColor || 'transparent';
            var mText = oDisplay.format(mValue);
            var sHint = this._displayRenderer.resolveDisplayScale(mValue, oDisplay.hintScale, oDisplay.hintRange);
            sHint = sHint || oDisplay.hint || (mText === null || mText === undefined ? '' : String(mText));
            // Only replace our content. Validation/change/disabled classes and cell titles remain owned by the grid.
            domCell.textContent = '';
            if (bEmpty && oDisplay.type === 'progress') return;
            var domDisplay = document.createElement('div');
            domDisplay.className = 'exf-spreadsheet-display exf-spreadsheet-' + oDisplay.type;
            domDisplay.title = String(sHint);
            domDisplay.style.boxSizing = 'border-box';
            domDisplay.style.width = '100%';
            domDisplay.style.minHeight = '1em';
            if (oDisplay.type === 'color') {
                domDisplay.textContent = oDisplay.colorOnly ? '\u00a0' : (mText == null ? '' : String(mText));
                if (oDisplay.colorOnly) domDisplay.setAttribute('aria-label', String(sHint));
                domDisplay.style[oDisplay.fill || oDisplay.colorOnly ? 'backgroundColor' : 'color'] = sColor;
                if (!oDisplay.fill && !oDisplay.colorOnly) domDisplay.style.fontWeight = 'bold';
            } else {
                var fValue = Number(mValue);
                if (!isFinite(fValue)) return;
                var fRange = oDisplay.max - oDisplay.min;
                var fPercent = fRange > 0 ? Math.max(0, Math.min(100, (fValue - oDisplay.min) / fRange * 100)) : 0;
                if (oDisplay.textColumn) {
                    mText = oDisplay.formatText(fnRead(oDisplay.textColumn));
                } else if (oDisplay.text !== null && oDisplay.text !== undefined) {
                    mText = oDisplay.text;
                } else if (Object.keys(oDisplay.textScale).length) {
                    mText = this._displayRenderer.resolveDisplayScale(mValue, oDisplay.textScale, true);
                }
                domDisplay.style.position = 'relative';
                domDisplay.style.border = '1px solid currentColor';
                domDisplay.style.borderRadius = '0.25rem';
                domDisplay.style.overflow = 'hidden';
                // Keep the text gradient's width identical to the fill's width.
                domDisplay.style.paddingLeft = '0';
                domDisplay.style.paddingRight = '0';
                domDisplay.setAttribute('role', 'progressbar');
                domDisplay.setAttribute('aria-valuemin', oDisplay.min);
                domDisplay.setAttribute('aria-valuemax', oDisplay.max);
                domDisplay.setAttribute('aria-valuenow', Math.max(oDisplay.min, Math.min(oDisplay.max, fValue)));
                domDisplay.setAttribute('aria-valuetext', mText == null ? '' : String(mText));
                var domBar = document.createElement('div');
                domBar.className = 'exf-spreadsheet-progress-fill';
                domBar.style.position = 'absolute';
                domBar.style.top = '0';
                domBar.style.bottom = '0';
                domBar.style.left = '0';
                domBar.style.width = fPercent + '%';
                domBar.style.backgroundColor = sColor;
                domBar.style.borderRadius = 'inherit';
                domBar.setAttribute('aria-hidden', 'true');
                domDisplay.appendChild(domBar);
                var domText = document.createElement('span');
                domText.className = 'exf-spreadsheet-progress-text';
                domText.style.position = 'relative';
                domText.style.display = 'block';
                domText.style.boxSizing = 'border-box';
                domText.style.padding = '0 3px';
                domText.textContent = mText == null ? '' : String(mText);
                domDisplay.appendChild(domText);
            }
            if (typeof oDisplay.style === 'function') {
                oDisplay.style(domDisplay, oDisplay, sColor, domCell);
            }
            domCell.appendChild(domDisplay);
            if (oDisplay.type === 'progress' && sColor !== 'transparent') {
                // Keep currentColor inherited from the theme, including detached rows
                // and later theme changes. Only the glyph fill becomes transparent.
                var sInsideText = exfColorTools.pickTextColorForBackgroundColor(sColor, oDisplay.textColorPreference);
                domText.style.backgroundImage = 'linear-gradient(to right, ' + sInsideText + ' ' + fPercent + '%, currentColor ' + fPercent + '%)';
                domText.style.backgroundClip = 'text';
                domText.style.webkitBackgroundClip = 'text';
                domText.style.webkitTextFillColor = 'transparent';
            }
        }})
JS;
    }
}








