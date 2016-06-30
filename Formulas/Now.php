<?php namespace exface\Apps\exface\Core\Formulas;

class Now extends \exface\Core\Model\Formula {
	
	function run($format=''){
		global $exface;
		if (!$format) $format = $exface->get_config_value('default_datetime_format');
		$date = new \DateTime();
		return $date->format($format);
	}
}
?>