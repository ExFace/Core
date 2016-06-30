<?php namespace exface\Apps\exface\Core\Formulas;

/**
 * Concatenates all its arguments. Used to quickly concatenate values from different cells of a data sheet.
 * E.g.: concatenate(ALIAS1, ALIAS2, ALIAS3...)
 * @author aka
 *
 */
class Concatenate extends \exface\Core\Model\Formula {
	
	function run(){
		for($i=0;$i<func_num_args();$i++) {
        	$return .= func_get_arg($i);
        }
        return $return;
	}
}
?>