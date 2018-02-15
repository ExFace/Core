<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\NumberDataType;

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
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        $dataType = $this->getDataType();
        
        if ($dataType->getBase() !== 10) {
            return $jsInput;
        }
        
        $precision_max = is_null($dataType->getPrecisionMax()) ? 'undefined' : $dataType->getPrecisionMax();
        $precision_min = is_null($dataType->getPrecisionMin()) ? 'undefined' : $dataType->getPrecisionMin();
        $locale = $this->getWorkbench()->context()->getScopeSession()->getSessionLocale();
        $locale = is_null($locale) ? 'undefined' : "'" . str_replace('_', '-', $locale) . "'";
        $use_grouping = $this->getThousandsSeparator() ? 'true' : 'false';
        
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
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        $separator_regex = preg_quote($this->getDecimalSeparator());
        return "{$jsInput}.toString().replace(/{$separator_regex}/g, '.').replace(/ /g, '')";
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildHtmlHeadIncludes()
     */
    public function buildHtmlHeadIncludes()
    {
        return [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildHtmlBodyIncludes()
     */
    public function buildHtmlBodyIncludes()
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



    
}
