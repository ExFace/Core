<?php namespace exface\Apps\exface\Core\Formulas;

/**
 * Replaces a set of characters with another. E.g. SUBSTITUTE('asdf', 'df', 'as') = 'asas'
 * @author aka
 *
 */
class Substitute extends \exface\Core\Model\Formula {
	
	function run($text, $old_text, $new_text){
        return str_replace($old_text, $new_text, $text);
	}
}
?>