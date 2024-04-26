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
    use DataWidgetPartTrait;
    
    private $columns = [];
    
    private $rows = [];
    
    private $values = [];
    
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
}