<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\DataTypes\PercentDataType;

/**
 * 
 * @method NumberDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsNumberFormatter extends AbstractJsDataTypeFormatter
{
    private $decimalSeparator = null;
    private $thousandsSeparator = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput, string $decimalSeparator = null, string $thousandSeparator = null)
    {
        $dataType = $this->getDataType();
        
        if ($dataType->getBase() !== 10) {
            return $jsInput;
        }
        
        $precision_max = $dataType->getPrecisionMax() === null ? 'undefined' : $dataType->getPrecisionMax();
        $precision_min = $dataType->getPrecisionMin() === null ? 'undefined' : $dataType->getPrecisionMin();
        $showPlusJs = $dataType->getShowPlusSign() ? 'true' : 'false';
        $locale = $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $locale = is_null($locale) ? 'undefined' : "'" . str_replace('_', '-', $locale) . "'";
        if ($dataType->getGroupDigits() && $this->getThousandsSeparator()) {
            $use_grouping =  'true';
            $setGroupSeparatorJs = <<<JS

            sTsdSep = (1000).toLocaleString({$locale}, {useGrouping: true}).charAt(1);
            sNum = sNum.replace(sTsdSep, '{$this->getThousandsSeparator()}');
JS;
        } else {
            $use_grouping =  'false';
            $setGroupSeparatorJs = '';
        }
        
        $prefix = $dataType->getPrefix();
        $prefixJs = $prefix === '' || $prefix === null ? '""' : json_encode($prefix . ' ');
        $suffix = $dataType->getSuffix();
        $suffixJs = $suffix === '' || $suffix === null ? '""' : json_encode(' ' . $suffix);
        $emptyFormatJs = json_encode($this->getDataType()->getEmptyFormat() ?? '');
        
        return <<<JS
        function(mNumber) {
            var fNum, sNum, sTsdSep;
            var bShowPlus = $showPlusJs;
            var sPrefix = $prefixJs;
            var sSuffix = $suffixJs;
            var sEmpty = $emptyFormatJs;
                    
            if ((mNumber === null || mNumber === undefined || mNumber === '') && sEmpty !== '') {
                return sEmpty;
            }
			fNum = {$this->buildJsFormatParser('mNumber')};
            if (isNaN(fNum) || fNum === null) {
                return mNumber;
            }
            sNum = fNum.toLocaleString(
                {$locale}, // use a string like 'en-US' to override browser locale
                {
                    minimumFractionDigits: {$precision_min},
                    maximumFractionDigits: {$precision_max},
                    useGrouping: {$use_grouping}
                }
            );
            {$setGroupSeparatorJs}

            sNum = (bShowPlus === true && fNum > 0 ? '+' : '') + sNum;
            if (sPrefix !== '') {
                sNum = sPrefix + sNum;
            }
            if (sSuffix !== '') {
                sNum = sNum + sSuffix;
            }

            return sNum;
        }({$jsInput})
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        if($this->getDataType()->getBase() != 10) {
            return $jsInput;
        }

        $decimalRegex = preg_quote($this->getDecimalSeparator());
        $thousandsRegex = preg_quote($this->getThousandsSeparator());
        
        $prefix = $this->getDataType()->getPrefix();
        $prefixJs = $prefix === '' || $prefix === null ? '""' : json_encode($prefix . ' ');
        $suffix = $this->getDataType()->getSuffix();
        $suffixJs = $suffix === '' || $suffix === null ? '""' : json_encode(' ' . $suffix);
        $emptyFormatJs = json_encode($this->getDataType()->getEmptyFormat() ?? '');
        
        return <<<JS
        function(mNumber) {
            var sPrefix = $prefixJs;
            var sSuffix = $suffixJs;
            if (mNumber === undefined || mNumber === null) return mNumber;
            if (typeof mNumber === 'number' && isFinite(mNumber)) {
                return mNumber;
            }
            if ((sPrefix !== '' || sSuffix !== '') && (typeof mNumber === 'string' || mNumber instanceof String)) {
                mNumber = mNumber.trim();
                if (sPrefix !== '' && mNumber.startsWith(sPrefix)) {
                    mNumber = mNumber.substring(sPrefix.length).trim();
                }
                if (sSuffix !== '' && mNumber.endsWith(sSuffix)) {
                    mNumber = mNumber.substring(sSuffix.length).trim();
                }
            }
            if (mNumber === '' || mNumber === $emptyFormatJs) return null;
            mNumber = mNumber.toString().replace(/{$thousandsRegex}/g, '').replace(/ /g, '').replace(/{$decimalRegex}/g, '.');

            // Return as number because otherwise comparisons between 100 and 100.00 will fail! The comparator logic cannot
            // know, whether the value was inteded to be a number, so it is important to parse a numeric string to a real
            // JS number!
            return mNumber === '' ? null : parseFloat(mNumber);
        }({$jsInput})
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
        // return ['<script type="text/javascript" src="exface/vendor/bower-asset/number-format.js/lib/format.min.js"></script>'];
        return [];
    }
    
    /**
     * @return string
     */
    public function getDecimalSeparator()
    {
        if ($this->decimalSeparator === null) {
            $this->decimalSeparator = $this->getDataType()->getDecimalSeparator();
        }
        if ($this->decimalSeparator === $this->thousandsSeparator) {
            throw new DataTypeConfigurationError($this->getDataType(), 'Cannot use the same separator "' . $this->decimalSeparator . '" for decimals an thousands in a number data type!');
        }
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
        if ($this->thousandsSeparator === null) {
            $this->thousandsSeparator = $this->getDataType()->getGroupSeparator();
        }
        if ($this->decimalSeparator === $this->thousandsSeparator) {
            throw new DataTypeConfigurationError($this->getDataType(), 'Cannot use the same separator "' . $this->decimalSeparator . '" for decimals an thousands in a number data type!');
        }
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
        
        $checksOk = [];
        if ($type->getMin() !== null) {
            $checksOk[] = "nVal >= {$type->getMin()}";
        }
        if ($type->getMax() !== null) {
            $checksOk[] = "nVal <= {$type->getMax()}";
        }
        $checksOkJs = ! empty($checksOk) ? implode(' && ', $checksOk) : 'true';
        
        $nullStr = '" . EXF_LOGICAL_NULL . "';
        return <<<JS
function(mVal) {console.log('validator');
                var bEmpty = (mVal === null || mVal === undefined || mVal.toString() === '' || mVal.toString() === $nullStr);
                var nVal = {$this->buildJsFormatParser('mVal')};
                return (bEmpty || ($checksOkJs));
            }($jsValue)
JS;
    }
}
