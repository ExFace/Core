<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iTakeInputAsDataSubsheet;
use exface\Core\Widgets\Traits\EditableTableTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\DataSpreadSheetFooter;
use exface\Core\Widgets\Traits\DataTableTrait;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iCanWrapText;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\Widgets\iCanEditData;

/**
 * An Excel-like table with editable cells.
 * 
 * The spreadsheet is very handy for editing multiple rows of data. Depending on the facade used,
 * it will have Excel-like features like autofill, formulas, etc.
 * 
 * An editor widget can be defined for every column. If no editor is explicitly defined, the default
 * editor for the attribute will be used - similarly to a DataTable with editable columns.
 * 
 * In contrast to a `DataTable`, it does not offer row grouping, row details, etc. - it's focus
 * is comfortable editing of flat tabular data. Also, the `DataSpreadSheet` has different default
 * settings:
 * 
 * - `editable` is `true` by default
 * - `paginate` is `false` by default 
 * 
 * It is possible to save row numbers of the spreadsheet in an attribute using `row_number_attribute_alias`. 
 * This is important if the user should be able to insert new rows between existing ones or sort
 * rows. However, if this feature is used, you should be careful with filtering and sorting - each
 * time the data is saved, the row-number-attribute will be overwritten with the current row number
 * in the sheet!
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSpreadSheet extends Data implements iFillEntireContainer, iTakeInputAsDataSubsheet, iCanEditData, iCanWrapText
{
    use EditableTableTrait;
    use DataTableTrait;
    
    private $defaultRow = null;
    
    private $allowToAddRows = null;
    
    private $allowToDeleteRows = null;
    
    private $allowToDragRows = null;
    
    private $allowEmptyRows = false;
    
    private $rowNumberAttributeAlias = null;
    
    private $rowNumberColumn = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setPaginate(false);
        $this->setEditable(true);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getWidth()
     */
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
    
    /**
     * 
     * @return bool
     */
    public function getAllowToDragRows() : bool
    {
        return $this->allowToDragRows ?? $this->hasRowNumberAttribute();
    }
    
    /**
     * Set to TRUE to allow reordering rows by drag and drop.
     * 
     * Draggin rows is enabled automatically once a `row_number_attribute_alias` is set. You
     * can disable it manually in this case.
     * 
     * Should dragging rows be enabled without `row_number_attribute_alias`, make sure the
     * order of rows is stored in some other way - otherwise the position of a dragged row
     * will not be saved and will be restored after the data has been read again.
     * 
     * @uxon-property allow_to_drag_rows
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return DataSpreadSheet
     */
    public function setAllowToDragRows(bool $value) : DataSpreadSheet
    {
        $this->allowToDragRows = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getAllowEmptyRows() : bool
    {
        return $this->allowEmptyRows;
    }
    
    /**
     * Set to TRUE to include empty rows in action data
     * 
     * By default, visually empty rows (= empty except for hidden columns) are ignored and
     * not treated as part of the data passed to actions.
     * 
     * @uxon-property allow_empty_rows
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return DataSpreadSheet
     */
    public function setAllowEmptyRows(bool $value) : DataSpreadSheet
    {
        $this->allowEmptyRows = $value;
        return $this;
    }
    
    /**
     * Save row numbers to an attribute.
     * 
     * This option binds the row number column of the spreadsheet (the one a the very left) to an
     * attribute. This will result in row numbers being saved to the data source. This is very
     * usefull for all sorts of child-objets, that need to be explicitly ordererd (i.e. by
     * drag and drop) - e.g. tasks in a project or similar.
     * 
     * NOTE: this will automatically add a sorter over this attribute if no sorters are explicitly
     * defined for the widget. However, if sorters are manually set, they should take care of
     * the proper sorting of row numbers!
     * 
     * @uxon-property row_number_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return DataSpreadSheet
     */
    public function setRowNumberAttributeAlias(string $value) : DataSpreadSheet
    {
        if (! $col = $this->getColumnByAttributeAlias($value)) {
            $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($value), null, true);
            $this->addColumn($col);
        }
        $this->rowNumberAttributeAlias = $value;
        $this->rowNumberColumn = $col;
        return $this;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getRowNumberColumn() : ?DataColumn
    {
        return $this->rowNumberColumn;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasRowNumberAttribute() : bool
    {
        return $this->rowNumberAttributeAlias !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getSorters()
     */
    public function getSorters() : array 
    {
        $sorters = parent::getSorters();
        // Sort over the row number attribute if applicable
        if (empty($sorters) && $this->rowNumberAttributeAlias !== null) {
            $sorters[] = new UxonObject([
                'attribute_alias' => $this->rowNumberAttributeAlias,
                'direction' => SortingDirectionsDataType::ASC
            ]);
        }
        return $sorters;
    }
}