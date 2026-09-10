<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * 
 * @method EnumDataTypeInterface getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsEnumFormatter extends AbstractJsDataTypeFormatter
{
    protected function setDataType(DataTypeInterface $dataType)
    {
        if (! $dataType instanceof EnumDataTypeInterface) {
            // TODO
        }
        return parent::setDataType($dataType);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        $valueLabelsJs = json_encode($this->getDataType()->getLabels());
        return <<<JS
    function(key) {
        var labels = {$valueLabelsJs};
        return labels[key] !== undefined ? labels[key] : {$this->getJsEmptyText('key')};
    }({$jsInput})
JS;
    }
    
    /**
     * Finds the enum value by its label - optionally performing a case-insensitive search
     * 
     * By default only exact matches are returned. However, if `$searchForPartialMatches` is `true`
     * even substrings of labels will be transformed into values. In this case, if a substring matches
     * multiple labels (and ultimately multiple values), a delimited list of potential values is returned
     * 
     * @param string $jsInput
     * @param bool $searchForPartialMatches
     * @param string $searchResultsDelimiter
     * @return string
     * 
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput, bool $searchForPartialMatches = false, string $searchResultsDelimiter = EXF_LIST_SEPARATOR)
    {
        $labelValuesJs = json_encode(array_flip($this->getDataType()->getLabels()));
        if ($searchForPartialMatches === false) {
            return <<<JS
    function(sLabel) {
        var oLabelsToKeys = {$labelValuesJs};
        return oLabelsToKeys[sLabel] !== undefined ? oLabelsToKeys[sLabel] : {$jsInput};
    }({$jsInput})
JS;
        } else {
            $sDelimJs = json_encode($searchResultsDelimiter);
            return <<<JS
    function(sLabel) {
        var oLabelsToKeys = {$labelValuesJs};
        var aMatches = [];
        if (oLabelsToKeys[sLabel] !== undefined) {
            return oLabelsToKeys[sLabel];
        } else {
            for (var i in oLabelsToKeys) {
                if (exfTools.data.compareValues(i, sLabel, '=', {$sDelimJs})) {
                    aMatches.push(oLabelsToKeys[i]);
                }
            }
            return (aMatches.length > 0 ? aMatches.join({$sDelimJs}) : sLabel);
        }
        return sLabel;
    }({$jsInput})
JS;
        }
    }

    /**
     * Resolves keys and labels (case-insensitively) before applying the comparator, so a column
     * header filter can be used just like on a regular text column.
     * 
     * - `<`, `<=`, `>`, `>=` and the `BETWEEN` endpoints are matched exactly against the enum key or
     *   label (case-insensitive) and translated to the matching key, since these comparators only
     *   make sense when applied to the key (e.g. `> Draft` on a status enum with keys `10`, `20`, etc.)
     * - `=` performs a case-insensitive search in the enum labels (like it would for a text column):
     *   an exact key/label match resolves to that single value, while multiple label matches turn the
     *   condition into an `IN` with all matching keys.
     * - every other comparator falls back to the default parser.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFilterParser()
     */
    public function buildJsFilterParser(string $jsValue, string $jsComparator) : string
    {
        $labels = $this->getDataType()->getLabels();
        $keysLowerToKey = [];
        foreach (array_keys($labels) as $key) {
            $keysLowerToKey[mb_strtolower((string) $key)] = $key;
        }
        $labelsLowerToKey = [];
        foreach ($labels as $key => $label) {
            $labelsLowerToKey[mb_strtolower((string) $label)] = $key;
        }
        $keysLowerJs = json_encode($keysLowerToKey);
        $labelsLowerJs = json_encode($labelsLowerToKey);
        $delimJs = json_encode(EXF_LIST_SEPARATOR);
        $between = ComparatorDataType::BETWEEN;
        $is = ComparatorDataType::IS;
        $in = ComparatorDataType::IN;
        $defaultParserJs = parent::buildJsFilterParser('mFilterValue', 'sComparator');
        return <<<JS
(function(mFilterValue, sComparator) {
    var oKeysLower = {$keysLowerJs};
    var oLabelsLower = {$labelsLowerJs};
    // Resolves a value to its enum key via an exact, case-insensitive match against keys and
    // labels. Returns the original value unchanged if nothing matches.
    var fnResolveExact = function(mVal) {
        if (mVal === undefined || mVal === null || mVal === '') {
            return mVal;
        }
        var sLower = String(mVal).toLowerCase();
        if (oKeysLower[sLower] !== undefined) {
            return oKeysLower[sLower];
        }
        if (oLabelsLower[sLower] !== undefined) {
            return oLabelsLower[sLower];
        }
        return mVal;
    };

    if (sComparator === '{$between}') {
        var iSeparator = String(mFilterValue).indexOf('{$between}');
        var mValueFrom = iSeparator === -1 ? mFilterValue : String(mFilterValue).slice(0, iSeparator);
        var mValueTo = iSeparator === -1 ? '' : String(mFilterValue).slice(iSeparator + 2);
        return {
            comparator: sComparator,
            value: (mValueFrom === '' ? '' : fnResolveExact(mValueFrom)) + '{$between}' + (mValueTo === '' ? '' : fnResolveExact(mValueTo))
        };
    }

    if (sComparator === '<' || sComparator === '<=' || sComparator === '>' || sComparator === '>=') {
        return {
            comparator: sComparator,
            value: fnResolveExact(mFilterValue)
        };
    }

    if (sComparator === '{$is}') {
        var sLower = String(mFilterValue).toLowerCase();
        var aMatches = [];
        if (oKeysLower[sLower] !== undefined) {
            aMatches.push(oKeysLower[sLower]);
        } else if (oLabelsLower[sLower] !== undefined) {
            aMatches.push(oLabelsLower[sLower]);
        } else {
            for (var sLabelLower in oLabelsLower) {
                if (sLabelLower.indexOf(sLower) !== -1) {
                    aMatches.push(oLabelsLower[sLabelLower]);
                }
            }
        }
        if (aMatches.length === 1) {
            return {
                comparator: sComparator,
                value: aMatches[0]
            };
        }
        if (aMatches.length > 1) {
            return {
                comparator: '{$in}',
                value: aMatches.join({$delimJs})
            };
        }
        return {
            comparator: sComparator,
            value: mFilterValue
        };
    }

    return {$defaultParserJs};
})({$jsValue}, {$jsComparator})
JS;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlHeadIncludes()
     */
    public function buildHtmlHeadIncludes(FacadeInterface $facade) : array
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes(FacadeInterface $facade) : array
    {
        return [];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsValidator()
     */
    public function buildJsValidator(string $jsValue) : string
    {
        return 'true';
    }
}
