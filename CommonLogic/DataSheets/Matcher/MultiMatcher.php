<?php
namespace exface\Core\CommonLogic\DataSheets\Matcher;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataMatcherInterface;

/**
 * Container to combine multiple matchers of for a single main scheet - e.g. to find matches in different compare-sheets
 * 
 * @author Andrej Kabachnik
 *
 */
class MultiMatcher implements DataMatcherInterface
{
    private $mainSheet = null;
    
    private $name = null;
    
    private $matchers = null;
    
    public function __construct(DataSheetInterface $mainSheet, string $name = null)
    {
        $this->mainSheet = $mainSheet;
        $this->name = $name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getMainDataSheet()
     */
    public function getMainDataSheet() : DataSheetInterface
    {
        return $this->mainSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getName()
     */
    public function getName() : ?string
    {
        return $this->name;
    }
    
    protected function getInnerMatchers() : array
    {
        return $this->matchers;
    }
    
    public function addMatcher(DataMatcherInterface $innerMatcher) : DataMatcherInterface
    {
        $this->matchers[] = $innerMatcher;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::hasMatches()
     */
    public function hasMatches() : bool
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->hasMatches() === true) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * @return DataRowMatch[]
     */
    public function getMatches(string $matcherName = null) : array
    {
        $matches = [];
        foreach ($this->matchers as $matcher) {
            if ($matcherName !== null && $matcherName !== $matcher->getName()) {
                continue;
            }
            foreach ($matcher->getMatches() as $match) {
                $matches[] = $match;
            }
        }
        return $matches;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getMatchesForRow()
     */
    public function getMatchesForRow(int $mainSheetRowIdx, string $matcherName = null) : array
    {
        $matches = [];
        foreach ($this->matchers as $matcher) {
            if ($matcherName !== null && $matcherName !== $matcher->getName()) {
                continue;
            }
            foreach ($matcher->getMatchesForRow($mainSheetRowIdx) as $match) {
                $matches[] = $match;
            }
        }
        return $matches;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getMatchedRowIndexes()
     */
    public function getMatchedRowIndexes(): array
    {
        $idxs = [];
        foreach ($this->matchers as $matcher) {
            $idxs = array_merge($idxs, $matcher->getMatchedRowIndexes());
        }
        $idxs = array_unique($idxs);
        sort($idxs, SORT_NUMERIC);
        return $idxs;
    }

}