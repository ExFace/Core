<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * Interface for a simple matcher comparing two data sheets
 * 
 * @author Andrej Kabachnik
 *
 */
interface TwoSheetMatcherInterface extends DataMatcherInterface
{
    /**
     *
     * @return DataSheetInterface
     */
    public function getCompareDataSheet() : DataSheetInterface;
}