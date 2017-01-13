<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\ConfigurationInterface;

class ConfigurationFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param AppInterface $app
	 * @return ConfigurationInterface
	 */
	public static function create_from_app(AppInterface $app){
		$workbench = $app->get_workbench();
		return static::create($workbench);
	}
	
	/**
	 * 
	 * @param Workbench $workbench
	 * @return ConfigurationInterface
	 */
	public static function create(Workbench $workbench){
		return new \exface\Core\CommonLogic\Configuration($workbench);
	}
	
}
?>