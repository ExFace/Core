<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

/**
 * Event fired before a data sheet starts updating it's data in the corresponding data sources.
 * 
 * Use `$event->preventUpdate()` to disable the general create logic of the data sheet: i.e.
 * the UPDATE-query to the data source(s).
 * 
 * @event exface.Core.DataSheet.OnBeforeUpdateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeUpdateDataEvent extends AbstractDataSheetEvent
{
    private $preventUpdate = false;
    
    private $createIfUidNotFound = false;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param DataTransactionInterface $transaction
     * @param bool $updateIfUidExists
     */
    public function __construct(DataSheetInterface $dataSheet, DataTransactionInterface $transaction, bool $createIfUidNotFound = false)
    {
        parent::__construct($dataSheet, $transaction);
        $this->createIfUidNotFound = $createIfUidNotFound;
    }
    
    /**
     * Prevents the default update operation.
     *
     * Use this if the event handler fills the data sheet.
     *
     * @return OnBeforeUpdateDataEvent
     */
    public function preventUpdate() : OnBeforeUpdateDataEvent
    {
        $this->preventUpdate = true;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isPreventUpdate() : bool
    {
        return $this->preventUpdate;
    }
    
    /**
     * Returns the value of the create-if-not-exists-flag used in the operation, that fired the event.
     * 
     * @return bool
     */
    public function getCreateIfUidNotFound() : bool
    {
        return $this->createIfUidNotFound;
    }
}