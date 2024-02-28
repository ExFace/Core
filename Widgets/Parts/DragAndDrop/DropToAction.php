<?php
namespace exface\Core\Widgets\Parts\DragAndDrop;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\CommonLogic\DataSheets\Mappings\DataColumnMapping;
use exface\Core\Factories\DataSheetMapperFactory;

class DropToAction implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $widget = null;
    
    private $objectAlias = null;
    
    private $object = null;
    
    private $actionUxon = null;
    
    private $action = null;
    
    private $triggerWidget = null;
    
    private $includeTargetColumnsUxon = null;
    
    private $includeTargetColumnMapper = null;
    
    public function __construct(WidgetInterface $widget, UxonObject $uxon = null)
    {
        $this->widget = $widget;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }
    
    public function getMetaObject() : MetaObjectInterface
    {
        if ($this->object === null) {
            if ($this->objectAlias === null) {
                $this->object = $this->getDataWidget()->getMetaObject();
            } else {
                $this->object = MetaObjectFactory::createFromString($this->getWorkbench(), $this->objectAlias);
            }
        }
        return $this->object;
    }
    
    /**
     * Alias of the object to be dropped
     * 
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * 
     * @param string $value
     * @return DropToAction
     */
    protected function setObjectAlias(string $value) : DropToAction
    {
        $this->objectAlias = $value;
        $this->object = null;
        return $this;
    }
    
    protected function getActionUxon() : UxonObject
    {
        return $this->actionUxon;
    }
    
    public function getAction() : ActionInterface
    {
        if ($this->action === null) {
            $this->action = $this->getActionTrigger()->getAction();
        }
        return $this->action;   
    }
    
    /**
     * Action to be performed on drop
     * 
     * @uxon-property action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias: ""}
     * 
     * @param UxonObject $value
     * @return DropToAction
     */
    protected function setAction(UxonObject $value) : DropToAction
    {
        $this->actionUxon = $value;
        $this->action = null;
        $this->triggerWidget = null;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
    
    /**
     *
     * @return iTriggerAction
     */
    public function getActionTrigger() : iTriggerAction
    {
        if ($this->triggerWidget === null) {
            if ($this->getWidget() instanceof iHaveButtons) {
                $btnType = $this->getWidget()->getButtonWidgetType();
            } else {
                $btnType = 'Button';
            }
            $btnUxon = new UxonObject([
                'action' => $this->getActionUxon()->toArray()
            ]);
            $this->triggerWidget = WidgetFactory::createFromUxonInParent($this->getWidget(), $btnUxon, $btnType);
        }
        return $this->triggerWidget;
    }
    
    /**
     * 
     * @return DataColumnMapping[]
     */
    public function getIncludeTargetColumnMappings() : array
    {
        if ($this->includeTargetColumnMapper === null) {
            if ($this->includeTargetColumnsUxon === null) {
                return [];
            }
            $uxon = new UxonObject([
                'from_object_alias' => $this->getMetaObject()->getAliasWithNamespace(),
                'to_object_alias' => $this->getMetaObject()->getAliasWithNamespace(),
                'column_to_column_mappings' => $this->includeTargetColumnsUxon->toArray()
            ]);
            $this->includeTargetColumnMapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $uxon);
        }
        return $this->includeTargetColumnMapper->getMappings();
    }
    
    /**
     * Add the following columns from the drop-target to the dropped data when calling the action
     * 
     * @uxon-property include_target_columns
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataColumnMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     * 
     * @param UxonObject $value
     * @return DropToAction
     */
    protected function setIncludeTargetColumns(UxonObject $value) : DropToAction
    {
        $this->includeTargetColumnsUxon = $value;
        $this->includeTargetColumnMapper = null;
        return $this;
    }
}