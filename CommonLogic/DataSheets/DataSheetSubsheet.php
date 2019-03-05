<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

/**
 * This is the default implementation of the DataSheetSubsheetInterface.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetSubsheet extends DataSheet implements DataSheetSubsheetInterface
{

    private $parentSheet = null;

    private $joinKeyAliasOfSubsheet = null;
    
    private $joinKeyAliasOfParentSheet = null;
    
    private $relationPathFromParentSheet = null;
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param DataSheetInterface $parentSheet
     * @param string $joinKeyAliasOfSubsheet
     * @param string $joinKeyAliasOfParentSheet
     * @param MetaRelationPathInterface $relationPathFromParentSheet
     */
    public function __construct(
        MetaObjectInterface $object, 
        DataSheetInterface $parentSheet, 
        string $joinKeyAliasOfSubsheet, 
        string $joinKeyAliasOfParentSheet,
        MetaRelationPathInterface $relationPathFromParentSheet = null
    )
    {
        parent::__construct($object);
        $this->setParentSheet($parentSheet);
        $this->setJoinKeyAliasOfParentSheet($joinKeyAliasOfParentSheet);
        $this->setJoinKeyAliasOfSubsheet($joinKeyAliasOfSubsheet);
        if ($relationPathFromParentSheet !== null) {
            $this->setRelationPathFromParentSheet($relationPathFromParentSheet);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface::getParentSheet()
     */
    public function getParentSheet() : DataSheetInterface
    {
        return $this->parent_sheet;
    }

    /**
     * 
     * @param DataSheetInterface $sheet
     * @return DataSheetSubsheetInterface
     */
    protected function setParentSheet(DataSheetInterface $sheet) : DataSheetSubsheetInterface
    {
        $this->parent_sheet = $sheet;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface::getJoinKeyAliasOfSubsheet()
     */
    public function getJoinKeyAliasOfSubsheet() : string
    {
        return $this->joinKeyAliasOfSubsheet;
    }

    /**
     * 
     * @param string $value
     * @return DataSheetSubsheetInterface
     */
    protected function setJoinKeyAliasOfSubsheet(string $value) : DataSheetSubsheetInterface
    {
        $this->joinKeyAliasOfSubsheet = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface::getJoinKeyAliasOfParentSheet()
     */
    public function getJoinKeyAliasOfParentSheet() : string
    {
        return $this->joinKeyAliasOfParentSheet;
    }
    
    /**
     * 
     * @param string $value
     * @return DataSheetSubsheetInterface
     */
    protected function setJoinKeyAliasOfParentSheet(string $value) : DataSheetSubsheetInterface
    {
        $this->joinKeyAliasOfParentSheet = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface::getJoinKeyColumnOfParentSheet()
     */
    public function getJoinKeyColumnOfParentSheet(): DataColumnInterface
    {
        $col = $this->getParentSheet()->getColumns()->getByExpression($this->getJoinKeyAliasOfParentSheet());
        if (! $col) {
            throw new DataSheetColumnNotFoundError($this->getParentSheet(), 'Key column "' . $this->getJoinKeyAliasOfParentSheet() . '" to join subsheet with "' . $this->getMetaObject()->getName() . '" (' . $this->getMetaObject()->getAliasWithNamespace() . ') to parent data sheet with "' . $this->getParentSheet()->getMetaObject()->getName() . '" (' . $this->getParentSheet()->getMetaObject()->getAliasWithNamespace() . ') not found in parent data sheet!');
        }
        return $col;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface::getJoinKeyColumnOfSubsheet()
     */
    public function getJoinKeyColumnOfSubsheet(): DataColumnInterface
    {
        $col = $this->getColumns()->getByExpression($this->getJoinKeyAliasOfSubsheet());
        if (! $col) {
            throw new DataSheetColumnNotFoundError($this, 'Key column "' . $this->getJoinKeyAliasOfSubsheet() . '" to join subsheet with "' . $this->getMetaObject()->getName() . '" (' . $this->getMetaObject()->getAliasWithNamespace() . ') to parent data sheet with "' . $this->getParentSheet()->getMetaObject()->getName() . '" (' . $this->getParentSheet()->getMetaObject()->getAliasWithNamespace() . ') not found in subsheet!');
        }
        return $col;
    }

    /**
     *
     * @return MetaRelationPathInterface|NULL
     */
    public function getRelationPathFromParentSheet() : ?MetaRelationPathInterface
    {
        return $this->relationPathFromParentSheet;
    }
    
    /**
     * 
     * @param MetaRelationPathInterface $value
     * @return DataSheetSubsheet
     */
    protected function setRelationPathFromParentSheet(MetaRelationPathInterface $value) : DataSheetSubsheet
    {
        $this->relationPathFromParentSheet = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface::hasRelationToParent()
     */
    public function hasRelationToParent() : bool
    {
        return $this->getRelationPathFromParentSheet() !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetSubsheetInterface::getRelationPathToParentSheet()
     */
    public function getRelationPathToParentSheet() : ?MetaRelationPathInterface
    {
        if ($this->hasRelationToParent() === true) {
            return $this->getRelationPathFromParentSheet()->reverse();
        }
        return null;
    }
}