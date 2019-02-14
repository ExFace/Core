<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Trait for parts of Data widgets (e.g. DataCalendarItem, DataTimeline, etc.).
 * 
 * @author Andrej Kabachnik
 *
 */
trait DataWidgetPartTrait
{
    use ImportUxonObjectTrait;
    
    private $dataWidget;
    
    public function __construct(iShowData $dataWidget, UxonObject $uxon = null)
    {
        $this->dataWidget = $dataWidget;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->dataWidget->getMetaObject();
    }
    
    /**
     * 
     * @return iShowData
     */
    public function getDataWidget() : iShowData
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
        return $this->getWidget()->getWorkbench();
    }
    
    /**
     * 
     * @param string $expression
     * @return DataColumn
     */
    protected function addDataColumn(string $expression) : DataColumn
    {
        $dw = $this->getDataWidget();
        if (! $col = $dw->getColumnByAttributeAlias($expression)) {
            $col = $dw->createColumnFromUxon(new UxonObject([
                'attribute_alias' => $expression
            ]));
            $dw->addColumn($col);
        }
        return $col;
    }
}