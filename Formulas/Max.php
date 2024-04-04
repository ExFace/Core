<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Returns the maximum of passed numbers
 *
 * @author Andrej Kabachnik
 *        
 */
class Max extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run()
    {
        $return = null;
        for ($i = 0; $i < func_num_args(); $i ++) {
            $num = NumberDataType::cast(func_get_arg($i));
            if ($return === null || $return < $num) {
                $return = $num;
            }
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