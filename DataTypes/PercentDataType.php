<?php
namespace exface\Core\DataTypes;

/**
 * Data type for percentage values
 * 
 * @author Andrej Kabachnik
 *
 */
class PercentDataType extends NumberDataType
{  
    private $showPercentSign = false;
    
    public function getShowPercentSign() : bool
    {
        return $this->showPercentSign;
    }
    
    /**
     * Set to TRUE to append a percent sign to every value, unless another suffix is explicitly set.
     * 
     * @uxon-property show_percent_sign
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return PercentDataType
     */
    public function setShowPercentSign(bool $value) : PercentDataType
    {
        $this->showPercentSign = $value;
        return $this;
    }
    
    /**
     * 
     * @param mixed $string
     * @return number
     */
    public static function cast($string)
    {
        // Accept values with trailing percent sign - like `5%`
        if (is_string($string)) {
            $string = trim($string);
            if (StringDataType::endsWith($string, '%')) {
                $string = trim(substr($string, 0, -1));
            }
        }
        
        return parent::cast($string);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::getSuffix()
     */
    public function getSuffix() : ?string
    {
        if (parent::getSuffix() === null && $this->getShowPercentSign()) {
            return ' %';
        }
        return parent::getSuffix();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (null !== $val = $this->showPercentSign) {
            $uxon->setProperty('show_percent_sign', $val);
        }
        return $uxon;
    }
}