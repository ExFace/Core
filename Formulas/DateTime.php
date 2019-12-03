<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Factories\DataTypeFactory;

class DateTime extends \exface\Core\CommonLogic\Model\Formula
{

    function run($date, $format = '')
    {
        if (! $date)
            return;
        
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
        
        
        $dataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
        if ($format) {
            $dataType->setFormat($format);
        }
        
        return $dataType->formatDate($date);
    }
}
?>