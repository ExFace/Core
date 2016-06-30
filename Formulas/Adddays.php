<?php namespace exface\Apps\exface\Core\Formulas;

use exface\Core\Model\Formula;

class Adddays extends Formula {
	
	function run($date, $days_to_add=null){
		global $exface;
		if (!$date) return;
		if (!$format) $format = $exface->get_config_value('default_date_format');
		$date = new \DateTime($date);
		$interval = ($day_to_add < 0 ? 'N' : 'P') . intval($days_to_add) . 'D';
		$date->add(new \DateInterval($interval));
		return $date->format($format);
	}
}
?>