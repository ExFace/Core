<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface;

/**
 * A group of regular columns to be transposed relatively to a single "headers column"
 * 
 * @author Andrej Kabachnik
 *
 */
class PivotColumnGroup implements PivotColumnGroupInterface
{
    private $headersCol = null;
    
    private $valuesCols = [];
    
    /**
     * 
     * @param DataColumnInterface $headersColumn
     */
    public function __construct(DataColumnInterface $headersColumn)
    {
        $this->headersCol = $headersColumn;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface::getColumnWithHeaders()
     */
    public function getColumnWithHeaders() : DataColumnInterface
    {
        return $this->headersCol;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface::getDataSheet()
     */
    public function getDataSheet() : DataSheetInterface
    {
        return $this->getColumnWithHeaders()->getDataSheet();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface::addColumnWithValues()
     */
    public function addColumnWithValues(DataColumnInterface $column) : PivotColumnGroupInterface
    {
        $this->valuesCols[] = $column;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface::getColumnsWithValues()
     */
    public function getColumnsWithValues() : array
    {
        return $this->valuesCols;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface::hasColumnWithValues()
     */
    public function hasColumnWithValues(DataColumnInterface $col) : bool
    {
        return in_array($col, $this->valuesCols, true);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\PivotColumnGroupInterface::countColumnsWithValues()
     */
    public function countColumnsWithValues() : int
    {
        return count($this->valuesCols);
    }
    
    /**
     * 
     * @param string $colName
     * @return DataColumnInterface|NULL
     */
    public function getColumnByName(string $colName) : ?DataColumnInterface
    {
        foreach ($this->valuesCols as $col) {
            if ($col->getName() === $colName) {
                return $col;
            }
        }
        return null;
    }
    
    /**
     * 
     * @param DataColumnInterface $col
     * @throws UnexpectedValueException
     * @return int
     */
    public function getColumnIndex(DataColumnInterface $col) : int
    {
        $idx = array_search($col, $this->valuesCols);
        if ($idx === false) {
            throw new UnexpectedValueException('Pivot values column "' . $col->getName() . '" not found!');
        }
        return $idx;
    }
}