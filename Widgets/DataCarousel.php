<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iShowData;

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
     * @return iShowData
     */
    public function getDataWidget() : iShowData
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
     * @param iShowData $dataWidget
     * @return DataCarousel
     */
    protected function setDataWidget(iShowData $dataWidget) : DataCarousel
    {
        $this->dataWidget = $dataWidget;
        return $this;
    }
    
    public function setData(UxonObject $uxon) : DataCarousel
    {
        $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'DataList');
        if ($widget instanceof iUseData) {
            $this->setDataWidget($widget->getData());
        } else {
            $this->setDataWidget($widget);
        }
        $this->addWidget($widget, 0);
        return $this;
    }
    
    public function getMasterWidget() : WidgetInterface
    {
        return $this->getWidget(0);
    }

    /**
     * 
     * @return iContainOtherWidgets
     */
    public function getDetailsWidget() : iContainOtherWidgets
    {
        return $this->detailsWidget;
    }

    /**
     * 
     * @param WidgetInterface $container
     * @return DataCarousel
     */
    public function setDetailsWidget(iContainOtherWidgets $container) : DataCarousel
    {
        $this->detailsWidget = $container;
        return $this;
    }
    
    public function setDetails(UxonObject $uxon) : DataCarousel
    {
        $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Form');
        $this->addWidget($widget, 1);
        $this->setDetailsWidget($widget);
        return $this;
    }

}
?>