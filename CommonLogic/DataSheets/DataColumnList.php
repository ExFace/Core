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
        
        $data_sheet = $this->getDataSheet();
        $existingColumn = $this->get($column->getName());
        if (! $existingColumn || $existingColumn->getExpressionObj()->toString() !== $column->getExpressionObj()->toString()) {
            if ($column->getDataSheet() !== $data_sheet) {
                $column_original = $column;
                $column = $column_original->copy();
                $column->setDataSheet($data_sheet);
                
                // If the original column had values, use them to overwrite the values in the newly added column
                if ($overwrite_values && $column_original->isFresh()) {
                    $data_sheet->setColumnValues($column->getName(), $column->getValues());
                }
            }
            
            $result = parent::add($column, ($key === null && $column->getName() ? $column->getName() : $key));
            unset($existingColumn);
            
            // Mark the data as outdated if new columns are added because the values for these columns should be fetched now
            // Actually we do not need to mark static columns not fresh - we could recalculate them right away without
            // querying the data source.
            if ($column->isStatic()) {
                // If we are adding a static column, no data source refresh is actually needed - a recalculation is enough.
                $column->setValuesByExpression($column->getExpressionObj(), true);
            } else {
                $column->setFresh(false);
            }
            
            return $this;            
        }
        
        return $result;
    }

    /**
     * Add an array of columns.
     * The array can contain DataColumns, expressions or a mixture of those
     *
     * @param array $columns            
     * @param string $relation_path            
     * @return DataColumnListInterface
     */
    public function addMultiple(array $columns, $relation_path = '')
    {
        foreach ($columns as $col) {
            if ($col instanceof DataColumn) {
                $col_name = $relation_path ? RelationPath::relationPathAdd($relation_path, $col->getName()) : $col->getName();
                if (! $this->get($col_name)) {
                    // Change the column name so it does not overwrite any existing columns
                    $col->setName($col_name);
                    // Add the column (this will change the column's data sheet, etc.)
                    $this->add($col);
                    // Modify the column's expression and overwrite the old one. Overwriting explicitly is important because
                    // it will also update the attribute alias, etc.
                    // FIXME perhaps it would be nicer to use the expression::rebase() here, but the relation path seems to
                    // be in the wrong direction here
                    $col->setExpression($col->getExpressionObj()->setRelationPath($relation_path));
                    // Update the formatter
                    if ($col->getFormatter()) {
                        $col->getFormatter()->setRelationPath($relation_path);
                    }
                }
            } else {
                $col_name = $relation_path ? RelationPath::relationPathAdd($relation_path, $col) : $col;
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
    public function addFromAttribute(MetaAttributeInterface $attribute)
    {
        $sheetObject = $this->getDataSheet()->getMetaObject();
        // Make sure, it is clear, how the attribute is related to the sheet object. Pay attention to
        // the fact, that the attribute may have a relation path.
        if ($sheetObject->is($attribute->getRelationPath()->getStartObject())) {
            // If the relation path starts with the sheet object, just add the attribute as-is.
            return $this->addFromExpression($attribute->getAliasWithRelationPath());
        } elseif ($sheetObject->is($attribute->getObject())) {
            // If the relation path starts with another object, but the attribute itself belongs 
            // to the sheet object, we can still add it by cutting off the relation path.
            return $this->addFromExpression($attribute->getAlias());
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
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::getByExpression()
     */
    public function getByExpression($expression_or_string)
    {
        if ($expression_or_string instanceof ExpressionInterface) {
            $expression_or_string = $expression_or_string->toString();
        }
        foreach ($this->getAll() as $col) {
            if ($col->getExpressionObj()->toString() === $expression_or_string) {
                return $col;
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
        foreach ($this->getAll() as $col) {
            if ($col->getAttribute() && $col->getAttribute()->getAliasWithRelationPath() == $attribute->getAliasWithRelationPath()) {
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
}
?>