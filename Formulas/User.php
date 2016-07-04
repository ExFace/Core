<?php namespace exface\Core\Formulas;

/**
 * Replaces a set of characters with another. E.g. SUBSTITUTE('asdf', 'df', 'as') = 'asas'
 * @author aka
 *
 */
class User extends \exface\Core\CommonLogic\Model\Formula {
	
	function run($variable = null){
		global $exface;
		switch ($variable) {
			case "id": return $exface->context()->get_scope_user()->get_user_id();
			case "user_name": 
			default: return $exface->context()->get_scope_user()->get_user_name();
			// TODO Add the possibility to fetch other user data like first and last name, etc.
		}
	}
}
?>