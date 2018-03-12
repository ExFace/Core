<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface ResultDataInterface extends ResultInterface
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::getData()
     */
    public function getData(): DataSheetInterface;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return ResultDataInterface
     */
    public function setData(DataSheetInterface $dataSheet) : ResultDataInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasData() : bool;
}