<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

abstract class AbstractJsDataTypeFormatter implements JsDataTypeFormatterInterface, WorkbenchDependantInterface
{
    /**
     * 
     * @var DataTypeInterface
     */
    private $dataType = null;
    
    /**
     * 
     * @param DataTypeInterface $dataType
     */
    public function __construct(DataTypeInterface $dataType)
    {
        $this->setDataType($dataType);
    }
    
    /**
     * Sets the data type for this formatter. 
     * 
     * Override this method to include additional checks for specific compatible data types.
     * 
     * @param DataTypeInterface $dataType
     * @return \exface\Core\Facades\AbstractAjaxFacade\Formatters\AbstractJsDataTypeFormatter
     */
    protected function setDataType(DataTypeInterface $dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::getDataType()
     */
    public function getDataType()
    {
        return $this->dataType;
    }
    
    public function getWorkbench()
    {
        return $this->getDataType()->getWorkbench();
    }

    /**
     * @inerhitDoc 
     * @see JsDataTypeFormatterInterface::getJsEmptyText()
     */
    public function getJsEmptyText(string $jsFallback = '', bool $encode = true) : ?string
    {
        $result = $this->getDataType()->getEmptyText(); 
        
        if($result === null) {
            return $jsFallback;
        }
        
        return $encode ? json_encode($result) : $result;
    }

    /**
     * Returns an inline snippet that checks whether a variable with
     * name `$jsVar` is empty.
     * 
     * ```
     * ({$jsVar} === null || {$jsVar} === undefined || {$jsVar} === '')
     * ```
     * 
     * @param string $jsVar
     * @return string
     */
    protected function getJsEmptyCheck(string $jsVar) : string 
    {
        return "({$jsVar} === null || {$jsVar} === undefined || {$jsVar} === '')";
    }

    /**
     * Builds a JavaScript expression that returns an object with:
     *
     * - `comparator`: normalized ComparatorDataType value
     * - `value`: normalized scalar or serialized range value
    *
     * The filter parser takes care of filter-specific normalization of values (on top of those of the regular parser):
    *
     * - Filter values can contain comparators (e.g. `> 5`, `<= 10`, `!= 3`) which are extracted and normalized into
     * the `comparator` property.
     * - Filter values can contain ranges (e.g. `5..10`, `25.07.2026..30.07.2026`) which remain strings in the value
     * property, but get a BETWEEN comparator and both values parsed according to the underlying data type.
     * - Filter values can contain lists (e.g. `1,2,3`, `2026-07-25,2026-07-30`) which remain strings too, but get an
     * IN comparator and all values parsed according to the underlying data type.
     * - Filter values can be the logical `NULL` constant (EXF_LOGICAL_NULL). In contrast to the `null` JS value, this
     * means the filter is not empty, and we will need to filter for `NULL`s in the DB. So the `NULL` constant is 
     * actually a valid filter value.
    *
     * Centralizing all this value logic is important. This is why `$jsValue` can take either a scalar raw value or
     * the JS object produced by `exfTools.data.filterComparator.extract()`. When overriding this method, you can
     * use the extractor once and pass the result back to the parent method - see `JsDateFormatter` and `JsEnumFormatter`
     * for examples.
    *
     * @see JsDataTypeFormatterInterface::buildJsFilterParser()
     */
    public function buildJsFilterParser(string $jsValue, string $jsComparator) : string
    {
        $parserJs = $this->buildJsFormatParser('mFilterValue');
        $rightListComparators = array_values(array_filter(
            ComparatorDataType::getValuesStatic(),
            function(string $comparator) : bool {
                return ComparatorDataType::isListComparator($comparator, 'right');
            }
        ));
        $rightListComparatorsJs = json_encode($rightListComparators);
        // Preserve right-hand lists as a whole. Their individual values are parsed by the filter consumer.
        // EACH and ANY comparators are intentionally excluded because their right-hand value is scalar.
        // An empty comparator means the server will determine it from the meta model, so the value may still
        // be a list or contain an inline operator/range - parsing it here as a scalar would corrupt it.
        return <<<JS
(function(mFilterValue, sComparator) {
    var oExtracted = exfTools.data.filterComparator.extract(mFilterValue);
    if (oExtracted.comparator !== null) {
        sComparator = oExtracted.comparator;
    }
    if (oExtracted.isEmpty) {
        return {
            comparator: sComparator,
            value: oExtracted.value
        };
    }
    // Logical NULL is an active filter value. Preserve it verbatim instead of datatype-parsing it.
    if (oExtracted.isNullConstant) {
        return {
            comparator: sComparator,
            value: oExtracted.value
        };
    }
    var aRightListComparators = {$rightListComparatorsJs};
    var fnParse = function(mFilterValue) {
        return {$parserJs};
    };
    var bPreserve = sComparator === null || sComparator === '' || aRightListComparators.indexOf(sComparator) !== -1;
    return {
        comparator: sComparator,
        value: bPreserve ? oExtracted.value : fnParse(oExtracted.value)
    };
})({$jsValue}, {$jsComparator})
JS;
    }

    /**
     * @inheritDoc
     */
    public function buildJsGetValidatorIssues(string $jsValue): string
    {
        $dataType = $this->getDataType();
        if(null !== $message = $dataType->getValidationErrorMessage()) {
            $msg = StringDataType::endSentence($message->getTitle());
        } else {
            $msg = '';
        }
        $msgJs = json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return <<<JS

(function (value){
    if ({$this->buildJsValidator('value')} === false) {
        return {$msgJs};
    } else {
        return '';
    }
})({$jsValue})
JS;
    }
}