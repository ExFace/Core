<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\CrudPerformedEventInterface;

/**
 * Event fired after a data sheet finished creating its data in the corresponding data sources (but before the transaction is committed!).
 * 
 * @event exface.Core.DataSheet.OnCreateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnCreateDataEvent 
    extends AbstractDataSheetEvent 
    implements CrudPerformedEventInterface 
{
    protected ?int $affectedRowsCount = null;

    public function __construct(
        DataSheetInterface $dataSheet, 
        DataTransactionInterface $transaction,
        int $affectedRowsCount = null)
    {
        $this->affectedRowsCount = $affectedRowsCount;
        parent::__construct($dataSheet, $transaction);
    }

    /**
     * @inheritdoc
     * @see CrudPerformedEventInterface::getAffectedRowsCount()
     */
    function getAffectedRowsCount(): ?int
    {
        return $this->affectedRowsCount;
    }
}