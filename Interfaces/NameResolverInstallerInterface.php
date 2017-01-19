<?php namespace exface\Core\Interfaces;

interface NameResolverInstallerInterface extends InstallerInterface {	
	
	/**
	 * 
	 * @param NameResolverInterface $name_resolver
	 */
	public function __construct(NameResolverInterface $name_resolver);
	
	/**
	 * Returns the name resolver representing the element to install
	 * 
	 * @return NameResolverInterface
	 */
	public function get_name_resolver();
	
}
?>