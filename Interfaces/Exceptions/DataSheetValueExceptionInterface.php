<?php
namespace exface\Core\Interfaces\Exceptions;

/**
 * Interface for errors affecting creaint data sheet cells only - e.g. validation, missing values, etc.
 * 
 * These errors are particularly important for users as they typically require corrections 
 * in the data. It is important to tell the user exactly, where the error happened!
 * 
 * On the other hand, these errors often originate from low-level checks like data type parsing,
 * where there is no context information. So these exceptions allow higher-level logic to access
 * more details about the error and to generate a well understandable user-error at a later
 * point in time.
 * 
 * @author Andrej Kabachnik
 */
Interface DataSheetValueExceptionInterface extends DataSheetExceptionInterface
{
    /**
     * Returns the translated message to show to users without information about the affected rows
     * 
     * @return string
     */
    public function getMessageTitleWithoutLocation() : string;

     /**
     * Returns the affected row indexes (starting with 0)
     * @return array|NULL
     */
    public function getRowIndexes() : ?array;
    
    /**
     * Returns the affected row numbers (starting with 1)
     * 
     * @return array|NULL
     */
    public function getRowNumbers() : ?array;
}