<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Security\Authorization\UiPageAuthorizationPoint;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\iCallOtherActions;

/**
 * A generic task for objects, actions, pages, widgets and input/prefill data
 * 
 * @author Andrej Kabachnik
 *
 */
class GenericTask implements TaskInterface
{
    use ImportUxonObjectTrait {
        importUxonObject as importUxonObjectViaTrait;
    }
    
    private $facade = null;
    
    private $workbench = null;
    
    private $parameters = [];
    
    private $prefillData = null;
    
    private $inputData = null;
    
    private $transaction = null;
    
    private $object = null;
    
    private $objectSelector = null;
    
    private $actionSelector = null;
    
    private $action = null;
    
    private $originWidget = null;
    
    private $originWigetId = null;
    
    private $originPageSelctor = null;
    
    private $originPage = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     */
    public function __construct(WorkbenchInterface $workbench, FacadeInterface $facade = null)
    {
        $this->facade = $facade;
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getFacade()
     */
    public function getFacade() : ?FacadeInterface
    {
        return $this->facade;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getParameter()
     */
    public function getParameter($name)
    {
        return $this->parameters[$name] ?? null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setParameter()
     */
    public function setParameter($name, $value) : TaskInterface
    {
        $this->parameters[$name] = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getParameters()
     */
    public function getParameters() : array
    {
        return $this->parameters;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::hasParameter()
     */
    public function hasParameter($name) : bool
    {
        return array_key_exists($name, $this->parameters);
    }
    
    /**
     * Parameters of this task defined as a generic list of key-value-pairs.
     * 
     * @uxon-property parameters
     * @uxon-type object
     * @uxon-template {"":""}
     * 
     * @param array|UxonObject $array
     */
    protected function setParameters($array) : TaskInterface
    {
        if ($array instanceof UxonObject) {
            $array = $array->toArray();
        }
        foreach ($array as $name => $value) {
            $this->setParameter($name, $value);
        }
        return $this;
    }

    /**
     * Data sheet to be used as input data
     * 
     * @uxon-property input_data
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "rows": [{"": "", "": ""}]}
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setInputData()
     */
    public function setInputData(DataSheetInterface $dataSheet): TaskInterface
    {
        $this->inputData = $dataSheet;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getPrefillData()
     */
    public function getPrefillData()
    {
        if (is_null($this->prefillData)) {
            $this->prefillData = DataSheetFactory::createFromObject($this->getMetaObject());
        }
        return $this->prefillData->copy();
    }

    /**
     * Data sheet to be used as prefill data
     * 
     * @uxon-property prefill_data
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "rows": [{"": "", "": ""}]}
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setPrefillData()
     */
    public function setPrefillData(DataSheetInterface $dataSheet): TaskInterface
    {
        $this->prefillData = $dataSheet;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getActionSelector()
     */
    public function getActionSelector() : ActionSelectorInterface
    {
        return $this->actionSelector;
    }

    /**
     * UID or namespaced alias of the action to be performed
     * 
     * @uxon-property action_alias
     * @uxon-type metamodel:action
     * 
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setActionSelector()
     */
    public function setActionSelector($selectorOrString): TaskInterface
    {
        $this->action = null;
        if ($selectorOrString instanceof ActionSelectorInterface) {
            $this->actionSelector = $selectorOrString;
        } else {
            $this->actionSelector = new ActionSelector($this->getWorkbench(), $selectorOrString);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        $widget = null;
        if ($this->action === null) {
            if ($this->isTriggeredByWidget()) {
                $widget = $this->getWidgetTriggeredBy();
                if (! $this->hasMetaObject()) {
                    $this->setMetaObject($widget->getMetaObject());
                }
                
                // If the task tigger is a button or similar, the action might be defined in that
                // trigger widget. However, we can only use this definition, if the task does not
                // have an action explicitly defined or that action is exactly the same as the
                // one of the trigger widget.
                if ($widget instanceof iTriggerAction) {
                    if (! $this->hasAction()) {
                        $action = $widget->getAction();
                    } elseif ($widget->hasAction()) {
                        // At this point, we know, that both, task and widget, have actions - so we
                        // need to compare them.
                        if ($this->getActionSelector()->isAlias() && strcasecmp($this->getActionSelector()->toString(), $widget->getAction()->getAliasWithNamespace()) === 0) {
                            // In most cases, the task action will be defined via
                            // alias, so we can simply compare the alias without instantiating the action.
                            $action = $widget->getAction();
                        } else {
                            // Otherwise we need to instantiate it first to get the alias.
                            $task_action = ActionFactory::create($this->getActionSelector(), ($widget ? $widget : null));
                            $widget_action = $widget->getAction();
                            switch (true) {
                                // If the task tells us to perform the action of the widget, use the description in the
                                // widget, because it is more detailed.
                                case $task_action->isExactly($widget_action):
                                    $action = $widget->getAction();
                                    break;
                                    
                                    // If the widget triggers an action containing multiple sub-actions, see if one of them
                                    // matches the task action
                                case $widget_action instanceof iCallOtherActions:
                                    $action = $widget_action->getActionToStart($this);
                                    if ($action !== null) {
                                        break;
                                    }
                                    // If none match, continue with the default.
                                    
                                    // If the task is about another action (e.g. ReadPrefill on a button, that does ShowDialog),
                                    // Take the task action and inherit action settings related to the input data from the widget.
                                default:
                                    $action = $task_action;
                                    if ($widget_action->hasInputDataPreset() === true) {
                                        $action->setInputDataPreset($widget->getAction()->getInputDataPreset());
                                    }
                                    if ($widget_action->hasInputMappers() === true) {
                                        foreach ($widget_action->getInputMappers() as $mapper) {
                                            $action->addInputMapper($mapper);
                                        }
                                    }
                                    break;
                            }
                        }
                        
                    }
                }
            }
            
            if (! isset($action)) {
                $action = ActionFactory::create($this->getActionSelector(), $widget);
            }
            
            $this->action = $action;
        }
        
        return $this->action;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getInputData()
     */
    public function getInputData(): DataSheetInterface
    {
        if (is_null($this->inputData)) {
            switch (true) {
                case $this->isTriggeredByWidget():
                    $this->inputData = DataSheetFactory::createFromObject($this->getWidgetTriggeredBy()->getMetaObject());
                    break;
                case $this->hasMetaObject():
                    $this->inputData = DataSheetFactory::createFromObject($this->getMetaObject());
                    break;
                case $this->isTriggeredOnPage():
                    $this->inputData = DataSheetFactory::createFromObject($this->getWidgetTriggeredBy()->getMetaObject());
                    break;
            }
            $this->inputData = DataSheetFactory::createFromObject($this->getMetaObject());
        }
        
        return $this->inputData->copy();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getMetaObject()
     */
    public function getMetaObject(): MetaObjectInterface
    {
        if ($this->object === null){
            if (! is_null($this->objectSelector)){
                $this->object = $this->getWorkbench()->model()->getObject($this->objectSelector);
            } elseif ($this->hasInputData()) {
                $this->object = $this->getInputData()->getMetaObject();
            } elseif ($this->isTriggeredByWidget()) {
                $this->object = $this->getWidgetTriggeredBy()->getMetaObject();
            } elseif ($this->isTriggeredOnPage()) {
                $this->object = $this->getWidgetTriggeredBy()->getMetaObject();
            }
        }
        return $this->object;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getMetaObjectSelector()
     */
    public function getMetaObjectSelector() : ?MetaObjectSelectorInterface
    {
        return $this->objectSelector;
    }

    /**
     * UID or namespaced alias of the base meta object of this task
     * 
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setMetaObject()
     */
    public function setMetaObject(MetaObjectInterface $object): TaskInterface
    {
        $this->object = $object;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::hasInputData()
     */
    public function hasInputData(): bool
    {
        return is_null($this->inputData) ? false : true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::hasPrefillData()
     */
    public function hasPrefillData(): bool
    {
        return is_null($this->prefillData) || $this->prefillData->isBlank() ? false : true;
    }
    
    /**
     * UID or namespaced alias of the meta object
     * 
     * @uxon-property meta_object
     * @uxon-type metamodel:object
     * 
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setMetaObjectSelector()
     */
    public function setMetaObjectSelector($selectorOrString): TaskInterface
    {
        if ($selectorOrString instanceof MetaObjectSelectorInterface) {
            $this->objectSelector = $selectorOrString;
        } else {
            $this->objectSelector = new MetaObjectSelector($this->getWorkbench(), $selectorOrString);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::hasAction()
     */
    public function hasAction(): bool
    {
        return is_null($this->actionSelector) ? false : true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::hasMetaObject()
     */
    public function hasMetaObject(bool $checkAllTaskParams = false): bool
    {
        if ($this->objectSelector === null && $this->object === null){
            if ($checkAllTaskParams === false) { 
                return false;
            } else {
                try {
                    $this->getMetaObject();
                    return true;
                } catch (\Throwable $e) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::isTriggeredByWidget()
     */
    public function isTriggeredByWidget(): bool
    {
        return $this->isTriggeredOnPage() && $this->originWigetId !== null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::isTriggeredOnPage()
     */
    public function isTriggeredOnPage(): bool
    {
        return $this->originPageSelctor !== null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setWidgetIdTriggeredBy()
     */
    public function setWidgetIdTriggeredBy($string) : TaskInterface
    {
        $this->originWigetId = $string;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getWidgetTriggeredBy()
     */
    public function getWidgetTriggeredBy(): WidgetInterface
    {
        $page = $this->getPageTriggeredOn();
        if (is_null($this->originWigetId)) {
            return $page->getWidgetRoot();
        } else {
            return $page->getWidget($this->originWigetId);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getPageSelector()
     */
    public function getPageSelector(): UiPageSelectorInterface
    {
        return $this->originPageSelctor;
    }

    /**
     * UID or namespaced alias of the UI page this task is referring to
     * 
     * @uxon-property page_alias
     * @uxon-type metamodel:page
     * 
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setPageSelector()
     */
    public function setPageSelector($selectorOrString): TaskInterface
    {
        if ($selectorOrString instanceof UiPageSelectorInterface) {
            $this->originPageSelctor = $selectorOrString;
        } elseif ($selectorOrString !== '') {
            $this->originPageSelctor = new UiPageSelector($this->getWorkbench(), $selectorOrString);
        }
        $this->originPage = null;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setPage()
     */
    public function setPage(UiPageInterface $page) : TaskInterface
    {
        if ($this->isTriggeredOnPage() === true) {
            throw new RuntimeException('Cannot change page of a task, that is triggered by another page!');
        }
        $this->originPage = $page;
        $this->originPageSelctor = $page->getSelector();
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getPageTriggeredOn()
     */
    public function getPageTriggeredOn() : UiPageInterface
    {
        if (is_null($this->originPage)) {
            $this->originPage = UiPageFactory::create($this->getPageSelector());
            $pageAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(UiPageAuthorizationPoint::class);
            $this->originPage = $pageAP->authorize($this->originPage);
        }
        return $this->originPage;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        if ($this->isTriggeredOnPage()) {
            $uxon->setProperty('page_alias', $this->getPageSelector()->toString());
        }
        if ($this->isTriggeredByWidget()) {
            $uxon->setProperty('widget_id', $this->originWigetId ?? $this->getWidgetTriggeredBy()->getId());
        }
        if ($this->hasMetaObject()) {
            $uxon->setProperty('object_alias', $this->getMetaObject()->getAliasWithNamespace());
        }
        if ($this->hasAction()) {
            $uxon->setProperty('action_alias', $this->getActionSelector()->toString());
        }
        if ($this->hasInputData()) {
            $uxon->setProperty('input_data', $this->getInputData()->exportUxonObject());
        }
        if ($this->hasPrefillData()) {
            $uxon->setProperty('prefill_data', $this->getPrefillData()->exportUxonObject());
        }
        if (! empty($this->getParameters())) {
            $uxon->setProperty('parameters', $this->getParameters());
        }
        return $uxon;
    }

    /**
     * 
     * @return string|NULL
     */
    public static function getUxonSchemaClass(): ?string
    {
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        foreach ($uxon->getPropertiesAll() as $prop => $val) {
            switch ($prop) {
                case 'input_data': $this->setInputData(DataSheetFactory::createFromUxon($this->getWorkbench(), $val)); break;
                case 'prefill_data': $this->setPrefillData(DataSheetFactory::createFromUxon($this->getWorkbench(), $val)); break;
                case 'action': 
                case 'action_alias':
                    $this->setActionSelector($val); 
                    break;
                case 'page':
                case 'page_selector': 
                case 'page_alias':
                    $this->setPageSelector($val); 
                    break;
                case 'meta_object': 
                case 'object_alias': 
                    $this->setMetaObjectSelector($val); 
                    break;
                case 'widget_id':
                    $this->setWidgetIdTriggeredBy($val);
                    break;
            }
        }
        // Fall back to the default importer for all other properties
        $this->importUxonObjectViaTrait($uxon, [
            'input_data',
            'prefill_data',
            'action', 'action_alias',
            'page', 'page_selector', 'page_alias',
            'meta_object', 'object_alias',
            'widget_id'
        ]);
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     */
    public function copy() : self
    {
        $copy = clone $this;
        return $copy;
    }
}