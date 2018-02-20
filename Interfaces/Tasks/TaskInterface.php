<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\Templates\TemplateInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\WidgetInterface;

interface TaskInterface extends ExfaceClassInterface
{
    /**
     * 
     * @param TemplateInterface $template
     */
    public function __construct(TemplateInterface $template);
    
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
     * Returns the data transaction, the action runs in.
     * Most action should run in a single transactions, so it is a good
     * practice to use the action's transaction for all data source operations. If not transaction was set explicitly
     * via set_transaction(), a new transaction will be started automatically.
     *
     * @return DataTransactionInterface
     */
    public function getTransaction() : DataTransactionInterface;
    
    /**
     * Sets the main data transaction used in this action.
     *
     * @param DataTransactionInterface $transaction
     * @return TaskInterface
     */
    public function setTransaction(DataTransactionInterface $transaction) : TaskInterface;
    
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
    public function getOriginPageSelector() : UiPageSelectorInterface;
    
    /**
     * 
     * @param UiPageSelectorInterface $selector
     * @return TaskInterface
     */
    public function setOriginPageSelector(UiPageSelectorInterface $selector) : TaskInterface;
    
    /**
     * @return string
     */
    public function getOriginWidgetId();
    
    /**
     * 
     * @param string $string
     */
    public function setOriginWidgetId($string) : TaskInterface;
    
    /**
     * 
     * @return WidgetInterface
     */
    public function getOriginWidget() : WidgetInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasOriginWidget() : bool;
    
}