<?php namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\NameResolverInstallerInterface;

/**
 * 
 * @author Andrej Kabachnik
 * 
 */
abstract class AbstractNameResolverInstaller implements NameResolverInstallerInterface {
	private $name_resolver = null;
	
	/**
	 * 
	 * @param NameResolverInterface $name_resolver
	 */
	public function __construct(NameResolverInterface $name_resolver){
		$this->name_resolver = $name_resolver;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\NameResolverInstallerInterface::get_name_resolver()
	 */
	public function get_name_resolver(){
		return $this->name_resolver;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\ExfaceClassInterface::get_workbench()
	 */
	public function get_workbench(){
		return $this->get_name_resolver()->get_workbench();
	}
	
}