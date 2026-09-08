<?php
namespace exface\Core\Widgets\Parts\Pivot;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\PivotTable;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *        
 */
class PivotDimension implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $attributeAlias = null;
    
    private $dataWidget;
    
    private $layout;
    
    public function __construct(PivotLayout $layout, UxonObject $uxon = null)
    {
        $this->layout = $layout;
        $this->dataWidget = $layout->getPivotTable();
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    protected function setAttributeAlias(string $alias) : WidgetPartInterface
    {
        $this->attributeAlias = $alias;
        return $this;
    }
    
    public function getAttributeAlias() : string
    {
        return $this->attributeAlias;
    }
    
    public function getAttribute(): MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getAttributeAlias());
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getDataColumn() : DataColumn
    {
        $exprString = $this->getAttributeAlias();
        if (Expression::detectCalculation($exprString)) {
            $col = $this->getPivotTable()->getColumnByExpression($exprString);
        } else {
            $col = $this->getPivotTable()->getColumnByAttributeAlias($this->getAttributeAlias());
        }
        if ($col === null) {
            throw new WidgetConfigurationError($this->getPivotTable(), 'Pivot dimension "' . $this->getAttributeAlias() . '" does not exist in the pivot table!');
        }
        return $col;
    }
    
    /**
     * 
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface
    {
        return $this->getDataColumn()->getDataType();
    }
    
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->dataWidget->getMetaObject();
    }
    
    /**
     *
     * @throws WidgetConfigurationError
     * @return PivotTable
     */
    public function getPivotTable()
    {
        return $this->dataWidget;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->dataWidget;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->dataWidget->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        $uxon = new UxonObject([
            'attribute_alias' => $this->getAttributeAlias()
        ]);
        
        return $uxon;
    }
}