<?php namespace exface\Apps\exface\Core\Formulas;

class DateTime extends \exface\Core\Model\Formula {
	
	function run($date, $format=''){
		global $exface;
		if (!$date) return;
		if (!$format) $format = $exface->get_config_value('default_datetime_format');
		try {
			$date = new \DateTime($date);
		} catch (\Exception $e){
			return $date;
		}
		return $date->format($format);
	}
}
?>