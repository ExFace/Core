<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Sums all its arguments.
 * Analogous to Excel's SUM() function.
 * E.g.: SUM(ALIAS1, ALIAS2, ALIAS3...)
 *
 * @author Andrej Kabachnik
 *        
 */
class Sum extends \exface\Core\CommonLogic\Model\Formula
{

    function run()
    {
        $return = 0;
        for ($i = 0; $i < func_num_args(); $i ++) {
            $return += func_get_arg($i);
        }
        return $return;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), NumberDataType::class);
    }
}
?>