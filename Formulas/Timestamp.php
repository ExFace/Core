<?php
namespace exface\Core\Formulas;

class Timestamp extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     *
     * @param string $date            
     * @param number $multiplier            
     * @return number
     */
    function run($date, $multiplier = 1000)
    {
        if (! $date)
            return 0;
        return strtotime($date) * $multiplier;
    }
}
?>