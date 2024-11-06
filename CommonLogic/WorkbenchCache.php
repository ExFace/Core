<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\WorkbenchCacheInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\OutOfBoundsException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

/**
 * Default implementation of the WorkbenchCacheInterface.
 * 
 * @author Andrej Kabachnik
 *
 */
class WorkbenchCache implements WorkbenchCacheInterface
{
    const KEY_RESERVED_CHARS = ["\\", '/', '[', ']', '(', ')', '@', ':'];
    
    private $workbench = null;
    private $mainPool = null;
    private $pools = [];
 
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param CacheInterface $mainPool
     */
    public function __construct(WorkbenchInterface $workbench, CacheInterface $mainPool)
    {
        $this->workbench = $workbench;
        $this->mainPool = $mainPool;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::deleteMultiple()
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->mainPool->deleteMultiple($keys);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::set()
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return $this->mainPool->set($key, $value, $ttl);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::getMultiple()
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->mainPool->getMultiple($keys, $default);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::get()
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        return $this->mainPool->get($key, $default);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::clear()
     */
    public function clear(): bool
    {
        $ok = true;
        
        // Clear APCU cache if it is enabled
        if ($this->workbench->getConfig()->getOption('CACHE.USE_APCU') === true && $this::isAPCUAvailable()) {
            try {
                $ok = apcu_clear_cache() === false ? false : $ok;
            } catch (\Throwable $e){
                $ok = false;
                $this->workbench->getLogger()->logException($e);
            }
        }
        
        // Empty cache dir in any case
        try {
            $filemanager = $this->workbench->filemanager();
            $filemanager::emptyDir($filemanager->getPathToCacheFolder());
        } catch (\Throwable $e){
            $ok = false;
            $this->workbench->getLogger()->logException($e);
        }
        
        // Clear cache pools currently being used
        try {
            $ok = $this->mainPool->clear() === false ? false : $ok;
            foreach ($this->pools as $pool) {
                $ok = $pool->clear() === false ? false : $ok;
            }
        } catch (\Throwable $e) {
            $ok = false;
            $this->workbench->getLogger()->logException($e);
        }
        
        
        return $ok;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return $this->mainPool->setMultiple($values, $ttl);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::has()
     */
    public function has(string $key): bool
    {
        return $this->mainPool->has($key);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::delete()
     */
    public function delete(string $key): bool
    {
        return $this->mainPool->delete($key);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $name
     */
    public static function createDefaultPool(WorkbenchInterface $workbench, string $name = null, bool $psr16 = true)
    {
        $config = $workbench->getConfig();
        switch (true) {
            case $config->getOption('CACHE.ENABLED') === false:
                $psr6Cache = new ArrayAdapter();
                break;
            case $config->getOption('CACHE.USE_APCU') === true && static::isAPCUAvailable():
                $psr6Cache = new ApcuAdapter($name ?? '_workbench', 0);
                break;
            default:
                $psr6Cache = new PhpFilesAdapter($name ?? '_workbench', 0, $workbench->filemanager()->getPathToCacheFolder());
                break;
        }
        if ($psr16) {
            return new Psr16Cache($psr6Cache);
        }
        return $psr6Cache;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchCacheInterface::addPool()
     */
    public function addPool(string $name, $psr6or16) : WorkbenchCacheInterface
    {
        if (! ($psr6or16 instanceof CacheInterface) && ! ($psr6or16 instanceof CacheItemPoolInterface)) {
            throw new InvalidArgumentException('Invalid cache pool class "' . get_class($psr6or16) . '": a cache pool MUST be compatible to PSR-6 or PSR-16!');
        }
        
        $this->pools[$name] = $psr6or16;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchCacheInterface::getPool()
     */
    public function getPool(string $name, bool $autoCreate = true)
    {
        if ($name === '') {
            return $this->mainPool;
        }
        
        if ($this->pools[$name] === null) {
            if ($autoCreate === true) {
                $this->pools[$name] = static::createDefaultPool($this->workbench, $name);
            } else {
                throw new OutOfBoundsException('Cache pool "' . $name . '" not found!');
            }
        }
        
        return $this->pools[$name];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchCacheInterface::hasPool()
     */
    public function hasPool(string $name) : bool
    {
        return null !== $this->pools[$name] ?? null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchCacheInterface::getPoolDefault()
     */
    public function getPoolDefault()
    {
        return $this->mainPool;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchCacheInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        return $this->getWorkbench()->getConfig()->getOption('CACHE.ENABLED') === false;
    }
    
    /**
     * 
     * @return bool
     */
    protected static function isAPCUAvailable() : bool
    {
        return function_exists('apcu_clear_cache');
    }
}