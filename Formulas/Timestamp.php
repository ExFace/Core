<?php namespace exface\Apps\exface\Core\Formulas;

class Timestamp extends \exface\Core\Model\Formula {
	
	function run($date){
		if (!$date) return;
		return strtotime($date);
	}
}
?>