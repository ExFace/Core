<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Formatters;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\BooleanDataType;

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
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        return "({$jsInput} ? '{$this->getHtmlChecked()}' : '{$this->getHtmlUnchecked()}')";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface::buildJsFormatParser()
     */
    public function buildJsFormatParser($jsInput)
    {
        return "({$jsInput} == '{$this->getHtmlChecked()}' ? 1 : 0)";
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


}
