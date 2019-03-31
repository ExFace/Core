<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Generic task implementation to create task programmatically.
 * 
 * @author Andrej Kabachnik
 *
 */
class GenericTask implements TaskInterface
{
    private $facade = null;
    
    private $workbench = null;
    
    private $parameters = [];
    
    private $prefillData = null;
    
    private $inputData = null;
    
    private $transaction = null;
    
    private $object = null;
    
    private $objectSelector = null;
    
    private $actionSelector = null;
    
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
    public function getFacade() : FacadeInterface
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
        return $this->parameters[$name];
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
     * Adds the parameters of this task with those in the given array replacing duplicates.
     * 
     * @param array $array
     */
    protected function setParameters(array $array) : TaskInterface
    {
        foreach ($array as $name => $value) {
            $this->setParameter($name, $value);
        }
        return $this;
    }

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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setActionSelector()
     */
    public function setActionSelector($selectorOrString): TaskInterface
    {
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
        if (is_null($this->object)){
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
     * 
     * {@inheritDoc}
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
    public function hasMetaObject(): bool
    {
        return is_null($this->objectSelector) && is_null($this->object) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::isTriggeredByWidget()
     */
    public function isTriggeredByWidget(): bool
    {
        return is_null($this->originWigetId) ? false : true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::isTriggeredOnPage()
     */
    public function isTriggeredOnPage(): bool
    {
        return is_null($this->originPageSelctor) ? false : true;
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setPageSelector()
     */
    public function setPageSelector($selectorOrString): TaskInterface
    {
        if ($selectorOrString instanceof UiPageSelectorInterface) {
            $this->originPageSelctor = $selectorOrString;
        } else {
            $this->originPageSelctor = new UiPageSelector($this->getWorkbench(), $selectorOrString);
        }
        $this->originPage = null;
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
        }
        return $this->originPage;
    }

}