<?php

namespace exface\Core\CommonLogic\DataSheets\Matcher;

use exface\Core\Interfaces\DataSheets\DataMatcherInterface;
use exface\Core\Interfaces\DataSheets\DataMatchInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\TwoSheetMatcherInterface;

/**
 * A simplified row matcher to be filled manually - e.g. to save information about matching rows of two data sheets
 * 
 * @author Andrej Kabachnik
 */
class DataRowMatchCollection implements TwoSheetMatcherInterface
{
    private DataSheetInterface $mainSheet;
    private DataSheetInterface $otherSheet;
    private ?string $name = null;
    
    /**
     * @var DataMatchInterface[]
     */
    private array $matches;

    public function __construct(DataSheetInterface $mainSheet, DataSheetInterface $otherSheet, array $matches = [], string $name = null)
    {
        $this->mainSheet = $mainSheet;
        $this->otherSheet = $otherSheet;
        $this->name = $name;
        $this->matches = $matches;
    }

    /**
     * @param DataMatchInterface $match
     * @return $this
     */
    public function addMatch(DataMatchInterface $match) : DataRowMatchCollection
    {
        $this->matches[] = $match;
        return $this;
    }

    /**
     * @param int $mainSheetRowIndex
     * @param int $otherSheetRowIndex
     * @return DataRowMatchCollection
     */
    public function addMatchingRows(int $mainSheetRowIndex, int $otherSheetRowIndex) : DataRowMatchCollection
    {
        return $this->addMatch(new DataRowMatch($this, $mainSheetRowIndex, $otherSheetRowIndex));
    }

    /**
     * @param DataMatchInterface $match
     * @return $this
     */
    public function removeMatch(DataMatchInterface $match) : DataRowMatchCollection
    {
        unset($this->matches[array_search($match, $this->matches)]);
        $this->matches = array_values($this->matches);
        return $this;
    }
    
    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMainDataSheet()
     */
    public function getMainDataSheet(): DataSheetInterface
    {
        return $this->mainSheet;
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getName()
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    
    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::hasMatches()
     */
    public function hasMatches(): bool
    {
        return ! empty($this->matches);
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMatches()
     */
    public function getMatches(string $matcherName = null): array
    {
        return $this->matches;
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMatchesForRow()
     */
    public function getMatchesForRow(int $mainSheetRowIdx): array
    {
        $matches = [];
        foreach ($this->matches as $match) {
            if ($match->getMainSheetPointer()->getRowNumber() === $mainSheetRowIdx) {
                $matches[] = $match;
            }
        }
        return $matches;
    }

    /**
     * {@inheritDoc}
     * @see DataMatcherInterface::getMatchedRowIndexes()
     */
    public function getMatchedRowIndexes(): array
    {
        $indexes = [];
        foreach ($this->matches as $match) {
            $indexes[] = $match->getMainSheetIndex();
        }
        return array_unique($indexes);
    }

    /**
     * @inheritDoc
     */
    public function getCompareDataSheet(): DataSheetInterface
    {
        return $this->otherSheet;
    }
}