<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iShowDataColumn;

/**
 * A master-detail widget showing a data widget and a detail-container (e.g. Form) working on the same data.
 * 
 * The user can cycle through the items in the data widget viewing details or making changes in the
 * detail-container. Changes will immediately have affect on the data widget, but will remain local
 * until the entire data is sent to the data source. 
 * 
 * The carousel consists of a `data` widget (typically a `DataTableResponsive`) and a `details` widget,
 * (typically a `Form`), that is positioned next to the data and gets filled once a data item is selected. 
 * Whether the details are displayed left, right, on top or below the data is controlled by the 
 * `details_position` property.
 * 
 * The width/height of each area can be simply controlled by setting `width` or `height` of the respecitve
 * child widget.
 * 
 * **NOTE:** sharing data between the data widget and the details-widgets only works if they are based
 * on the same object or are explicitly bound to the same data columns - see examples for more information.
 * You can still add other types of widgets to the details container (e.g. based on another object or not
 * boundt to data at all), but they will not react to seletions made in the data widget.
 * 
 * ## Example
 * 
 * ```
 * {
 *  "widget_type": "DataCarousel",
 *  "object_alias": "exface.Core.OBJECT"
 *  "data": {
 *      "widget_type": "DataTableResponsive",
 *      "columns": [
 *          {"attribute_alias": "NAME"},
 *          {"data_column_name": "my_custom_column"}
 *      ],
 *      "buttons": [
 *          {"action_alias": "exface.Core.UpdateData"}
 *      ]
 *  },
 *  "details": {
 *      "widget_type": "Form",
 *      "widgets": [
 *          {"attribute_alias": "NAME"},
 *          {"attribute_alias": "ALIAS"},
 *          {"data_column_name": "my_custom_column", "widget_type": "Input"}
 *      ]
 *  }
 * 
 * ```
 * 
 * In this example, a responsive table with meta object names and an (empty) custom column will be
 * shown next to a form, that will get filled with the data of the selected row from the table. 
 * If the `NAME` is changed in the form, it will change immediately in the table - but the change
 * will only get saved in the data source once the `UpdateData` button is pressed (saving all changed
 * rows). 
 * 
 * Also note, that the `ALIAS` is only present in the details widget. It will be added to the table
 * as an invisible column automatically, so changes to it will still have effect.
 * 
 * The non-attribute `my_custom_column` will be synced between the data and the details widget too
 * because both widgets are bound to the same data column. In this case, the column/widget both need
 * to be explicitly defined - no invisible column can be added automatically! 
 * 
 * ## Responsive behavior
 * 
 * On small screens, data and details won't fit on the screen at once, so the widget will show the
 * data first and swith to the details once an item is select. This behavior may be slightly different
 * depending on the facade used.
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
    
    private $dataWidgetInitialized = false;
    
    private $detailsWidget = null;
    
    private $detailPosition = null;
    
    /**
     * 
     * @return bool
     */
    protected function hasDataWidget() : bool
    {
        return $this->dataWidget !== null;
    }

    /**
     * 
     * @return iShowData
     */
    public function getDataWidget() : iShowData
    {
        if ($this->dataWidget === null) {
            try {
                $widget = parent::getWidget(0);
            } catch (WidgetChildNotFoundError $e) {
                throw new WidgetConfigurationError($this, 'No data widget in widget "' . $this->getWidgetType() . '": please fill the `data` property!', '7DA4MW9');
            }
            if ($widget instanceof iUseData) {
                $widget = $widget->getData();
            } 
            if (! ($widget instanceof Data)) {
                throw new WidgetConfigurationError($this, 'Invalid type of data widget "' . $widget->getWidgetType() . '" inside widget "' . $this->getWidgetType() . '": expecting a data widget like `DataTableResponsive`!', '7DA4MW9');
            }
            $this->setDataWidget($widget);
        }
        if ($this->dataWidgetInitialized === false) {
           $this->initDataWidget($this->dataWidget);
        }
        return $this->dataWidget;
    }
    
    /**
     * 
     * @param iShowData $widget
     * @return iShowData
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        if ($this->hasDetailsWidget()) {
            $details = $this->getDetailsWidget();
            foreach ($this->getChildrenToSyncWithDataWidget($details) as $child) {
                if ($child instanceof iShowSingleAttribute && $child->isBoundToAttribute()) {
                    if (! $widget->getColumnByAttributeAlias($child->getAttributeAlias())) {
                        $widget->addColumn($widget->createColumnFromAttribute($child->getAttribute(), null, true));
                    }
                }
            }
            $this->dataWidgetInitialized = true;
        }
        return $widget;
    }
    
    /**
     * Returns all the details widgets, which need data that can be loaded via data widget:
     * 
     * - Those bound to an attribute of the same object as the carousel
     * - Those bound to a data column (e.g. via `data_column_name`) base on the same object
     * 
     * The search for details widget is performed recursively in all containers within the
     * `details` widget.
     * 
     * @param iContainOtherWidgets $container
     * @return WidgetInterface[]
     */
    public function getChildrenToSyncWithDataWidget(iContainOtherWidgets $container) : array
    {
        $widgets = [];
        foreach ($container->getWidgets() as $child) {
            switch (true) {
                case ! $child->getMetaObject()->is($this->getMetaObject()):
                    break;
                case $child instanceof iShowSingleAttribute && $child->isBoundToAttribute():
                case $child instanceof iShowDataColumn && $child->isBoundToDataColumn():
                    $widgets[] = $child;
                    break;
                case $child instanceof iContainOtherWidgets:
                    $widgets = array_merge($widgets, $this->getChildrenToSyncWithDataWidget($child));
                    break;
            }
        }
        return $widgets;
    }

    /**
     * 
     * @param iShowData $dataWidget
     * @return DataCarousel
     */
    protected function setDataWidget(iShowData $dataWidget) : DataCarousel
    {
        $dataWidget = $this->initDataWidget($dataWidget);
        $this->dataWidget = $dataWidget;
        $this->addWidget($dataWidget, 0);
        return $this;
    }
    
    /**
     * Widget to select the desired data item to show in the details - e.g. a `DataTableResponsive`.
     * 
     * @uxon-property data
     * @uxon-type \exface\Core\Widgets\Data
     * @uxon-template {"widget_type": "DataTableResponsive", "": ""}
     * @uxon-required true
     * 
     * @param UxonObject $uxon
     * @return DataCarousel
     */
    public function setData(UxonObject $uxon) : DataCarousel
    {
        $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, $this->getDefaultDataWidgetType());
        if ($widget instanceof iUseData) {
            $this->setDataWidget($widget->getData());
        } else {
            $this->setDataWidget($widget);
        }
        return $this;
    }
    
    protected function hasDetailsWidget() : bool
    {
        return $this->detailsWidget !== null;
    }

    /**
     * 
     * @return iContainOtherWidgets
     */
    public function getDetailsWidget() : iContainOtherWidgets
    {
        if ($this->detailsWidget === null) {
            try {
                $widget = parent::getWidget(1);
            } catch (WidgetChildNotFoundError $e) {
                throw new WidgetConfigurationError($this, 'No details widget in widget "' . $this->getWidgetType() . '": please fill the `details` property!', '7DA4MW9');
            }
            if (! ($widget instanceof iContainOtherWidgets)) {
                throw new WidgetConfigurationError($this, 'Invalid type of details widget "' . $widget->getWidgetType() . '" inside widget "' . $this->getWidgetType() . '": expecting a container widget like `Form`!', '7DA4MW9');
            }
            $this->setDetailsWidget($widget);
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
        $this->addWidget($container, 1);
        
        // Make sure to re-init the data widget since it depends on the details!
        if ($this->dataWidget !== null) {
            $this->initDataWidget($this->dataWidget);
        }
        
        return $this;
    }
    
    /**
     * Container to display the details for the item selected in the data widget - e.g. a `Form`.
     * 
     * @uxon-property details
     * @uxon-type \exface\Core\Widgets\Container
     * @uxon-template {"widget_type": "Form", "":""}
     * @uxon-required true
     * 
     * @param UxonObject $uxon
     * @return DataCarousel
     */
    public function setDetails(UxonObject $uxon) : DataCarousel
    {
        $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, 'Form');
        $this->setDetailsWidget($widget);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getDefaultDataWidgetType() : string
    {
        return 'DataTableResponsive';
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
    
    /**
     * @deprecated use setData() and setDetails() instead!
     * 
     * @see \exface\Core\Widgets\Split::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        throw new WidgetConfigurationError($this, 'Cannot set widget of ' . $this->getWidgetType() . ' directly: pleasy use `data` and `details` to define the widgets inside!', '7DA4MW9');
    }
}