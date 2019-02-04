<?php
namespace exface\Core\Interfaces;

use Psr\SimpleCache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The workbench cache provides a centralized access point to cache pools.
 * 
 * The workbench can use multiple cache storages and implementations, which are
 * called cache pools and referenced by their unique name. Apps can use any
 * of the existing pools or create their own ones (with their own implementation).
 * Registering all pools in the workbench cache ensures easy reusability!
 * 
 * The workbench allways provides a default cache pool accessible via getPoolDefault().
 * 
 * Developers are encouraged to use separate pools to prevent item key collisions.
 * 
 * @author Andrej Kabachnik
 *
 */
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
     * Pools, that have not been added previously via addPool() will be created with the
     * sam settings as the default pool. It still makes sense to use separate pools in
     * order to prevent item name collisions.
     * 
     * @param string $name
     * @param bool $autoCreate
     * 
     * @return CacheInterface|CacheItemPoolInterface
     */
    public function getPool(string $name, bool $autoCreate = true);
    
    /**
     * Returns the defaul cache pool of the workbench.
     * 
     * @return CacheInterface|CacheItemPoolInterface
     */
    public function getPoolDefault();
    
    /**
     * Registeres the given cache implementation as a cache pool under the provided name.
     * 
     * @param string $name
     * @param CacheInterface|CacheItemPoolInterface $psr6or16
     */
    public function addPool(string $name, $psr6or16) : WorkbenchCacheInterface;
}