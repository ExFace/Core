<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\CrudPerformedEventInterface;

/**
 * Event fired after a data sheet finished reading its data from the corresponding data sources.
 * 
 * @event exface.Core.DataSheet.OnReadData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnReadDataEvent 
    extends OnBeforeReadDataEvent
    implements CrudPerformedEventInterface
{
    protected ?int $affectedRowsCount = null;

    public function __construct(
        DataSheetInterface $dataSheet, 
        int $limit = null, 
        int $offset = 0,
        int $affectedRowsCount = null
    )
    {
        $this->affectedRowsCount = $affectedRowsCount;
        parent::__construct($dataSheet, $limit, $offset);
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