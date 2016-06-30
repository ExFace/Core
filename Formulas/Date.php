<?php namespace exface\Core\Formulas;

class Date extends \exface\Core\Model\Formula {
	
	function run($date, $format=''){
		if (!$date) return;
		return $this->format_date($date, $format);
	}
	
	function format_date($date, $format=''){
		global $exface;
		if (!$format) $format = $exface->get_config_value('default_date_format');
		try {
			$date = new \DateTime($date);
		} catch (\Exception $e){
			return $date;
		}
		return $date->format($format);
	}
}
?>