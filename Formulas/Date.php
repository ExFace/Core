<?php namespace exface\Core\Formulas;

class Date extends \exface\Core\CommonLogic\Model\Formula {
	
	function run($date, $format=''){
		if (!$date) return;
		return $this->format_date($date, $format);
	}
	
	function format_date($date, $format=''){
		if (!$format) $format = $this->get_workbench()->get_config_value('default_date_format');
		try {
			$date = new \DateTime($date);
		} catch (\Exception $e){
			return $date;
		}
		return $date->format($format);
	}
}
?>