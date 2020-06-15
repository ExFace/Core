<?php
namespace exface\Core\Formulas;


/**
 * Executes the php script and returns its result..
 * 
 * E.g. =Func('time();').
 *
 * @author Ralf Mulansky
 *        
 */
class Func extends \exface\Core\CommonLogic\Model\Formula
{

    function run($func = null)
    {
        ob_start();
        eval("echo " . $func);
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
}
?>