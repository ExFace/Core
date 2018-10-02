<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataPointerInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Default implementation of a data pointer.
 * 
 * @see DataPointerInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataPointer implements DataPointerInterface
{
    private $dataSheet = null;
    
    private $dataColumn = null;
    
    private $rowNumber = null;
    
    /**
     * @deprecated use DataPointerFactory instead
     * 
     * @param DataSheetInterface $dataSheet
     * @param string $columnName
     * @param int $rowNumber
     */
    public function __construct(DataSheetInterface $dataSheet, string $columnName = null, int $rowNumber = null)
    {
        $this->dataSheet = $dataSheet;
        if ($columnName !== null) {
            $this->dataColumn = $dataSheet->getColumns()->get($columnName);
        }
        $this->rowNumber = $rowNumber;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::getRowNumber()
     */
    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::getValue()
     */
    public function getValue()
    {
        if ($this->isCell()) {
            return $this->getColumn()->getCellValue($this->getRowNumber());
        }
        
        if ($this->isRow()) {
            return $this->getDataSheet()->getRow($this->getRowNumber());
        }
        
        if ($this->isColumn()) {
            return $this->getColumn()->getValues(false);
        }
        
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::isEmpty()
     */
    public function isEmpty(): bool
    {
        return $this->dataColumn === null && $this->rowNumber === null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::getDataSheet()
     */
    public function getDataSheet(): DataSheetInterface
    {
        return $this->dataSheet;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::getColumn()
     */
    public function getColumn(): DataColumnInterface
    {
        return $this->dataColumn;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getDataSheet()->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::isColumn()
     */
    public function isColumn(): bool
    {
        return $this->dataColumn !== null && $this->rowNumber === null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::isCell()
     */
    public function isCell(): bool
    {
        return $this->dataColumn !== null && $this->rowNumber !== null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::isRow()
     */
    public function isRow(): bool
    {
        return $this->dataColumn === null && $this->rowNumber !== null;
    }
}