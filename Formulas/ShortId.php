<?php
namespace exface\Core\Formulas;


use exface\Core\DataTypes\UUIDDataType;

/**
 * Creates a 7 characters long id depending on the time and based on 36 characters system.
 * 
 * E.g. `=ShortId()` => 7B7KU9Q
 *
 * @author Ralf Mulansky
 *        
 */
class ShortId extends \exface\Core\CommonLogic\Model\Formula
{

    function run()
    {
        return UUIDDataType::generateShortIdFromTime(1);
    }
}
?>