<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Events\DataTransactionEventInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractDataSheetEvent extends AbstractEvent implements DataSheetEventInterface, DataTransactionEventInterface
{
    private $dataSheet = null;
    
    private $transaction = null;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     */
    public function __construct(DataSheetInterface $dataSheet, DataTransactionInterface $transaction)
    {
        $this->dataSheet = $dataSheet;
        $this->transaction = $transaction;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataSheetEventInterface::getDataSheet()
     */
    public function getDataSheet() : DataSheetInterface
    {
        return $this->dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataTransactionEventInterface::getTransaction()
     */
    public function getTransaction() : DataTransactionInterface
    {
        return $this->transaction;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->dataSheet->getWorkbench();
    }
}