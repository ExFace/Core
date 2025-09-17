<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\CrudPerformedEventInterface;

/**
 * Event fired once a data sheet finished updating its data in the corresponding data sources (but before the transaction is committed!).
 * 
 * @event exface.Core.DataSheet.OnUpdateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnUpdateDataEvent 
    extends AbstractDataSheetEvent
    implements CrudPerformedEventInterface
{
    protected ?int $affectedRowsCount = null;

    public function __construct(
        DataSheetInterface $dataSheet, 
        DataTransactionInterface $transaction,
        $affectedRowsCount = null
    )
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