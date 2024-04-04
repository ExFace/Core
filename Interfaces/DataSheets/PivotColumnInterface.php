<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PivotColumnInterface extends DataColumnInterface
{
    /**
     * Returns the pivot column group where this column results from.
     * 
     * @return PivotColumnGroupInterface
     */
    public function getPivotColumnGroup() : PivotColumnGroupInterface;
}