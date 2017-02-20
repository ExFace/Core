<?php
namespace exface\Core;
use exface\Core\CommonLogic\AbstractApp;
use exface\Core\Interfaces\InstallerInterface;
use exface\SqlDataConnector\Interfaces\SqlDataConnectorInterface;

class CoreApp extends AbstractApp {
	
	public function get_installer(InstallerInterface $injected_installer = null){
		$installer = parent::get_installer($injected_installer);
		
		// Execute the installer of the model connector first, so it can perform all DB changes.
		// Note, this will only execute custom installers of the model connector, not it's meta model installer, 
		// because that is passed by the package manager as an injected installer and is not created by the app itself.
		// TODO  add method get_installer() to ModelLoaderInterface and execute the installer here manually.
		
		return $installer;
	}
}
?>