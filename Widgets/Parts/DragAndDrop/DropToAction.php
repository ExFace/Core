<?php
namespace exface\Core\Widgets\Parts\DragAndDrop;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;

class DropToAction implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $widget = null;
    
    private $objectAlias = null;
    
    private $actionUxon = null;
    
    private $action = null;
    
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
    
    protected function getObjectAlias() : string
    {
        if ($this->objectAlias === null) {
            return $this->getDataWidget()->getMetaObject();
        }
        return $this->objectAlias;
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
    public function setObjectAlias(string $value) : DropToAction
    {
        $this->objectAlias = $value;
        return $this;
    }
    
    protected function getActionUxon() : UxonObject
    {
        return $this->actionUxon;
    }
    
    public function getAction() : ActionInterface
    {
        if ($this->action === null) {
            $this->action = ActionFactory::createFromUxon($this->getWorkbench(), $this->actionUxon, $this->getDataWidget());
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
    public function setAction(UxonObject $value) : DropToAction
    {
        $this->actionUxon = $value;
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
}