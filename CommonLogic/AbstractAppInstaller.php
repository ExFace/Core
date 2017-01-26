<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\AppInstallerInterface;

/**
 * 
 * @author Andrej Kabachnik
 * 
 */
abstract class AbstractAppInstaller extends AbstractNameResolverInstaller implements AppInstallerInterface {
	
	private $app = null;
	private $install_folder_name = 'Install';
	
	/**
	 *
	 * @return \exface\Core\Interfaces\AppInterface
	 */
	public function get_app(){
		if (is_null($this->app)){
			$this->app = $this->get_workbench()->get_app($this->get_name_resolver()->get_alias_with_namespace());
		}
		return $this->app;
	}
	
	/**
	 * Returns the absolute path to the folder containing installation files for this app. By default it is %app_folder%/Install.
	 * 
	 * @return string
	 */
	public function get_install_folder_absolute_path($source_absolute_path){
		return $source_absolute_path . DIRECTORY_SEPARATOR . $this->get_install_folder_name();
	}
	
	/**
	 * Returns the path to the folder containing installation files for this app relative to the app folder. Default: "Install".
	 * 
	 * @return string
	 */
	public function get_install_folder_name() {
		return $this->install_folder_name;
	}
	
	/**
	 * Changes the name of the folder, that contains installation files for this app. 
	 * 
	 * @param string $path_relative_to_app_folder
	 * @return \exface\Core\CommonLogic\AbstractAppInstaller
	 */
	public function set_install_folder_name($path_relative_to_app_folder) {
		$this->install_folder_name = $path_relative_to_app_folder;
		return $this;
	}
	 
}