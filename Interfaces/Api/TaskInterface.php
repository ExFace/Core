<?php
namespace exface\Core\Interfaces\Api;

use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

interface TaskInterface extends ExfaceClassInterface
{
    public function __construct(TemplateInterface $template);
    
    public function getActionSelector();
    
    public function setActionSelector(ActionSelectorInterface $selector) : TaskInterface;
    
    /**
     * @return DataSheetInterface|null
     */
    public function getPrefillData();
    
    public function setPrefillData(DataSheetInterface $dataSheet) : TaskInterface;
    
    /**
     * @return DataSheetInterface
     */
    public function getInputData() : DataSheetInterface;
    
    public function setInputData(DataSheetInterface $dataSheet) : TaskInterface;
    
    public function getParameter($name);
    
    public function setParameter($name, $value) : TaskInterface;
    
    public function getTempalte() : TemplateInterface;
    
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
}