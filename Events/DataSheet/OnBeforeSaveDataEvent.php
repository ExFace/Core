<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\DataTypes\DataSheetDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\DataChangeEventInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

/**
 * Event fired right after a data operation (create, update or delete) was started.
 *  
 * @event exface.Core.DataSheet.OnBeforeSaveData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeSaveDataEvent extends AbstractDataSheetEvent
{
    const OPERATION_CREATE = 'create';
    const OPERATION_UPDATE = 'update';
    const OPERATION_DELETE = 'delete';
    
    private $operation = false;

    /**
     *
     * @param DataSheetInterface $dataSheet
     * @param DataTransactionInterface $transaction
     * @param string $operation
     */
    public function __construct(DataSheetInterface $dataSheet, DataTransactionInterface $transaction, string $operation)
    {
        parent::__construct($dataSheet, $transaction);
        $this->operation = $operation;
    }
    
    /**
     * Returns the type of the current operation: create, update or delete.
     * 
     * @return string
     */
    public function getOperation() : string
    {
        return $this->operation;
    }
}