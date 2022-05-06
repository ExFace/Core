<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * 
 * @method NumberDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsNumberFormatter extends AbstractJsDataTypeFormatter
{
    private $decimalSeparator = '.';
    private $thousandsSeparator = '';
    
    protected function setDataType(DataTypeInterface $dataType)
    {
        if (! $dataType instanceof NumberDataType) {
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
        $dataType = $this->getDataType();
        
        if ($dataType->getBase() !== 10) {
            return $jsInput;
        }
        
        $precision_max = $dataType->getPrecisionMax() === null ? 'undefined' : $dataType->getPrecisionMax();
        $precision_min = $dataType->getPrecisionMin() === null ? 'undefined' : $dataType->getPrecisionMin();
        $locale = $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $locale = is_null($locale) ? 'undefined' : "'" . str_replace('_', '-', $locale) . "'";
        $use_grouping = $dataType->getGroupDigits() && $this->getThousandsSeparator() ? 'true' : 'false';
        
        return <<<JS
        function() {
            var input = {$jsInput};
            if (input !== null && input !== undefined && input !== ''){
    			var number = parseFloat({$this->buildJsFormatParser('input')});
                if (! isNaN(number)) {
                    return number.toLocaleString(
                        {$locale}, // use a string like 'en-US' to override browser locale
                        {
                            minimumFractionDigits: {$precision_min},
                            maximumFractionDigits: {$precision_max},
                            useGrouping: {$use_grouping}
                        }
                    );
                }
            }
            return input;
        }()
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        $separator_regex = preg_quote($this->getDecimalSeparator());
        return "{$jsInput}.toString().replace(/{$separator_regex}/g, '.').replace(/ /g, '')";
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
        // return ['<script type="text/javascript" src="exface/vendor/bower-asset/number-format.js/lib/format.min.js"></script>'];
        return [];
    }
    
    /**
     * @return string
     */
    public function getDecimalSeparator()
    {
        return $this->decimalSeparator;
    }

    /**
     * @param string $decimalSeparator
     * @return JsNumberFormatter
     */
    public function setDecimalSeparator($decimalSeparator)
    {
        $this->decimalSeparator = $decimalSeparator;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getThousandsSeparator()
    {
        return $this->thousandsSeparator;
    }

    /**
     * @param string $thousandsSeparator
     * @return JsNumberFormatter
     */
    public function setThousandsSeparator($thousandsSeparator)
    {
        $this->thousandsSeparator = $thousandsSeparator;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsValidator()
     */
    public function buildJsValidator(string $jsValue) : string
    {
        $type = $this->getDataType();
        if ($type->getBase() !== 10) {
            return 'true';
        }
        $checks = [];
        if ($type->getMin() !== null) {
            $checks[] = "parseFloat(mVal) >= {$type->getMin()}";
        }
        if ($type->getMax() !== null) {
            $checks[] = "parseFloat(mVal) <= {$type->getMax()}";
        }
        $checksJs = ! empty($checks) ? implode(' || ', $checks) : 'true';
        $nullStr = '" . EXF_LOGICAL_NULL . "';
        return <<<JS
function(mVal) {
                var bEmpty = mVal.toString() === '' || mVal.toString() === $nullStr;
                return (bEmpty || $checksJs);
            }($jsValue)
JS;
    }
}
