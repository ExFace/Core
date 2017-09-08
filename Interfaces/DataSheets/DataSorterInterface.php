<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Exceptions\InvalidArgumentException;

interface DataSorterInterface extends iCanBeConvertedToUxon, iCanBeCopied
{

    function __construct(DataSheetInterface $data_sheet);

    /**
     *
     * @return string
     */
    public function getAttributeAlias();

    /**
     *
     * @param string $value            
     * @return DataSorterInterface
     */
    public function setAttributeAlias($value);

    /**
     *
     * @return string
     */
    public function getDirection();

    /**
     *
     * @param string $value            
     * @throws InvalidArgumentException
     * @return DataSorterInterface
     */
    public function setDirection($value);

    /**
     *
     * @return DataSheetInterface
     */
    public function getDataSheet();

    /**
     *
     * @param DataSheetInterface $data_sheet            
     * @return DataSorterInterface
     */
    public function setDataSheet(DataSheetInterface $data_sheet);
}