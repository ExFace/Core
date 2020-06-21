<?php
namespace exface\Core\Formulas;


/**
 * Creates a 7 characters long id depending on the time and based on 36 characters system.
 * 
 * E.g. '=LogId()' => 7B7KU9Q
 *
 * @author Ralf Mulansky
 *        
 */
class LogId extends \exface\Core\CommonLogic\Model\Formula
{

    function run()
    {
        return strtoupper(base_convert(round(microtime(true)*10),10,36));
    }
}
?>