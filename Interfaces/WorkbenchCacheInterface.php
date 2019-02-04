<?php
namespace exface\Core\Interfaces;

use Psr\SimpleCache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;

interface WorkbenchCacheInterface extends CacheInterface, WorkbenchDependantInterface
{
    /**
     * Creates a cache pool with the default implementation.
     * 
     * @param WorkbenchInterface $workbench
     * @param string $name
     * @return CacheInterface
     */
    public static function createDefaultPool(WorkbenchInterface $workbench, string $name = null) : CacheInterface;
    
    /**
     * Returns the cache pool matching the given name; automatically creates one if needed.
     * 
     * Set $autoCreate parameter to FALSE to throw an exception if no pool matching the
     * given name was found.
     * 
     * @param string $name
     * @return CacheInterface|CacheItemPoolInterface
     */
    public function getPool(string $name, bool $autoCreate = true);
    
    /**
     * Registeres the given cache implementation as a cache pool under the provided name.
     * 
     * @param string $name
     * @param CacheInterface|CacheItemPoolInterface $psr6or16
     */
    public function addPool(string $name, $psr6or16) : WorkbenchCacheInterface;
}