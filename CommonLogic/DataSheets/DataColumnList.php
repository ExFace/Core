<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Interfaces\DataSheets\DataColumnListInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Expression;

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
        if (! $this->get($column->getName())) {
            if ($column->getDataSheet() !== $data_sheet) {
                $column_original = $column;
                $column = $column_original->copy();
                $column->setDataSheet($data_sheet);
            }
            // Mark the data as outdated if new columns are added because the values for these columns should be fetched now
            $column->setFresh(false);
            $result = parent::add($column, (is_null($key) && $column->getName() ? $column->getName() : $key));
        }
        
        // If the original column had values, use them to overwrite the values in the newly added column
        // IDEA When is this used??? It seems, it can only happen when addin a foreign column? Shouldn't it then be moved to the IF above?
        if ($overwrite_values && $column_original && $column_original->isFresh()) {
            $data_sheet->setColumnValues($column->getName(), $column->getValues());
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
                if (! $this->get($col_name)) {
                    try {
                        $this->addFromExpression($col_name);
                    } catch (\Exception $e) {
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
    public function addFromExpression($expression_or_string, $name = '', $hidden = false)
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
    public function addFromAttribute(Attribute $attribute)
    {
        return $this->addFromExpression($attribute->getAliasWithRelationPath());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::getByExpression()
     */
    public function getByExpression($expression_or_string)
    {
        if ($expression_or_string instanceof Expression) {
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
     * @param Attribute $attribute            
     * @return DataColumnInterface|boolean
     */
    public function getByAttribute(Attribute $attribute)
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