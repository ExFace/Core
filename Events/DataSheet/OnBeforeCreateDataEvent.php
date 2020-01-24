<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

/**
 * Event fired before a data sheet starts creating it's data in the corresponding data sources.
 * 
 * Use `$event->preventCreate()` to disable the general create logic of the data sheet: i.e.
 * the CREATE-query to the data source(s).
 * 
 * @event exface.Core.DataSheet.OnBeforeCreateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeCreateDataEvent extends AbstractDataSheetEvent
{
    private $preventCreate = false;
    
    private $updateIfUidExists = null;
    
    /**
     *
     * @param DataSheetInterface $dataSheet
     */
    public function __construct(DataSheetInterface $dataSheet, DataTransactionInterface $transaction, bool $updateIfUidExists = true)
    {
        parent::__construct($dataSheet, $transaction);
        $this->updateIfUidExists = $updateIfUidExists;
    }
    
    /**
     * 
     * @return bool
     */
    public function getUpdateIfUidExists() : bool
    {
        return $this->updateIfUidExists;
    }
    
    /**
     * Prevents the default create operation.
     *
     * Use this if the event handler fills the data sheet.
     *
     * @return OnBeforeCreateDataEvent
     */
    public function preventCreate() : OnBeforeCreateDataEvent
    {
        $this->preventCreate = true;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isPreventCreate() : bool
    {
        return $this->preventCreate;
    }
}