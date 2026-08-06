<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

/**
 * Event fired before a data operation (create, update or delete) was started (EXPERIMENTAL!).
 * 
 * **WARNING:** this event is known to have side effects. Read these docs carefully. Prefer dedicated `OnBeforeCreate`, 
 * `OnBeforeUpdate` and `OnBeforeDelete` if you are unsure!
 * 
 * The events `OnBeforeSaveData` and `OnSaveData` "wrap" around dedicated data operation events `OnBeforeCreate`, 
 * `OnBeforeUpdate` and `OnBeforeDelete`: 
 * - `OnBeforeSaveData` is fired immediately before the dedicated events are - accept for one case: 
 * `DataSheet::dataUpdate()` will fire this event before separating rows into creates and updates. Thus, listening to 
 * this event will allow to get the original data passed to `dataSave()`. 
 * - `OnSaveData` is fired after the dedicated events are.
 * 
 * Thus, if a data sheet is saved, that has both new and existing rows, the order of events is:
 * 
 * 1. `OnBeforeSaveData` (with operation `update`) - all rows
 * 2. Only for rows to be created:
 *  1. `OnBeforeSaveData` (with operation `create`)
 *  3. `OnBeforeCreateData`
 *  4. `OnCreateData`
 *  5. `OnSaveData`
 * 3. Only for rows to be updated:
 *  1. `OnBeforeSaveData` (with operation `update`)
 *  2. `OnBeforeUpdateData`
 *  3. `OnUpdateData`
 *  4. `OnSaveData`
 * 4. `OnSaveData` - all rows
 * 
 * ## Controversy
 * 
 * Although the idea of listening ot any data modification on a single event seems very simple, this event has produced
 * a lot of side effects:
 * 
 * - Since it is fired earlier, all its listeners are called before listeners of the dedicated events - regardless of
 * any custom priorities. For example, if you have an `OrderingBehavior`, that requires results of a `CalculatingBehavior`
 * listening to `OnCreateData` and `OnUpdateData`, the calculated values will never be there because the `OrderingBehavior`
 * listens to `OnSaveData` and, thus, will always be called first.
 * - Being a separate class, it has different methods, than the dedicated events. In particular, it currently does not
 * provide access to previous data like `OnBeforeUpdate` does.
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