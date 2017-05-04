<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\AppInstallerInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Interfaces\InstallerContainerInterface;

/**
 * 
 * @author Andrej Kabachnik
 * 
 */
class AppInstallerContainer implements AppInstallerInterface, InstallerContainerInterface {
	private $app = null;
	private $installers = array();
	
	public function __construct(AppInterface $app){
		$this->app = $app;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\AppInstallerInterface::get_app()
	 */
	public function get_app(){
		return $this->app;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->get_app()->get_workbench();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\InstallerInterface::install()
	 */
	public final function install($source_absolute_path){
		$result = '';
		// TODO Dispatch App.Install.Before
		foreach ($this->get_installers() as $installer){
			$result .= $installer->install($source_absolute_path);
		}
		// TODO Dispatch App.Install.After
		return $result;
	}
	
	public final function update($source_absolute_path){
		$result = '';
		// TODO Dispatch App.Install.Before
		foreach ($this->get_installers() as $installer){
			$result .= $installer->update($source_absolute_path);
		}
		// TODO Dispatch App.Install.After
		return $result;
	}

	public final function backup($destination_absolute_path){
		$exface = $this->get_workbench();
		$app = $this->get_app();
		$appAlias = $app->get_alias();
		$appNameResolver = NameResolver::create_from_string($appAlias, NameResolver::OBJECT_TYPE_APP, $exface);
		$appPath = $exface->filemanager()->get_path_to_vendor_folder() . $appNameResolver->get_class_directory();
		$result = '';
		$app->get_workbench()->filemanager()->path_construct($destination_absolute_path);
		// TODO Dispatch App.Backup.Before
		foreach ($this->get_installers() as $installer){
			$result .= $installer->backup($destination_absolute_path);
		}
		$exface->filemanager()->copyDir($appPath,$destination_absolute_path);
		// TODO Dispatch App.Backup.After
		$result .= '';
		return $result;
	}
	
	public final function uninstall(){
		
	}
	
	public function add_installer(InstallerInterface $installer, $insert_at_beinning = false){
		if ($insert_at_beinning){
			array_unshift($this->installers, $installer);
		} else {
			$this->installers[] = $installer;
		}
		return $this;
	}
	
	public function get_installers(){
		return $this->installers;
	}
	
}