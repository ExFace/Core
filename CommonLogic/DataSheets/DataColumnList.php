<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Interfaces\DataSheets\DataColumnListInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\Model\MetaAttributeListInterface;

/**
 *
 * @method DataColumnInterface[] getAll()
 * @method DataColumnInterface get(string $key)
 * @method DataColumnInterface getFirst()
 * @method DataColumnInterface getLast()
 *        
 * @author Andrej Kabachnik
 *        
 */
class DataColumnList extends EntityList implements DataColumnListInterface
{
    private $columnsExpressionsCache = null;

    /**
     * Adds a data sheet
     *
     * @param DataColumn $column            
     * @param mixed $key            
     * @param boolean $overwrite_values            
     * @return DataColumnListInterface
     */
    public function add($column, $key = null, $overwrite_values = true)
    {
        if (! ($column instanceof DataColumn)) {
            throw new InvalidArgumentException('Cannot add column to data sheet: only DataColumns can be added to the column list of a datasheet, "' . get_class($column) . '" given instead!');
        }
        
        $key = $key === null && $column->getName() ? $column->getName() : $key;
        $data_sheet = $this->getDataSheet();
        $existingColumn = $this->get($key);
        if (! $existingColumn || $existingColumn->getExpressionObj()->toString() !== $column->getExpressionObj()->toString()) {
            $this->columnsExpressionsCache = null;
            if ($column->getDataSheet() !== $data_sheet) {
                $column_original = $column;
                $column = $column_original->copy();
                $column->setDataSheet($data_sheet);
                
                // Add the column BEFORE copying values, as the latter will auto-add the column to the sheet
                $result = parent::add($column, $key);
                
                // If the original column had values, use them to overwrite the values in the newly added column
                if ($overwrite_values && $column_original->isFresh()) {
                    $data_sheet->setColumnValues($column->getName(), $column->getValues());
                }
            } else {
                $result = parent::add($column, $key);
            }
            
            unset($existingColumn);
            
            // Mark the data as outdated if new columns are added because the values for these columns should be fetched now
            // Actually we do not need to mark static columns not fresh - we could recalculate them right away without
            // querying the data source.
            switch (true) {
                // If we are adding a static column, we calculate all values on its rows and concider it
                // "fresh" because no data source refresh is needed - a recalculation is enough.
                case $column->isStatic():
                    $column->setValuesByExpression($column->getExpressionObj(), true);
                    break;
                // If we are adding a column, that is bound to an attribute, but also has a static formula, we need 
                // to calculate the values for this column from the formula. We can still concider it "fresh" because
                // no data source read is needed for the static formula- a recalculation is enough.
                case $column->isCalculated() && $column->getFormula()->isStatic():
                    $column->setValuesByExpression($column->getFormula(), true);
                    break;
                default:
                    $column->setFresh(false);
            }
            
            return $this;            
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::addMultiple()
     */
    public function addMultiple(array $columns, MetaRelationPathInterface $relationPath = null) : DataColumnListInterface
    {
        $relPathString = $relationPath === null ? '' : $relationPath->toString();
        foreach ($columns as $col) {
            if ($col instanceof DataColumn) {
                $col_name = $relPathString ? RelationPath::join($relPathString, $col->getName()) : $col->getName();
                // If there is no such column alread, add it
                if (! $this->get($col_name)) {
                    // If a relation path is given, need to copy the column and modify it's expressions
                    if ($relPathString !== '') {
                        $colCopy = $col->copy();
                        $colCopy->setName($col_name);
                        // Copy the columns expression with the new relation path. Keep in mind, that
                        // if the old expression already had a relation path, we need to combine both,
                        // so that the behavior is the same for expressions with attribute alias
                        // `RELATION__ATTRIBUTE` and those with attribute alias `ATTRIBUTE` and an
                        // explicit relation path `RELATION`.
                        
                        // IDEA perhaps it would be nicer to use the expression::rebase() here, but the 
                        // relation path seems to be in the wrong direction here
                        $newRelPath = $relationPath;
                        if ($oldRelPath = $col->getExpressionObj()->getRelationPath()) {
                            $newRelPath = $newRelPath->copy()->appendRelationsFromStringPath($oldRelPath->toString());
                        }
                        $colCopy->setExpression($col->getExpressionObj()->withRelationPath($newRelPath));
                        // Add the column, but do not transfer values.
                        // This won't be possible anyway, as $colCopy currently still may belong to another sheet and we changed
                        // it's name, so even if the original $col had values, they won't be associated with the modified $colCopy!
                        $this->add($colCopy, $col_name, false);
                    } else {
                        // If no relation path modification required, just add the column
                        $this->add($col);
                    }
                }
            } else {
                $col_name = $relPathString ? RelationPath::join($relPathString, $col) : $col;
                if ($this->get($col_name) === null) {
                    try {
                        $this->addFromExpression($col_name);
                    } catch (\Throwable $e) {
                        // TODO How to distinguish between unwanted garbage and bad column names?
                    }
                }
            }
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::addFromExpression()
     */
    public function addFromExpression($expression_or_string, $name = null, $hidden = false)
    {
        $data_sheet = $this->getDataSheet();
        $col = DataColumnFactory::createFromString($data_sheet, $expression_or_string, $name);
        $col->setHidden($hidden);
        $this->add($col);
        return $col;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::addFromAttribute()
     */
    public function addFromAttribute(MetaAttributeInterface $attribute, bool $hidden = false)
    {
        $sheetObject = $this->getDataSheet()->getMetaObject();
        // Make sure, it is clear, how the attribute is related to the sheet object. Pay attention to
        // the fact, that the attribute may have a relation path.
        if ($sheetObject->is($attribute->getRelationPath()->getStartObject())) {
            // If the relation path starts with the sheet object, just add the attribute as-is.
            return $this->addFromExpression($attribute->getAliasWithRelationPath(), null, $hidden);
        } elseif ($sheetObject->is($attribute->getObject())) {
            // If the relation path starts with another object, but the attribute itself belongs 
            // to the sheet object, we can still add it by cutting off the relation path.
            return $this->addFromExpression($attribute->getAlias(), null, $hidden);
        } else {
            // If none of the above worked, it's an error!
            throw new DataSheetStructureError($this->getDataSheet(), 'Cannot add attribute "' . $attribute->getAliasWithRelationPath() . '" to data sheet of "' . $sheetObject->getAliasWithNamespace() . '": no relation to the attribute could be found!');
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::addFromUidAttribute()
     */
    public function addFromUidAttribute() : DataColumnInterface
    {
        return $this->addFromAttribute($this->getDataSheet()->getMetaObject()->getUidAttribute());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::addFromLabelAttribute()
     */
    public function addFromLabelAttribute() : DataColumnInterface
    {
        return $this->addFromAttribute($this->getDataSheet()->getMetaObject()->getLabelAttribute());
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::getByExpression()
     */
    public function getByExpression($expression_or_string, bool $checkType = false)
    {
        if ($expression_or_string instanceof ExpressionInterface) {
            $exprString = $expression_or_string->toString();
        } else {
            $exprString = $expression_or_string;
        }
        
        // FIXME #unknown-column-types shouldn't we double-check the column-type here?
        // Especially the second round searching below produces strange results
        // on columns with aggregations. E.g. an attribute column `MY_ATTR:SUM`
        // will match an unknown column `MY_ATTR_SUM`, which may or may not be
        // a good idea depending on the use case! Since transforming data sheets
        // to JS and back often removes the original attribute aliases, this 
        // happens in dialog refreshes: the input of ReadPrefill contains such an
        // aggregated column of type "unknown" (because its attribute_alias was lost
        // in the request from the client - that column is "found" when the widget is
        // looking for its column `MY_ATTR:SUM`, but it cannot be read when refreshing
        // because it lost its attribute binding.
        
        // First check if there is a column with exactly the same expression
        foreach ($this->getAll() as $col) {
            if ($col->getExpressionObj()->toString() === $exprString) {
                return $col;
            }
        }
        
        // If not, see if there is a column with a name matching what would
        // be the name for a column with the same expression. Need to do this
        // because sometimes the original expression is lost due to encoding
        // or decoding data leaving only the column name. If that matches,
        // however, it's enough to assume, that this column fits.
        if ($checkType === false) {
            $colNameForExpression = DataColumn::sanitizeColumnName($exprString);
            if ($colNameForExpression !== '') {
                foreach ($this->getAll() as $col) {
                    if ($col->getName() === $colNameForExpression) {
                        return $col;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Returns the first column, that shows the specified attribute explicitly (not within a formula).
     * Returns FALSE if no column is found.
     *
     * @param MetaAttributeInterface $attribute            
     * @return DataColumnInterface|boolean
     */
    public function getByAttribute(MetaAttributeInterface $attribute)
    {
        $attrAlias = $attribute->getAliasWithRelationPath();
        foreach ($this->getAll() as $col) {
            if ($col->getAttribute() && $col->getAttribute()->getAliasWithRelationPath() == $attrAlias) {
                return $col;
            }
        }
        return false;
    }

    public function getSystem()
    {
        $exface = $this->getWorkbench();
        $parent = $this->getParent();
        $result = new self($exface, $parent);
        foreach ($this->getAll() as $col) {
            if ($col->getAttribute() && $col->getAttribute()->isSystem()) {
                $result->add($col);
            }
        }
        return $result;
    }

    /**
     * Removes a column from the list completetly including it's values
     *
     * @param string $column_name            
     * @return DataColumnListInterface
     */
    public function removeByKey($column_name)
    {
        parent::removeByKey($column_name);
        $this->getDataSheet()->removeRowsForColumn($column_name);
        
        // Make sure, the rows are reset if the last column is removed
        if ($this->isEmpty()) {
            $this->getDataSheet()->removeRows();
        }
        
        return $this;
    }

    /**
     * Returns the parent data sheet (this method is a better understandable alias for get_parent())
     *
     * @return DataSheetInterface
     */
    public function getDataSheet()
    {
        return $this->getParent();
    }

    /**
     * Set the given data sheet as parent object for this column list and all it's columns
     *
     * @see \exface\Core\CommonLogic\EntityList::setParent()
     * @param DataSheetInterface $data_sheet            
     */
    public function setParent($data_sheet)
    {
        $result = parent::setParent($data_sheet);
        foreach ($this->getAll() as $column) {
            $column->setDataSheet($data_sheet);
        }
        return $result;
    }
    
    public function addFromSystemAttributes() : DataColumnListInterface
    {
        foreach ($this->getDataSheet()->getMetaObject()->getAttributes()->getSystem() as $sys) {
            $this->addFromAttribute($sys);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::addFromAttributeGroup()
     */
    public function addFromAttributeGroup(MetaAttributeListInterface $group) : DataColumnListInterface
    {
        foreach ($group->getAll() as $attr) {
            $this->addFromAttribute($attr);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::hasSystemColumns()
     */
    public function hasSystemColumns() : bool
    {
        foreach ($this->getDataSheet()->getMetaObject()->getAttributes()->getSystem() as $attr) {
            if (! $this->getByAttribute($attr)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns a map with column names as keys and expression strings as values.
     *
     * This method can be used to quickly find columns by expression without traversing all column objects. The
     * expressions are cached for performance.
     *
     * @return string[]
     */
    public function getColumnsExpressions() : array
    {
        if ($this->columnsExpressionsCache === null) {
            $this->columnsExpressionsCache = [];
            foreach ($this->getAll() as $column) {
                $this->columnsExpressionsCache[$column->getName()] = $column->getExpressionObj()->__toString();
            }
        }
        return $this->columnsExpressionsCache;
    }
}