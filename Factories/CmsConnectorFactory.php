<?php

namespace exface\Core\Factories;

use exface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\CmsConnectorInterface;

abstract class CmsConnectorFactory extends AbstractNameResolverFactory
{

    /**
     * Creates a new CMS connector
     * 
     * @param NameResolverInterface $name_resolver            
     * @return CmsConnectorInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        $class = $name_resolver->getClassNameWithNamespace();
        $exface = $name_resolver->getWorkbench();
        return new $class($exface);
    }
}
?>