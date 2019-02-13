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
 * A master-detail widget showing a data widget and a container or form working on the same data set.
 * 
 * This widget consists of 
 * 
 * ## Similar widgets and alternatives
 * 
 * Use `ImageCarousel` to build a carousel of images. By default, it will use the `Imagegallery`
 * as data widget and show a larger version of each image in the details. You can define a custom
 * detail-widget to display additional information about the image or even create editors.
 * 
 * To build a master-detail for drilling down nested structures, use a `Split` widget with two or
 * more data widgets of your choice and link the value of a filter in the detail widget with the
 * value (= selected element) of the master widget. In contrast to a `DataCarousel`, this structure
 * will not operate on a common data set, the master will load it's data and the detail-widget will
 * use a part of it to load it's own data.
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataCarousel extends Split
{
    const DETAIL_POSITION_LEFT = 'left';
    const DETAIL_POSITION_RIGHT = 'right';
    const DETAIL_POSITION_TOP = 'top';
    const DETAIL_POSITION_BOTTOM = 'bottom';
    
    private $dataWidget = null;
    
    private $detailsWidget = null;
    
    private $showDetails = null;
    
    private $detailPosition = null;

    /**
     * 
     * @return iShowData
     */
    public function getDataWidget() : iShowData
    {
        if ($this->dataWidget === null) {
            $defaultData = WidgetFactory::create($this->getPage(), $this->getDefaultDataWidgetType(), $this);
            $this->setDataWidget($defaultData);
        }
        return $this->dataWidget;
    }
    
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $details = $this->getDetailsWidget();
        foreach ($details->getChildrenRecursive() as $child) {
            if ($child instanceof iShowSingleAttribute && $child->isBoundToAttribute()) {
                $widget->addColumn($widget->createColumnFromAttribute($child->getAttribute(), null, true));
            }
        }
        return $widget;
    }
    
    protected function isDataInitialized() : bool
    {
        return $this->dataWidget !== null;
    }

    /**
     * 
     * @param iShowData $dataWidget
     * @return DataCarousel
     */
    protected function setDataWidget(iShowData $dataWidget) : DataCarousel
    {
        $this->dataWidget = $this->initDataWidget($dataWidget);
        return $this;
    }
    
    public function setData(UxonObject $uxon) : DataCarousel
    {
        $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, $this->getDefaultDataWidgetType());
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
        if ($this->detailsWidget === null) {
            $this->detailsWidget = WidgetFactory::create($this->getPage(), 'Container', $this);
        }
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

    /**
     *
     * @return bool
     */
    public function getShowDetails() : bool
    {
        return $this->showDetails;
    }
    
    /**
     *
     * @param bool $value
     * @return ImageCarousel
     */
    public function setShowDetails(bool $value) : ImageCarousel
    {
        $this->showDetails = $value;
        return $this;
    }
    
    protected function getDefaultDataWidgetType() : string
    {
        return 'DataList';
    }
    
    public function getChildren() : \Iterator
    {
        foreach(parent::getChildren() as $child) {
            yield $child;
        }
        
        yield $this->getDataWidget();
        yield $this->getDetailsWidget();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Split::getOrientation()
     */
    protected function getOrientation() : string
    {
        switch ($this->getDetailPosition()) {
            case self::DETAIL_POSITION_BOTTOM:
            case self::DETAIL_POSITION_TOP:
                return self::ORIENTATION_VERTICAL;
            default:
                return self::ORIENTATION_HORIZONTAL;
        }
    }
    
    /**
     * Setting orientation is not enough for a carousel - use setDetailPosition() instead!
     *
     * No UXON annotations here!
     *
     * @param string $value
     * @return SplitVertical
     */
    public function setOrientation(string $value) : Split
    {
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getDetailPosition() : ?string
    {
        return $this->detailPosition;
    }
    
    /**
     * Where to place the detail widget: left, right, on-top (top) or below (bottom) of the master/data widget.
     * 
     * @uxon-property detail_position
     * @uxon-type [left,right,top,bottom]
     * @uxon-default right
     * 
     * @param string $value
     * @return DataCarousel
     */
    public function setDetailPosition(string $value) : DataCarousel
    {
        $this->detailPosition = $value;
        return $this;
    }
}