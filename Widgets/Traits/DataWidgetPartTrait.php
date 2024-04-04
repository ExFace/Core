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
 * Most parts of data widgets include the definition of special roles for certain attributes
 * or expressions: e.g. which attribute is the color of something, etc. This trait offers
 * a simple way to ensure, these column are always included in the data widget - just call
 * the method `addDataColumn()`.
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
     * Adds a column to the data widget if there is no such column already and returns this column
     * 
     * Use this method to automatically add columns defined in the query part.
     * This way, the user will not be forced to define the column twice: in the
     * data widget and in the query part.
     * 
     * IMPORTANT: do not cache the returned column for use in `getColumnXXX()`
     * methods if `addDataColumn()` is called from a UXON property setter!!!
     * Instead, wait till the entire data widget is initialized and cache
     * the column then - e.g. when your `getColumnXXX()` method is first called.
     * The reason for this is, that if the widget part adds a column AND there
     * is also such a column defined in the data widget explicitly, the auto-added
     * column will be replaced and the cache version will become an orphan.
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