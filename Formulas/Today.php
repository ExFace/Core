<?php namespace exface\Core\Formulas;

class Today extends \exface\Core\CommonLogic\Model\Formula {
	
	function run(){
		$exface = $this->get_workbench();
		if (!$format) $format = $exface->get_config()->get_option('DEFAULT_DATE_FORMAT');
		$date = new \DateTime($date);
		return $date->format($format);
	}
}
?>