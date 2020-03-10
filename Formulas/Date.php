<?php
namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateDataType;

class Date extends \exface\Core\CommonLogic\Model\Formula
{

    function run($date, $format = '')
    {
        if (! $date)
            return;
        return $this->formatDate($date, $format);
    }

    function formatDate($date, $format = '')
    {
        if ($format === 'Y-m-d') {
            $format = 'yyyy-MM-dd';
        } elseif ($format === 'Y-m-d H:i:s') {
            $format = 'yyyy-MM-dd HH:mm:ss';
        }
        
        try {
            $date = new \DateTime($date);
        } catch (\Exception $e) {
            return $date;
        }
        
        $dataType = $this->getDataType();
        if ($format) {
            $dataType->setFormat($format);
        } else {
            $dataType->setFormat(DateDataType::DATE_ICU_FORMAT_INTERNAL);
        }
        
        return $dataType->formatDate($date);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
    }
}