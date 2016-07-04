<?php namespace exface\Core\Formulas;

/**
 * Replaces a set of characters with another. E.g. SUBSTITUTE('asdf', 'df', 'as') = 'asas'
 * @author aka
 *
 */
class Percentage extends \exface\Core\CommonLogic\Model\Formula {
	
	function run($value, $in_percent_of, $precision = 1){
		if (!$in_percent_of) return 0;
        return round(($value / $in_percent_of) * 100, $precision);
	}
}
?>