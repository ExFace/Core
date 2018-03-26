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
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\CommonLogic\Selectors\DataConnectorSelector;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Interfaces\Selectors\ContextSelectorInterface;
use exface\Core\CommonLogic\Selectors\ContextSelector;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\CommonLogic\Selectors\DataTypeSelector;

/**
 * Static factory for selectors
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class SelectorFactory extends AbstractStaticFactory
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
    public static function createAppSelector(WorkbenchInterface $workbench, string $uidOrAlias) : AppSelectorInterface
    {
        return new AppSelector($workbench, $uidOrAlias);   
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $uidOrAlias
     * @return ActionSelectorInterface
     */
    public static function createActionSelector(WorkbenchInterface $workbench, string $uidOrAlias) : ActionSelectorInterface
    {
        return new ActionSelector($workbench, $uidOrAlias);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @return TemplateSelectorInterface
     */
    public static function createTemplateSelector(WorkbenchInterface $workbench, string $selectorString) : TemplateSelectorInterface
    {
        return new TemplateSelector($workbench, $selectorString);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @return UiPageSelectorInterface
     */
    public static function createPageSelector(WorkbenchInterface $workbench, string $selectorString) : UiPageSelectorInterface
    {
        return new UiPageSelector($workbench, $selectorString);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @return DataConnectorSelectorInterface
     */
    public static function createDataConnectorSelector(WorkbenchInterface $workbench, string $selectorString) : DataConnectorSelectorInterface
    {
        return new DataConnectorSelector($workbench, $selectorString);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @return ContextSelectorInterface
     */
    public static function createContextSelector(WorkbenchInterface $workbench, string $selectorString) : ContextSelectorInterface
    {
        return new ContextSelector($workbench, $selectorString);
    }
    
    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @return DataTypeSelectorInterface
     */
    public static function createDataTypeSelector(WorkbenchInterface $workbench, string $selectorString) : DataTypeSelectorInterface
    {
        return new DataTypeSelector($workbench, $selectorString);
    }
}
?>