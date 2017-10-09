<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;

class NumberValue extends \exface\Core\CommonLogic\Model\Formula
{

    function run($string)
    {
        try {
            $number = NumberDataType::cast($string);
        } catch (\exface\Core\Exceptions\DataTypeValidationError $e) {
            return '';
        }
        return $number;
    }
}
?>