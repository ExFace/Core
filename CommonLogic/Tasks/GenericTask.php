<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\DataSheetFactory;

/**
 * Generic task implementation to create task programmatically.
 * 
 * @author Andrej Kabachnik
 *
 */
class GenericTask implements TaskInterface
{
    private $template = null;
    
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
    
    /**
     * 
     * @param TemplateInterface $template
     */
    public function __construct(TemplateInterface $template)
    {
        $this->template = $template;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getTemplate()
     */
    public function getTemplate() : TemplateInterface
    {
        return $this->template;
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
    public function setActionSelector(ActionSelectorInterface $selector): TaskInterface
    {
        $this->actionSelector = $selector;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->template->getWorkbench();
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
                case $this->hasOriginWidget():
                    $this->inputData = DataSheetFactory::createFromObject($this->getOriginWidget()->getMetaObject());
                    break;
                case $this->hasMetaObject():
                    $this->inputData = DataSheetFactory::createFromObject($this->getMetaObject());
                    break;
                case $this->hasOriginPage():
                    $this->inputData = DataSheetFactory::createFromObject($this->getOriginWidget()->getMetaObject());
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
            } elseif ($this->hasOriginWidget()) {
                $this->object = $this->getOriginWidget()->getMetaObject();
            } elseif ($this->hasOriginPage()) {
                $this->object = $this->getOriginWidget()->getMetaObject();
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
        return is_null($this->inputData) || $this->inputData->isBlank() ? false : true;
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
    public function setMetaObjectSelector(MetaObjectSelectorInterface $selector): TaskInterface
    {
        $this->objectSelector = $selector;
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
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::hasOriginWidget()
     */
    public function hasOriginWidget(): bool
    {
        return is_null($this->originWigetId) ? false : true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::hasOriginPage()
     */
    public function hasOriginPage(): bool
    {
        return is_null($this->originPageSelctor) ? false : true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getOriginWidgetId()
     */
    public function getOriginWidgetId()
    {
        return $this->originWigetId;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setOriginWidgetId()
     */
    public function setOriginWidgetId($string) : TaskInterface
    {
        $this->originWigetId = $string;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getOriginWidget()
     */
    public function getOriginWidget(): WidgetInterface
    {
        $page = $this->getWorkbench()->ui()->getPage($this->getOriginPageSelector());
        if (is_null($this->originWigetId)) {
            return $page->getWidgetRoot();
        } else {
            return $page->getWidget($this->getOriginWidgetId());
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::getOriginPageSelector()
     */
    public function getOriginPageSelector(): UiPageSelectorInterface
    {
        return $this->originPageSelctor;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskInterface::setOriginPageSelector()
     */
    public function setOriginPageSelector(UiPageSelectorInterface $selector): TaskInterface
    {
        $this->originPageSelctor = $selector;
        return $this;
    }

}