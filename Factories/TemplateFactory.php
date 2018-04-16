<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\Selectors\TemplateSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\TemplateSelector;

abstract class TemplateFactory extends AbstractSelectableComponentFactory
{

    /**
     *
     * @param TemplateSelectorInterface $name_resolver            
     * @return TemplateInterface
     */
    public static function create(TemplateSelectorInterface $selector) : TemplateInterface
    {
        return parent::createFromSelector($selector);
    }

    /**
     *
     * @param string $aliasOrPathOrClassname            
     * @param WorkbenchInterface $exface            
     * @return TemplateInterface
     */
    public static function createFromString(string $aliasOrPathOrClassname, WorkbenchInterface $exface) : TemplateInterface
    {
        $selector = new TemplateSelector($exface, $aliasOrPathOrClassname);
        return static::create($selector);
    }

    /**
     *
     * @param string|TemplateSelectorInterface|TemplateInterface $selectorOrString            
     * @param WorkbenchInterface $exface            
     * @return \exface\Core\Interfaces\Templates\TemplateInterface
     */
    public static function createFromAnything($selectorOrString, WorkbenchInterface $exface) : TemplateInterface
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