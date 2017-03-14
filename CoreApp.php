<?php
namespace exface\Core;
use exface\Core\CommonLogic\AbstractApp;
use exface\Core\Interfaces\InstallerInterface;

class CoreApp extends AbstractApp {
	
	public function get_installer(InstallerInterface $injected_installer = null){
		$installer = parent::get_installer($injected_installer);
		// Add the custom core installer, that will take care of model schema updates, etc. 
		// Make sure, it runs before any other installers do.
		$installer->add_installer(new CoreInstaller($this->get_name_resolver()), true);
		return $installer;
	}
}
?>