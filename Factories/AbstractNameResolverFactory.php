<?php

namespace exface\Core\Factories;

use exface\Core\Interfaces\NameResolverInterface;

abstract class AbstractNameResolverFactory extends AbstractFactory
{

    public static function create(NameResolverInterface $name_resolver)
    {
        $class = $name_resolver->getClassNameWithNamespace();
        return new $class();
    }
}
?>