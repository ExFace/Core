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
    /**
     * 
     * @var DataSheetInterface|NULL
     */
    private $dataSheet = null;
    
    /**
     * 
     * @var DataColumnInterface|NULL
     */
    private $dataColumn = null;
    
    /**
     * 
     * @var int|NULL
     */
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
    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::getValue()
     */
    public function getValue(bool $splitLists = false, string $splitDelimiter = null, bool $onlyUnique = false)
    {
        $val = null;
        
        if ($this->isCell()) {
            $val = $this->getColumn()->getCellValue($this->getRowNumber());
        }
        
        if ($this->isColumn()) {
            $val = $this->getColumn()->getValues(false);
        }
        
        
        if ($splitLists === true && is_string($val) && ($splitDelimiter !== null || $this->dataColumn !== null && $this->dataColumn->isAttribute())) {
            $splitDelimiter = $splitDelimiter ?? $this->dataColumn->getAttribute()->getValueListDelimiter();
            if (stripos($val, $splitDelimiter) !== false) {
                $val = explode($splitDelimiter, $val);
            }
        }
        
        if ($onlyUnique === true && is_array($val)) {
            $val = array_unique($val);
            if (count($val) === 1) {
                $val = reset($val);
            }
        }
        
        if ($this->isRow()) {
            $val = $this->getDataSheet()->getRow($this->getRowNumber());
        }
        
        return $val;
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
    public function getColumn(): ?DataColumnInterface
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::hasValue()
     */
    public function hasValue() : bool
    {
        if ($this->isEmpty() === true) {
            return false;
        }
        $val = $this->getValue();
        return $val !== null && $val !== ''; 
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataPointerInterface::hasMultipleValues()
     */
    public function hasMultipleValues() : bool
    {
        if ($this->isEmpty() === true) {
            return false;
        }
        $val = $this->getValue();
        if (is_array($val)) {
            return true;
        }
        if ($this->dataColumn !== null && $this->dataColumn->isAttribute()) {
            $delim = $this->dataColumn->getAttribute()->getValueListDelimiter();
            if (stripos($val, $delim) !== false) {
                return true;
            }
        }
        return false;
    }
}