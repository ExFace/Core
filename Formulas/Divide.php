<?php namespace exface\Core\Formulas;

class Divide extends \exface\Core\Model\Formula {
	
	function run($numerator, $denominator, $alternate_result = 0){
		if (!$denominator) return $alternate_result;
		return ($numerator / $denominator);
	}
}
?>