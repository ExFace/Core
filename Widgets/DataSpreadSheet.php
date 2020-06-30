<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Traits\EditableTableTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\DataSpreadSheetFooter;
use exface\Core\Widgets\Traits\DataTableTrait;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;

/**
 * An Excel-like table with editable cells.
 * 
 * THe spreadsheet is very handy for editing multiple rows of data. Depending on the facade used,
 * it will have Excel-like features like autofill, formulas, etc.
 * 
 *  An editor widget can be defined for every column. If no editor is explicitly defined, the default
 * editor for the attribute will be used - similarly to a DataTable with editable columns.
 * 
 * In contrast to a `DataTable`, it does not offer row grouping, row details, etc. - it's focus
 * is comfortable editing of flat tabular data. Also, the `DataSpreadSheet` has different default
 * settings:
 * 
 * - `editable` is `true` by default
 * - `paginate` is `false` by default 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSpreadSheet extends Data implements iFillEntireContainer, iTakeInput
{
    use EditableTableTrait;
    use DataTableTrait;
    
    private $defaultRow = null;
    
    private $allowToAddRows = null;
    
    private $allowToDeleteRows = null;
    
    protected function init()
    {
        parent::init();
        $this->setPaginate(false);
        $this->setEditable(true);
    }
    
    public function getWidth()
    {
        if (parent::getWidth()->isUndefined()) {
            $this->setWidth('max');
        }
        return parent::getWidth();
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return null;
    }
    
    /**
     *
     * @return array
     */
    public function getDefaultRow() : array
    {
        foreach ($this->defaultRow as $alias => $expr) {
            if (! $expr instanceof ExpressionInterface) {
                $col = $this->getColumnByAttributeAlias($alias);
                if (! $col) {
                    throw new WidgetConfigurationError('Cannot use "' . $expr . '" as key in the default row of ' . $this->getWidgetType() . ': it does not match the attribute_alias of any existing column!');
                }
                $expression = ExpressionFactory::createForObject($this->getMetaObject(), $expr);
                $this->defaultRow[$col->getDataColumnName()] = $expression;
            }
        }
        return $this->defaultRow;
    }
    
    /**
     * Make empty spreadsheets autocreate a first row from given column values.
     * 
     * This property takes key-value-pairs as a UXON object with `attribute_alias`
     * of the column to fill for keys and expressions for values: formulas, widget 
     * links, numbers, quoted strings or other attribute aliases.
     * 
     * Here is an example for a spreadsheet over an object with `date` and 'qty'
     * among it's attributes.
     * 
     * ```
     * "default_row": {
     *  "date": "=NOW()",
     *  "qty": 1 
     * }
     * 
     * ```
     * 
     * @uxon-property default_row
     * @uxon-type {metamodel:attribute => metamodel:expression}
     * @uxon-template {"// attribute alias": "// =formula, another attribute alias, number or quoted string"}
     * 
     * @param array $uxon
     * @return DataSpreadSheet
     */
    public function setDefaultRow(UxonObject $uxon) : DataSpreadSheet
    {
        $this->defaultRow = $uxon->toArray();
        return $this;
    }
    
    public function hasDefaultRow() : bool
    {
        return $this->defaultRow !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getFooterWidgetPartClass()
     */
    public function getFooterWidgetPartClass() : string
    {
        return '\\' . DataSpreadSheetFooter::class;
    }
    
    /**
     *
     * @return bool
     */
    public function getAllowToAddRows() : bool
    {
        return $this->allowToAddRows ?? $this->isEditable();
    }
    
    /**
     * Set to FALSE to disable adding new rows.
     * 
     * @uxon-property allow_to_add_rows
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return DataSpreadSheet
     */
    public function setAllowToAddRows(bool $value) : DataSpreadSheet
    {
        $this->allowToAddRows = $value;
        return $this;
    }
    
    
    /**
     *
     * @return bool
     */
    public function getAllowToDeleteRows() : bool
    {
        return $this->allowToDeleteRows ?? $this->isEditable();
    }
    
    /**
     *
     * Set to FALSE to disable deleting rows.
     * 
     * @uxon-property allow_to_delete_rows
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return DataSpreadSheet
     */
    public function setAllowToDeleteRows(bool $value) : DataSpreadSheet
    {
        $this->allowToDeleteRows = $value;
        return $this;
    }
}