<?php
namespace exface\Core\Widgets\Parts\Pivot;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\PivotTable;

/**
 * 
 * 
 * @method PivotTable getWidget()
 *
 * @author Andrej Kabachnik
 *        
 */
class PivotLayout implements WidgetPartInterface
{
    const COLUMN_SUBTOTALS_TOP = 'top';
    const COLUMN_SUBTOTALS_BOTTOM = 'bottom';
    const COLUMN_SUBTOTALS_NONE = 'none';
    
    const ROW_SUBTOTALS_RIGHT = 'right';
    const ROW_SUBTOTALS_NONE = 'none';
    
    use DataWidgetPartTrait;
    
    private $columns = [];
    
    private $rows = [];
    
    private $values = [];
    
    private $showRowTotals = true;
    
    private $showRowSubtotals = self::ROW_SUBTOTALS_NONE;
    
    private $showColumnTotals = true;
    
    private $showColumnSubtotals = self::COLUMN_SUBTOTALS_NONE;
    
    private $showSubtotals = true;
    
    /**
     * 
     * @throws WidgetConfigurationError
     * @return PivotTable
     */
    public function getPivotTable()
    {
        $table = $this->getDataWidget();
        if (! ($table instanceof PivotTable)) {
            throw new WidgetConfigurationError($this, 'Pivot layout elements cannot be used outside of a PivotTable widget!', '6Z5MAVK');
        }
        return $table;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        $uxon = new UxonObject([]);
        
        return $uxon;
    }
    
    /**
     * 
     * @return PivotDimension[]
     */
    public function getPivotColumns() : array
    {
        return $this->columns;
    }
    
    /**
     * Attribtues to be used for columns
     * 
     * @uxon-property columns
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param string[] $arrayOfAliases
     * @return PivotLayout
     */
    protected function setColumns(UxonObject $arrayOfAliases) : PivotLayout
    {
        foreach($arrayOfAliases as $alias) {
            $this->columns[] = new PivotDimension($this, new UxonObject([
                'attribute_alias' => $alias
            ]));
            //$this->addDataColumn($alias);
        }
        return $this;
    }
    
    /**
     * 
     * @return PivotDimension[]
     */
    public function getPivotRows() : array
    {
        return $this->rows;
    }
    
    /**
     * Attribtues to be used for rows
     * 
     * @uxon-property rows
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param string[] $arrayOfAliases
     * @return PivotLayout
     */
    protected function setRows(UxonObject $arrayOfAliases) : PivotLayout
    {
        foreach($arrayOfAliases as $alias) {
            $this->rows[] = new PivotDimension($this, new UxonObject([
                'attribute_alias' => $alias
            ]));
            //$this->addDataColumn($alias);
        }
        return $this;
    }

    /**
     * Attribtues to be used for values - each with a corresponding aggregator
     * 
     * @uxon-property values
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param string[] $arrayOfAliases
     * @return PivotLayout
     */
    public function getPivotValues() : array
    {
        return $this->values;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasPivotValues() : bool
    {
        return empty($this->values) === false;
    }
    
    /**
     * Attribtues to be used for values - each with a corresponding aggregator
     * 
     * @uxon-property values
     * @uxon-type \exface\Core\Widgets\Parts\Pivot\PivotValue[]
     * @uxon-template [{"attribute_alias": "", "aggregator": ""}]
     * 
     * @param string[] $arrayOfAliases
     * @return PivotLayout
     */
    protected function setValues(UxonObject $arrayOfAliases) : PivotLayout
    {
        foreach($arrayOfAliases as $uxon) {
            $pivotValue = new PivotValue($this, $uxon);
            $this->values[] = $pivotValue;
            //$this->addDataColumn($pivotValue->getAttributeAlias());
        }
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getShowRowTotals() : bool
    {
        return $this->showRowTotals;
    }
    
    /**
     * Set to FALSE to hide the grand total column at the end of the table
     *
     * @uxon-property show_row_totals
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return PivotLayout
     */
    public function setShowRowTotals(bool $value) : PivotLayout
    {
        $this->showRowTotals = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getShowColumnTotals() : bool
    {
        return $this->showColumnTotals;
    }
    
    /**
     * Set to FALSE to hide the grand total row at the end of the table
     *
     * @uxon-property show_column_totals
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return PivotLayout
     */
    public function setShowColumnTotals(bool $value) : PivotLayout
    {
        $this->showColumnTotals = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowRowSubtotals() : string
    {
        return $this->showRowSubtotals;
    }
    
    /**
     * Show or hide subtotals for each set of columns in a row
     * 
     * @uxon-property show_row_subtotals
     * @uxon-type [right,none]
     * @uxon-default right
     * 
     * @param bool $value
     * @return PivotLayout
     */
    public function setShowRowSubtotals(string $value) : PivotLayout
    {
        $const = 'static::ROW_SUBTOTALS_' . mb_strtoupper($value);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getPivotTable(), 'Invalid value "' . $value . '" for property "show_row_subtotals" of widget ' . $this->getWidget()->getWidgetType());
        }
        $this->showRowSubtotals = constant($const);
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowColumnSubtotals() : string
    {
        return $this->showColumnSubtotals;
    }
    
    /**
     * Show or hide subtotals for each set of rows in a column
     * 
     * @uxon-property show_column_subtotals
     * @uxon-type [top,bottom,none]
     * @uxon-default top
     * 
     * @param bool $value
     * @return PivotLayout
     */
    public function setShowColumnSubtotals(string $value) : PivotLayout
    {
        $const = 'static::COLUMN_SUBTOTALS_' . mb_strtoupper($value);
        if (! defined($const)) {
            throw new WidgetConfigurationError($this->getPivotTable(), 'Invalid value "' . $value . '" for property "show_column_subtotals" of widget ' . $this->getWidget()->getWidgetType());
        }
        $this->showColumnSubtotals = constant($const);
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasSubtotals() : bool
    {
        return $this->getShowColumnSubtotals() !== self::COLUMN_SUBTOTALS_NONE || $this->getShowRowSubtotals() !== self::ROW_SUBTOTALS_NONE;
    }
}