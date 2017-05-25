<?php
namespace exface\Core\Formulas;

class Round extends \exface\Core\CommonLogic\Model\Formula
{

    function run($number, $digits = 0, $pad_digits = false)
    {
        if (is_numeric($number)) {
            $rounded_number = round($number, $digits);
            if ($pad_digits) {
                $rounded_number = number_format($rounded_number, $digits, '.', '');
            }
            return $rounded_number;
        } else {
            return $number;
        }
    }
}
?>