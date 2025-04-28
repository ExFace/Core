<?php

namespace exface\Core\Interfaces\Events;

/**
 * Interface for events that are triggered just before commiting a CRUD (Create, Read, Update, Delete) operation.
 */
interface CrudPerformedEventInterface extends DataSheetEventInterface
{
    /**
     * Get the number of rows affected by the source operation.
     * 
     * Returns NULL if the number of affected rows is unknown.
     * 
     * @return int|null
     */
    function getAffectedRowsCount() : ?int;
}