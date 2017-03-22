<?php
namespace exface\Core;

use exface\Core\CommonLogic\AbstractAppInstaller;

/**
 * 
 * 
 * @method CoreApp get_app()
 * 
 * @author Andrej Kabachnik
 *
 */
class CoreInstaller extends AbstractAppInstaller {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\AbstractApp::install()
	 */
	public function install($source_absolute_path){
		$model_source_installer = $this->get_workbench()->model()->get_model_loader()->get_installer();
		return $model_source_installer->install($this->get_workbench()->filemanager()->get_path_to_vendor_folder() . DIRECTORY_SEPARATOR . $model_source_installer->get_name_resolver()->get_class_directory());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\InstallerInterface::update()
	 */
	public function update($source_absolute_path){
		$model_source_installer = $this->get_workbench()->model()->get_model_loader()->get_installer();
		return $model_source_installer->update($this->get_workbench()->filemanager()->get_path_to_vendor_folder() . DIRECTORY_SEPARATOR . $model_source_installer->get_name_resolver()->get_class_directory());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
	 */
	public function uninstall(){
		return 'Uninstall not implemented for' . $this->get_name_resolver()->get_alias_with_namespace() . '!';
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\InstallerInterface::backup()
	 */
	public function backup($destination_absolute_path){
		return 'Backup not implemented for' . $this->get_name_resolver()->get_alias_with_namespace() . '!';
	}
}
?>