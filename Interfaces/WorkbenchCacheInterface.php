<?php
namespace exface\Core\Interfaces;

use Psr\SimpleCache\CacheInterface;

interface WorkbenchCacheInterface extends CacheInterface, WorkbenchDependantInterface
{
    public static function createDefaultPool(WorkbenchInterface $workbench, string $name = null) : CacheInterface;
}