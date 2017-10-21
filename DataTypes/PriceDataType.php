<?php
namespace exface\Core\DataTypes;

class PriceDataType extends NumberDataType
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\NumberDataType::getPrecisionMin()
     */
    public function getPrecisionMin()
    {
        $precision = parent::getPrecisionMin();
        if (is_null($precision)) {
            return intval($this->getApp()->getTranslator()->translate('LOCALIZATION.PRICE.PRECISION_MIN'));
        }
        return $precision;
    }
    
    /**
     * 
     */
    public function getPrecisionMax()
    {
        $precision = parent::getPrecisionMax();
        if (is_null($precision)) {
            return intval($this->getApp()->getTranslator()->translate('LOCALIZATION.PRICE.PRECISION_MAX'));
        }
        return $precision;
    }
}
?>