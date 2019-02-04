<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\WorkbenchCacheInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Psr\Cache\CacheItemPoolInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\OutOfRangeException;
use exface\Core\Exceptions\OutOfBoundsException;

/**
 * Default implementation of the WorkbenchCacheInterface.
 * 
 * @author Andrej Kabachnik
 *
 */
class WorkbenchCache implements WorkbenchCacheInterface
{
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
    public function deleteMultiple($keys)
    {
        return $this->mainPool->deleteMultiple($keys);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::set()
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->mainPool->set($key, $value, $ttl);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::getMultiple()
     */
    public function getMultiple($keys, $default = null)
    {
        return $this->mainPool->getMultiple($keys, $default);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::get()
     */
    public function get($key, $default = null)
    {
        return $this->mainPool->get($key, $default);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::clear()
     */
    public function clear()
    {
        // Clear main cache pool
        $ok = $this->mainPool->clear();
        
        // Clear CMS cache
        try {
            $this->workbench->getCMS()->clearCmsCache();
        } catch (\Throwable $e) {
            $ok = false;
            $this->workbench->getLogger()->logException($e);
        }
        
        // Empty cache dir
        $filemanager = $this->workbench->filemanager();
        try {
            $filemanager = $this->filemanager();
            $filemanager->emptyDir($this->filemanager()->getPathToCacheFolder());
        } catch (\Throwable $e){
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
    public function setMultiple($values, $ttl = null)
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
    public function has($key)
    {
        return $this->mainPool->has($key);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::delete()
     */
    public function delete($key)
    {
        return $this->mainPool->delete($key);
    }
    
    
    public static function createDefaultPool(WorkbenchInterface $workbench, string $name = null): CacheInterface
    {
        return new FilesystemCache($name ?? '_workbench', 0, $workbench->filemanager()->getPathToCacheFolder());
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
}