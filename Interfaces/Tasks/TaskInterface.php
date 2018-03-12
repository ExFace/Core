<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface TaskInterface extends ExfaceClassInterface
{    
    /**
     * 
     * @return ActionSelectorInterface
     */
    public function getActionSelector() : ActionSelectorInterface;
    
    /***
     * 
     * @param ActionSelectorInterface $selector
     * @return TaskInterface
     */
    public function setActionSelector(ActionSelectorInterface $selector) : TaskInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasAction() : bool;
    
    /**
     * Returns a copy of the prefill data sheet.
     * 
     * @return DataSheetInterface|null
     */
    public function getPrefillData();
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return TaskInterface
     */
    public function setPrefillData(DataSheetInterface $dataSheet) : TaskInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasPrefillData() : bool;
    
    /**
     * Returns a copy of the input data sheet.
     * 
     * @return DataSheetInterface
     */
    public function getInputData() : DataSheetInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasInputData() : bool;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return TaskInterface
     */
    public function setInputData(DataSheetInterface $dataSheet) : TaskInterface;
    
    /**
     * 
     * @param string $name
     */
    public function getParameter($name);
    
    /**
     * 
     * @param string $name
     * @param string $value
     * @return TaskInterface
     */
    public function setParameter($name, $value) : TaskInterface;
    
    /**
     * @return array
     */
    public function getParameters() : array;
    
    /**
     * 
     * @param string $name
     * @return bool
     */
    public function hasParameter($name) : bool;
    
    /**
     * 
     * @return TemplateInterface
     */
    public function getTemplate() : TemplateInterface;
    
    /**
     * 
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface;
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return TaskInterface
     */
    public function setMetaObject(MetaObjectInterface $object) : TaskInterface;
    
    /**
     * 
     * @param MetaObjectSelectorInterface $selector
     * @return TaskInterface
     */
    public function setMetaObjectSelector(MetaObjectSelectorInterface $selector) : TaskInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasMetaObject() : bool;
    
    /**
     * 
     * @return UiPageSelectorInterface
     */
    public function getPageSelector() : UiPageSelectorInterface;
    
    /**
     * 
     * @param UiPageSelectorInterface $selector
     * @return TaskInterface
     */
    public function setPageSelector(UiPageSelectorInterface $selector) : TaskInterface;
    
    /**
     * 
     * @see isTriggeredByWidget()
     * 
     * @param string $string
     * @return TaskInterface
     */
    public function setWidgetIdTriggeredBy($string) : TaskInterface;
    
    /**
     * 
     * @see isTriggeredByWidget()
     * 
     * @return WidgetInterface
     */
    public function getWidgetTriggeredBy() : WidgetInterface;
    
    /**
     * Returns TRUE if the task originates from a specific widget in a page and FALSE otherwise.
     * 
     * In contrast to isTriggeredOnPage() this method checks, if there is an explicit widget reference.
     * Having a page reference is enough for getWidgetTriggeredBy() to work, but you can still use
     * this method to check, if it was an explicitly referenced widget or the main widget of the
     * page was just assumed to be the right one.
     * 
     * @return bool
     */
    public function isTriggeredByWidget() : bool;
    
    
    /**
     * Returns TRUE if the task originates from a specific page and FALSE otherwise.
     * 
     * NOTE: This does not yet mean, that the origin widget is known. Use isTriggeredByWidget() to
     * find out if.
     * 
     * @return bool
     */
    public function isTriggeredOnPage() : bool;   
}