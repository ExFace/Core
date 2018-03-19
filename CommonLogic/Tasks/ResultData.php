<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Generic data result implementation - typical for actions like the core's ReadData, SaveData, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultData extends ResultMessage implements ResultDataInterface
{
    private $dataSheet = null;
        
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::getData()
     */
    public function getData(): DataSheetInterface
    {
        if (is_null($this->dataSheet)) {
            $this->dataSheet = $this->getTask()->getInputData();
        }
        return $this->dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultDataInterface::setData()
     */
    public function setData(DataSheetInterface $dataSheet): ResultDataInterface
    {
        $this->dataSheet = $dataSheet;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultDataInterface::hasData()
     */
    public function hasData(): bool
    {
        return is_null($this->dataSheet) ? false : true;
    }
    
    public function isEmpty() : bool
    {
        return parent::isEmpty() && ! $this->hasData();
    }
}