<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

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
        return labels[key] !== undefined ? labels[key] : key;
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
