<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class ConfigurationFactory extends AbstractStaticFactory
{

    /**
     *
     * @param AppInterface $app            
     * @return ConfigurationInterface
     */
    public static function createFromApp(AppInterface $app)
    {
        $workbench = $app->getWorkbench();
        return static::create($workbench);
    }

    /**
     *
     * @param WorkbenchInterface $workbench            
     * @return ConfigurationInterface
     */
    public static function create(WorkbenchInterface $workbench)
    {
        return new \exface\Core\CommonLogic\Configuration($workbench);
    }
}
?>