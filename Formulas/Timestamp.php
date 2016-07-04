<?php namespace exface\Core\Formulas;

class Timestamp extends \exface\Core\CommonLogic\Model\Formula {
	
	function run($date){
		if (!$date) return;
		return strtotime($date);
	}
}
?>