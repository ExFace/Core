<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * A special input widget to quickly select tags, categories and other tag-like mappings
 * 
 * @see InputCombo
 *
 * @author Andrej Kabachnik
 */
class InputTags extends InputCombo
{
    private $tagData = null;
    
    protected function init()
    {
        parent::init();
        $this->setMultiSelect(true);
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getTagData() : DataSheetInterface
    {
        if ($this->tagData === null) {
            $this->tagData = $this->getOptionsDataSheet();
            $this->tagData->getColumns()->addFromSystemAttributes();
            $this->tagData->dataRead();
        }
        return $this->tagData;
    }
    
    /**
     * Returns all available tags as an array with values for keys and tag texts for values
     * 
     * @return array
     */
    public function getTagsAvailable() : array
    {
        $tagSheet = $this->getTagData();
        $valCol = $tagSheet->getColumns()->getByAttribute($this->getValueAttribute());
        $valColName = $valCol->getName();
        $textCol = $tagSheet->getColumns()->getByAttribute($this->getTextAttribute());
        $textColName = $textCol->getName();
        $tags = [];
        foreach ($this->getTagData()->getRows() as $row) {
            $tags[$row[$valColName]] = $row[$textColName];
        }
        return $tags;
    }
}