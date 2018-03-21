<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\TemplateSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\TemplateSelector;

abstract class TemplateFactory extends AbstractSelectorFactory
{

    /**
     *
     * @param NameResolverInterface $name_resolver            
     * @return TemplateInterface
     */
    public static function create(SelectorInterface $selector)
    {
        if (! ($selector instanceof TemplateSelectorInterface)) {
            throw new InvalidArgumentException('Cannot create template from selector "' . get_class($selector) . '": expecting "TemplateSelector" or derivatives!');
        }
        
        return $selector->getWorkbench()->getApp($selector->getAppSelector())->get($selector);
    }

    /**
     *
     * @param string $aliasOrPathOrClassname            
     * @param WorkbenchInterface $exface            
     * @return TemplateInterface
     */
    public static function createFromString($aliasOrPathOrClassname, WorkbenchInterface $exface)
    {
        $selector = new TemplateSelector($exface, $aliasOrPathOrClassname);
        return static::create($selector);
    }

    /**
     *
     * @param string|NameResolverInterface|TemplateInterface $selectorOrString            
     * @param WorkbenchInterface $exface            
     * @return \exface\Core\Interfaces\Templates\TemplateInterface
     */
    public static function createFromAnything($selectorOrString, WorkbenchInterface $exface)
    {
        if ($selectorOrString instanceof TemplateInterface) {
            $template = $selectorOrString;
        } elseif ($selectorOrString instanceof TemplateSelectorInterface) {
            $template = static::create($selectorOrString);
        } elseif (is_string($selectorOrString)) {
            $template = static::createFromString($selectorOrString, $exface);
        } else {
            throw new InvalidArgumentException('Cannot create template from "' . get_class($selectorOrString) . '": expecting "TemplateSelector" or valid selector string!');
        }
        return $template;
    }
}
?>