<?php namespace exface\Core\Interfaces;

interface InstallerContainerInterface extends InstallerInterface {	
	
	/**
	 * 
	 * @param InstallerInterface $installer
	 * @return InstallerContainerInterface
	 */
	public function add_installer(InstallerInterface $installer);
	
	/**
	 * 
	 * @return InstallerInterface[]
	 */
	public function get_installers();
	
}
?>