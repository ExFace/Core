<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\DataTypes\ListDataType;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * JS formatter/parser for delimited lists produced by the ListDataType
 * 
 * @method \exface\Core\DataTypes\ListDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsListFormatter extends AbstractJsDataTypeFormatter
{
    private $valueFormatter = null;
    
    /**
     * 
     * @param DataTypeInterface $dataType
     */
    public function __construct(DataTypeInterface $dataType, JsDataTypeFormatterInterface $valueFormatter)
    {
        parent::__construct($dataType);
        $this->valueFormatter = $valueFormatter;
    }

    protected function setDataType(DataTypeInterface $dataType)
    {
        if (! $dataType instanceof ListDataType) {
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
        $listType = $this->getDataType();
        $delim = $listType->getListDelimiter();
        $delimJs = json_encode($delim);
        $delimFormattedJs = json_encode($listType::formatDelimiter($delim));
        return <<<JS
    function(sList, sDelim, sDelimFormatted) {
        var aFormatted = [];
        if ((typeof sList) !== 'string' || sList.trim() === '') {
            return sList;
        }
        sList.split($delimJs).forEach(function(sVal){
            sVal = sVal.trim();
            aFormatted.push({$this->valueFormatter->buildJsFormatter('sVal')})
        });
        return aFormatted.join(sDelimFormatted);
    }({$jsInput}, {$delimJs}, {$delimFormattedJs})
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
    public function buildJsFormatParser($jsInput)
    {
        $listType = $this->getDataType();
        $delim = $listType->getListDelimiter();
        $delimJs = json_encode($delim);
        return <<<JS
    function(sList, sDelim) {console.log('list formatter', sList);
        var aRaw = [];
        if ((typeof sList) !== 'string' || sList.trim() === '') {
            return sList;
        }
        sList.split($delimJs).forEach(function(sVal){
            sVal = sVal.trim();
            aRaw.push({$this->valueFormatter->buildJsFormatParser('sVal')})
        });
        return aRaw.join(sDelim);
    }({$jsInput}, {$delimJs})
JS;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlHeadIncludes()
     */
    public function buildHtmlHeadIncludes(FacadeInterface $facade) : array
    {
        return $this->valueFormatter->buildHtmlHeadIncludes($facade);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes(FacadeInterface $facade) : array
    {
        return $this->valueFormatter->buildHtmlBodyIncludes($facade);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsValidator()
     */
    public function buildJsValidator(string $jsValue) : string
    {
        // TODO do we need a validator for lists??? They only happe when displaying values, not when editing, right?
        return 'true';
    }
}
