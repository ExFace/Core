<?php
namespace exface\Core\CommonLogic\DataSheets\Matcher;

use exface\Core\Interfaces\DataSheets\DataMatchInterface;
use exface\Core\Interfaces\DataSheets\DataPointerInterface;
use exface\Core\CommonLogic\DataSheets\DataPointer;
use exface\Core\Interfaces\DataSheets\DataMatcherInterface;

class DataRowMatch implements DataMatchInterface
{
    private $matcher = null;
    
    private $mainRowIdx = null;
    
    private $matchedRowIdx = null;
    
    private $isUidMatch = false;
    
    private $mainPointer = null;
    
    private $matchPointer = null;
    
    public function __construct(DataRowMatcher $matcher, int $mainRowIdx, int $matchedRowIdx, bool $isUidMatch = false)
    {
        $this->matcher = $matcher;
        $this->mainRowIdx = $mainRowIdx;
        $this->matchedRowIdx = $matchedRowIdx;
        $this->isUidMatch = $isUidMatch;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatchInterface::isUidMatch()
     */
    public function isUidMatch() : bool
    {
        return $this->isUidMatch;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatchInterface::getMatcher()
     */
    public function getMatcher() : DataMatcherInterface
    {
        return $this->matcher;
    }
    
    public function getMainSheetRowIndex() : int
    {
        return $this->mainRowIdx;
    }
    
    public function getMatchedRowIndex() : int
    {
        return $this->matchedRowIdx;
    }
    
    public function getMainRow() : array
    {
        return $this->getMatcher()->getMainDataSheet()->getRow($this->getMainSheetRowIndex());
    }
    
    public function getMatchedRow() : array
    {
        return $this->getMatcher()->getCompareDataSheet()->getRow($this->getMatchedRowIndex());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatchInterface::getMatchedPointer()
     */
    public function getMatchedPointer(): DataPointerInterface
    {
        if ($this->mainPointer === null) {
            $this->mainPointer = new DataPointer($this->matcher->getMainDataSheet(), null, $this->mainRowIdx);
        }
        return $this->mainPointer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatchInterface::getMainSheetPointer()
     */
    public function getMainSheetPointer(): DataPointerInterface
    {
        if ($this->matchPointer === null) {
            $this->matchPointer = new DataPointer($this->matcher->getCompareDataSheet(), null, $this->matchedRowIdx);
        }
        return $this->matchPointer;
    }
    
    public function getUid()
    {
        $matchSheet = $this->matcher->getCompareDataSheet();
        if ($matchSheet->hasUidColumn()) {
            return $matchSheet->getUidColumn()->getValue($this->getMatchedRowIndex());
        } 
        return null;
    }

    public function hasUid() : bool
    {
        return $this->getUid() !== null;
    }
}