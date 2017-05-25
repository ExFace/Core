<?php
namespace exface\Core\Formulas;

/**
 * Subtracts all arguments from the first one.
 * There is no Excel analogon.
 * E.g.: SUBTRACT(ALIAS1, ALIAS2, ALIAS3...)
 *
 * @author Andrej Kabachnik
 *        
 */
class Subtract extends \exface\Core\CommonLogic\Model\Formula
{

    function run()
    {
        $return = func_get_arg(0);
        for ($i = 1; $i < func_num_args(); $i ++) {
            $return -= func_get_arg($i);
        }
        return $return;
    }
}
?>