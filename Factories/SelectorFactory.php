<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\TemplateSelector;
use exface\Core\Interfaces\Selectors\TemplateSelectorInterface;

/**
 * Static factory for selectors
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class SelectorFactory extends AbstractFactory
{

    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @param string $selectorClass
     * @return SelectorInterface
     */
    public static function createFromString(WorkbenchInterface $workbench, string $selectorString, string $selectorClass) : SelectorInterface
    {
        $class = $selectorClass;
        return new $class($workbench, $selectorString);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uidOrAlias
     * @return AppSelectorInterface
     */
    public function createAppSelector(WorkbenchInterface $workbench, string $uidOrAlias) : AppSelectorInterface
    {
        return new AppSelector($workbench, $uidOrAlias);   
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $uidOrAlias
     * @return ActionSelectorInterface
     */
    public function createActionSelector(WorkbenchInterface $workbench, string $uidOrAlias) : ActionSelectorInterface
    {
        return new ActionSelector($workbench, $uidOrAlias);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $uidOrAlias
     * @return TemplateSelectorInterface
     */
    public function createTemplateSelector(WorkbenchInterface $workbench, string $selectorString) : TemplateSelectorInterface
    {
        return new TemplateSelector($workbench, $selectorString);
    }
}
?>