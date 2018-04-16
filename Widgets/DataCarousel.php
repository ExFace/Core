<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iShowDataSet;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class DataCarousel extends SplitHorizontal
{
    private $dataWidget = null;
    
    private $detailsWidget = null;

    /**
     * 
     * @return iShowDataSet
     */
    public function getDataWidget() : WidgetInterface
    {
        $details = $this->getDetailsWidget();
        foreach ($details->getChildrenRecursive() as $child) {
            if ($child instanceof iShowSingleAttribute && $child->hasAttributeReference()) {
                $this->dataWidget->addColumn($this->dataWidget->createColumnFromAttribute($child->getAttribute(), null, true));
            }
        }
        return $this->dataWidget;
    }

    /**
     * 
     * @param iShowDataSet $dataWidget
     * @return DataCarousel
     */
    public function setDataWidget(WidgetInterface $dataWidget) : DataCarousel
    {
        $this->dataWidget = $dataWidget;
        return $this;
    }
    
    public function setData(UxonObject $uxon) : DataCarousel
    {
        $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'DataList');
        $this->setDataWidget($widget);
        return $this;
    }


    /**
     * 
     * @return unkown
     */
    public function getDetailsWidget() : iContainOtherWidgets
    {
        return $this->detailsWidget;
    }

    /**
     * 
     * @param WidgetInterface $detailsWidget
     * @return DataCarousel
     */
    public function setDetailsWidget(iContainOtherWidgets $detailsWidget) : DataCarousel
    {
        $this->detailsWidget = $detailsWidget;
        return $this;
    }
    
    public function setDetails(UxonObject $uxon) : DataCarousel
    {
        $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Form');
        $this->setDetailsWidget($widget);
        return $this;
    }
    
    public function getWidgets(callable $filter = null)
    {
        return [$this->getDataWidget(), $this->getDetailsWidget()];
    }

}
?>