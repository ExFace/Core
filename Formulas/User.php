<?php
namespace exface\Core\Formulas;

/**
 * Replaces a set of characters with another.
 * E.g. SUBSTITUTE('asdf', 'df', 'as') = 'asas'
 *
 * @author Andrej Kabachnik
 *        
 */
class User extends \exface\Core\CommonLogic\Model\Formula
{

    function run($variable = null)
    {
        switch ($variable) {
            case "id":
                return $this->getWorkbench()->context()->getScopeUser()->getUserId();
            case "user_name":
            default:
                return $this->getWorkbench()->context()->getScopeUser()->getUserName();
            // TODO Add the possibility to fetch other user data like first and last name, etc.
        }
    }
}
?>