<?php
namespace exface\Core\Interfaces\Tasks;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface TaskResultDataInterface extends TaskResultInterface
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::getData()
     */
    public function getData(): DataSheetInterface;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return TaskResultDataInterface
     */
    public function setData(DataSheetInterface $dataSheet) : TaskResultDataInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasData() : bool;
}