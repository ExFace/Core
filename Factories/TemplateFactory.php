<?php

namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\TemplateInterface;

abstract class TemplateFactory extends AbstractNameResolverFactory
{

    /**
     *
     * @param NameResolverInterface $name_resolver            
     * @return TemplateInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        $exface = $name_resolver->getWorkbench();
        $class = $name_resolver->getClassNameWithNamespace();
        $template = new $class($exface);
        $template->setNameResolver($name_resolver);
        return $template;
    }

    /**
     *
     * @param string $qualified_alias            
     * @param exface $exface            
     * @return TemplateInterface
     */
    public static function createFromString($qualified_alias, Workbench $exface)
    {
        $name_resolver = NameResolver::createFromString($qualified_alias, NameResolver::OBJECT_TYPE_TEMPLATE, $exface);
        return static::create($name_resolver);
    }

    /**
     *
     * @param string|NameResolverInterface|TemplateInterface $name_reslver_or_alias_or_template            
     * @param exface $exface            
     * @return \exface\Core\Interfaces\TemplateInterface
     */
    public static function createFromAnything($name_reslver_or_alias_or_template, Workbench $exface)
    {
        if ($name_reslver_or_alias_or_template instanceof TemplateInterface) {
            $template = $name_reslver_or_alias_or_template;
        } elseif ($name_reslver_or_alias_or_template instanceof NameResolverInterface) {
            $template = static::create($name_reslver_or_alias_or_template);
        } else {
            $template = static::createFromString($name_reslver_or_alias_or_template, $exface);
        }
        return $template;
    }
}
?>