<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;

class DataSheetSubsheet extends DataSheet implements DataSheetSubsheetInterface
{

    private $parentSheet = null;

    private $joinKeyAliasOfSubsheet = null;
    private $joinKeyAliasOfParentSheet = null;
    
    public function __construct(MetaObjectInterface $object, DataSheetInterface $parentSheet, string $joinKeyAliasOfSubsheet, string $joinKeyAliasOfParentSheet)
    {
        parent::__construct($object);
        $this->setParentSheet($parentSheet);
        $this->setJoinKeyAliasOfParentSheet($joinKeyAliasOfParentSheet);
        $this->setJoinKeyAliasOfSubsheet($joinKeyAliasOfSubsheet);
        
    }

    public function getParentSheet() : DataSheetInterface
    {
        return $this->parent_sheet;
    }

    protected function setParentSheet(DataSheetInterface $sheet) : DataSheetSubsheetInterface
    {
        $this->parent_sheet = $sheet;
        return $this;
    }

    public function getJoinKeyAliasOfSubsheet() : string
    {
        return $this->joinKeyAliasOfSubsheet;
    }

    protected function setJoinKeyAliasOfSubsheet(string $value) : DataSheetSubsheetInterface
    {
        $this->joinKeyAliasOfSubsheet = $value;
        return $this;
    }
    
    public function getJoinKeyAliasOfParentSheet() : string
    {
        return $this->joinKeyAliasOfParentSheet;
    }
    
    protected function setJoinKeyAliasOfParentSheet(string $value) : DataSheetSubsheetInterface
    {
        $this->joinKeyAliasOfParentSheet = $value;
        return $this;
    }
    
    public function getJoinKeyColumnOfParentSheet(): DataColumnInterface
    {
        $col = $this->getParentSheet()->getColumns()->getByExpression($this->getJoinKeyAliasOfParentSheet());
        if (! $col) {
            throw new DataSheetColumnNotFoundError($this->getParentSheet(), 'Key column "' . $this->getJoinKeyAliasOfParentSheet() . '" to join subsheet with "' . $this->getMetaObject()->getName() . '" (' . $this->getMetaObject()->getAliasWithNamespace() . ') to parent data sheet with "' . $this->getParentSheet()->getMetaObject()->getName() . '" (' . $this->getParentSheet()->getMetaObject()->getAliasWithNamespace() . ') not found in parent data sheet!');
        }
        return $col;
    }

    
    public function getJoinKeyColumnOfSubsheet(): DataColumnInterface
    {
        $col = $this->getColumns()->getByExpression($this->getJoinKeyAliasOfParentSheet());
        if (! $col) {
            throw new DataSheetColumnNotFoundError($this, 'Key column "' . $this->getJoinKeyAliasOfParentSheet() . '" to join subsheet with "' . $this->getMetaObject()->getName() . '" (' . $this->getMetaObject()->getAliasWithNamespace() . ') to parent data sheet with "' . $this->getParentSheet()->getMetaObject()->getName() . '" (' . $this->getParentSheet()->getMetaObject()->getAliasWithNamespace() . ') not found in parent data sheet!');
        }
        return $col;
    }

}

?>