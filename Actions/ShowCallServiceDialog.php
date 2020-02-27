<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DialogButton;
use exface\Core\Interfaces\Actions\ServiceParameterInterface;
use exface\Core\Interfaces\Actions\iCallService;
use exface\Core\Widgets\Value;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Factories\ActionFactory;
use exface\Core\Widgets\Button;

/**
 * Shows an input-dialog for a call-service-action (e.g. for a web service).
 * 
 * The dialog is generated automatically and includes input-widgets for
 * all service parameters defined in the action.
 *
 * @author Andrej Kabachnik
 *        
 */
class ShowCallServiceDialog extends ShowDialog
{

    private $callServiceActionButton = null;
    
    private $callServiceActionUxon = null;
    
    private $showSmallDialogIfLessAttributesThen = 7;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        // Disable prefilling the widget from contexts as we only whant to fill in data that actually comes from the data source
        $this->setPrefillWithFilterContext(false);
    }
    
    public function getName()
    {
        // Do not create the dialog widget here, because we might need the action's name before it's
        // trigger is actually activated (e.g. for the caption of the button). Creating the dialog
        // would cause a lot of overhead!
        if ($this->callServiceActionButton !== null) {
            return $this->callServiceActionButton->getCaption();
        } elseif ($this->callServiceActionUxon !== null) {
            $action = ActionFactory::createFromUxon($this->getWorkbench(), $this->callServiceActionUxon);
            return $action->getName();
        }
        return parent::getName();
    }

    /**
     * Create editors for all editable attributes of the object
     *
     * @return WidgetInterface[]
     */
    protected function createWidgetsForParameters(iCallService $action, AbstractWidget $parent_widget)
    {
        $editors = [];
        
        /* @var $attr \exface\Core\Interfaces\Model\MetaAttributeInterface */
        foreach ($action->getParameters() as $param) {
            if ($param->isEmpty() === true) {
                continue;
            }
            
            // Create the widget
            $editors[] = $this->createWidgetFromParameter($param, $parent_widget);
        }
        
        if (count($editors) == 0){
            $editors[] = WidgetFactory::create($parent_widget->getPage(), 'Message', $parent_widget)
            ->setType(MessageTypeDataType::WARNING)
            ->setText($this->getApp()->getTranslator()->translate('ACTION.SHOWOBJECTEDITDIALOG.NO_EDITABLE_ATTRIBUTES'));
        }
        
        return $editors;
    }

    /**
     * 
     * @param MetaObjectInterface $obj
     * @param string $attribute_alias
     * @param WidgetInterface $parent_widget
     * @return WidgetInterface
     */
    protected function createWidgetFromParameter(ServiceParameterInterface $parameter, WidgetInterface $parent_widget) : WidgetInterface
    {
        $paramType = $parameter->getDataType();
        $widgetUxon = $paramType->getDefaultEditorUxon();
        $widgetUxon->setProperty('caption', $parameter->getName());
        $widgetUxon->setProperty('data_column_name', $parameter->getName());
        if ($parameter->isRequired() === true) {
            $widgetUxon->setProperty('required', true);
        }
        $widget = WidgetFactory::createFromUxonInParent($parent_widget, $widgetUxon);
        
        if ($widget instanceof Value) {
            $widget->setValueDataType($paramType);
        }
        
        return $widget;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Actions\ShowDialog::createDialogWidget()
     */
    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
    {
        $dialog = parent::createDialogWidget($page);
        
        $action = $this->getCallServiceAction($dialog);
        $dialog->setCaption($action->getName());
        
        // If the content is explicitly defined, just add it to the dialog
        if (! is_null($contained_widget)) {
            $dialog->addWidget($contained_widget);
        } else {
            // Generate an editor from action's parameters
            $dialog->addWidgets($this->createWidgetsForParameters($action, $dialog));
            
            if ($dialog->countWidgetsVisible() < $this->getShowSmallDialogIfLessAttributesThen()) {
                $dialog->setColumnsInGrid(1);
            }
        }
        
        $saveButton = $this->getCallServiceActionButton($dialog);
        $saveButton
        ->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED)
        ->setAlign(EXF_ALIGN_OPPOSITE);
        // Make the save button refresh the same widget as the Button showing the dialog would do
        if ($this->getWidgetDefinedIn() instanceof Button) {
            $saveButton->setRefreshWidgetIds($this->getWidgetDefinedIn()->getRefreshWidgetIds());
            $this->getWidgetDefinedIn()->setRefreshWidgetLink(null);
        }
        $dialog->addButton($saveButton);
        
        return $dialog;
    }
    
    protected function getShowSmallDialogIfLessAttributesThen() : int
    {
        return $this->showSmallDialogIfLessAttributesThen; 
    }
    
    /**
     * Auto-generated editor will be smaller if object has less attributes, than defined here.
     * 
     * @uxon-property show_small_dialog_if_less_attributes_then
     * @uxon-type int
     * @uxon-default 7
     * 
     * @param int $number
     * @return ShowObjectInfoDialog
     */
    public function setShowSmallDialogIfLessAttributesThen(int $number) : ShowObjectInfoDialog
    {
        $this->showSmallDialogIfLessAttributesThen = $number;
        return $this;
    }
    
    protected function getCallServiceActionButton(Dialog $dialogWidget) : DialogButton
    {
        if ($this->callServiceActionButton === null && $this->callServiceActionUxon !== null) {
            $this->callServiceActionButton = $dialogWidget->createButton(new UxonObject(['action' => $this->callServiceActionUxon]));
        }
        return $this->callServiceActionButton;
    }
    
    /**
     *
     * @return UxonObject
     */
    protected function getCallServiceAction(Dialog $dialogWidget) : iCallService
    {
        return $this->getCallServiceActionButton($dialogWidget)->getAction();
    }
    
    /**
     * Configuration for the action to call
     * 
     * @uxon-property call_service_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * 
     * @param UxonObject $value
     * @return ShowCallServiceDialog
     */
    public function setCallServiceAction(UxonObject $value) : ShowCallServiceDialog
    {
        $this->callServiceActionUxon = $value;
        return $this;
    }
    
    /**
     * Alias of the action to call.
     * 
     * @uxon-property call_service_action_alias
     * @uxon-type metamodel:action
     * 
     * @param string $aliasWithNamespace
     * @throws ActionConfigurationError
     * @return ShowCallServiceDialog
     */
    public function setCallServiceActionAlias(string $aliasWithNamespace) : ShowCallServiceDialog
    {
        if ($this->callServiceActionUxon === null) {
            $this->callServiceActionUxon = new UxonObject(['alias' => $aliasWithNamespace]);
        } else {
            throw new ActionConfigurationError($this, 'Cannot set action property action_alias_to_call after action_to_call in "' . $this->getAliasWithNamespace() . '"!');
        }
        return $this;
    }
}