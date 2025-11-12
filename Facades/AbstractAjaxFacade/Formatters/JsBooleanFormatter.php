<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * 
 * @method BooleanDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class JsBooleanFormatter extends AbstractJsDataTypeFormatter
{
    private $html_checked = null;
    
    private $html_unchecked = null;
    
    private $useIcons = true;
    
    protected function setDataType(DataTypeInterface $dataType)
    {
        if (! $dataType instanceof BooleanDataType) {
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
        $jsFalsy = implode(' || ', $this->getFalsyValues('val === '));
        $jsTruthy = implode(' || ', $this->getTruthyValues('val === '));
        
        $str =  <<<JS
function(val){
    if (val === undefined || val === null) {
        val = {$this->getJsEmptyText('val')};
    }
    
    if (typeof val === 'string' || val instanceof String) {
        val = val.toLowerCase();
    }
    
    if ({$jsFalsy}) return '{$this->getHtmlUnchecked()}';
    if ({$jsTruthy}) return '{$this->getHtmlChecked()}';
    return val;
}({$jsInput})
JS;
        return $str;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        $jsTruthy = implode(' || ', $this->getTruthyValues('mInput === '));
        
        return <<<JS
function(mInput){
    if (mInput === null || mInput === '' || mInput === undefined) {
        return mInput;
    }
    
    if (typeof mInput === 'string' || mInput instanceof String) {
        mInput = mInput.toLowerCase();
    }
    
    return {$jsTruthy} ? 1 : 0;}({$jsInput})
JS;
    }

    /**
     * Returns an array with all input values that this formatter will interpret as TRUE.
     * 
     * NOTE: This includes translated values. This is the place to add new values to the parser.
     * 
     * @param string $prefix
     * This prefix will be applied to akl output values.
     * @return array
     */
    protected function getTruthyValues(string $prefix = '') : array
    {
        $values = [
            '1',
            "'1'",
            'true',
            "'true'",
            "'{$this->getHtmlChecked()}'"
        ];

        $translations = [
            "WIDGET.SELECT_YES",
            "WIDGET.SELECT_Y",
            "WIDGET.SELECT_TRUE",
        ];

        return $this->getValuesForParsing($prefix, $values, $translations);
    }

    /**
     * Returns an array with all input values that this formatter will interpret as FALSE.
     *
     * NOTE: This includes translated values. This is the place to add new values to the parser.
     *
     * @param string $prefix
     * This prefix will be applied to akl output values.
     * @return array
     */
    protected function getFalsyValues(string $prefix = '') : array
    {
        $values = [
            "''", 
            '0',
            "'0'",
            'false',
            "'false'",
        ];
        
        $translations = [
            "WIDGET.SELECT_NO",
	        "WIDGET.SELECT_N",
	        "WIDGET.SELECT_FALSE",
        ];
            
        return $this->getValuesForParsing($prefix, $values, $translations);
    }

    /**
     * Returns an array with strings ready for parsing.
     * 
     * @param       $prefix
     * This prefix will be applied to akl output values.
     * @param array $values
     * All items in this array will have the `$prefix` prepended, before being added to the result.
     * @param array $translations
     * All items in this list will be looked up in the `exface.Core` translations. Any translation key
     * without a matching translation will be skipped.
     * @return array
     */
    protected function getValuesForParsing($prefix, array $values, array $translations) : array
    {
        foreach ($values as $index => $value) {
            $values[$index] = $prefix . $value;
        }
        
        $trs = $this->getWorkbench()->getCoreApp()->getTranslator();
        $result = [];
        $miss = '~\\\\FailedTranslation';
        
        foreach ($translations as $key) {
            $translated = $trs->translate(
                $key,
                null,
                null,
                null,
                $miss
            );
            
            if($translated !== $miss){
                $translated = strtolower($translated);
                $result[] = $prefix ."'{$translated}'";
            }
        }

        return array_merge($values, $result);
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
     * @return string
     */
    public function getHtmlChecked()
    {
        if (is_null($this->html_checked)) {
            if ($this->getUseIcons()) {
                return '<i class="fa fa-check" aria-hidden="true"></i>';
            } else {
                return '&#10004;';
            }
        }
        return $this->html_checked;
    }

    /**
     * @param string $html
     */
    public function setHtmlChecked($html)
    {
        $this->html_checked = $html;
        return $this;
    }

    /**
     * @return string
     */
    public function getHtmlUnchecked()
    {
        if (is_null($this->html_unchecked)) {
            return '';
        }
        return $this->html_unchecked;
    }

    /**
     * @param string $html_unchecked
     */
    public function setHtmlUnchecked($html)
    {
        $this->html_unchecked = $html;
        return $this;
    }
    /**
     * @return boolean
     */
    public function getUseIcons()
    {
        return $this->useIcons;
    }

    /**
     * @param boolean $true_or_false
     */
    public function setUseIcons(bool $true_or_false)
    {
        $this->useIcons = $true_or_false;
        return $this;
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
