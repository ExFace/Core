<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;

class Now extends \exface\Core\CommonLogic\Model\Formula
{

    function run($format = '')
    {
        if ($format === 'Y-m-d') {
            $format = 'yyyy-MM-dd';
        } elseif ($format === 'Y-m-d H:i:s') {
            $format = 'yyyy-MM-dd HH:mm:ss';
        }
        
        $date = new \DateTime();
        
        $dataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
        if ($format) {
            $dataType->setFormat($format);
        } else {
            $dataType->setFormat(DateTimeDataType::DATETIME_ICU_FORMAT_INTERNAL);
        }
        
        return $dataType->formatDate($date);
    }
}
?>