<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Widgets\iHaveColorWithOutline;
use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Maps\Traits\ColoredLayerTrait;
use exface\Core\DataTypes\NumberDataType;

/**
 * Draws straight line(s) between two coordinates in a data row. The coordinates are give as longitude and latitude values. A line an interactive element with its own pop-up.
 *
 * ### Data Setup Guide
 * 
 * Please open the properties of widget_type `Map` for a full guide on how to pass on data to any layer in a map widget!
 * If no data or filter within data is provided everything from the object_alias is loaded, or it will use the data from another layer with the same object_alias.
 * 
 * ### Widget Setup Guide
 * 
 * Will show one or multiple lines from the linked table.
 * 
 * ```
 * {
 *     "type": "DataLines",
 *     "object_alias": "the.app.your_object_alias",
 *     "caption": "Your point from client data",
 *     "//": "For data setup please look into the properties of the Map widget."
 *     "from_longitude_attribute_alias": "your_from_longitude_alias",
 *     "from_latitude_attribute_alias": "your_from_latitude_alias",
 *     "to_longitude_attribute_alias": "your_to_longitude_alias",
 *     "to_latitude_attribute_alias": "your_to_latitude_alias",
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class DataLinesLayer extends AbstractDataLayer
    implements
    ColoredDataMapLayerInterface
{
    // TODO: Add color and values (color outline is missing since it is a line, but we wait for the new config with #1786)
    use ColoredLayerTrait;
    
    private $fromLatAttributeAlias = null;
    
    private $fromLatColumn = null;
    
    private $toLatAttributeAlias = null;
    
    private $toLatColumn = null;
    
    private $toLngAttributeAlias = null;
    
    private $toLngColumn = null;
    
    private $fromLngAttributeAlias = null;
    
    private $fromLngColumn = null;
    
    private $width = 3;
    
    /**
     *
     * @return string
     */
    public function getFromLongitudeAttributeAlias() : string
    {
        return $this->fromLngAttributeAlias;
    }
    
    /**
     * Alias of the attribute that will contain the latitude of the beginning of a line
     *
     * @uxon-property from_longitude_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setFromLongitudeAttributeAlias(string $value) : MapLayerInterface
    {
        $this->fromLngAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getFromLongitudeColumn() : DataColumn
    {
        return $this->fromLngColumn;
    }
    
    /**
     *
     * @return string
     */
    public function getToLongitudeAttributeAlias() : string
    {
        return $this->toLngAttributeAlias;
    }
    
    /**
     * Alias of the attribute that will contain the latitude of the beginning of a line
     *
     * @uxon-property to_longitude_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setToLongitudeAttributeAlias(string $value) : MapLayerInterface
    {
        $this->toLngAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getToLongitudeColumn() : DataColumn
    {
        return $this->toLngColumn;
    }
    
    /**
     *
     * @return string
     */
    public function getToLatitudeAttributeAlias() : string
    {
        return $this->toLatAttributeAlias;
    }
    
    /**
     * Alias of the attribute that will contain the latitude of the beginning of a line
     *
     * @uxon-property to_latitude_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setToLatitudeAttributeAlias(string $value) : MapLayerInterface
    {
        $this->toLatAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getToLatitudeColumn() : DataColumn
    {
        return $this->toLatColumn;
    }
    
    /**
     *
     * @return string
     */
    public function getFromLatitudeAttributeAlias() : string
    {
        return $this->fromLatAttributeAlias;
    }
    
    /**
     * Alias of the attribute that will contain the latitude of the beginning of a line
     *
     * @uxon-property from_latitude_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setFromLatitudeAttributeAlias(string $value) : MapLayerInterface
    {
        $this->fromLatAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getFromLatitudeColumn() : DataColumn
    {
        return $this->fromLatColumn;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = parent::initDataWidget($widget);
        if ($attrAlias = $this->getFromLatitudeAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($attrAlias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $attrAlias,
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->fromLatColumn = $col;
        }
        if ($attrAlias = $this->getToLatitudeAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($attrAlias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $attrAlias,
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->toLatColumn = $col;
        }
        if ($attrAlias = $this->getFromLongitudeAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($attrAlias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $attrAlias,
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->fromLngColumn = $col;
        }
        if ($attrAlias = $this->getToLongitudeAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($attrAlias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $attrAlias,
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->toLngColumn = $col;
        }
        
        return $widget;
    }
    
    /**
     * @return integer
     */
    public function getWidth() : int
    {
        return $this->width;
    }

    /**
     * Set the line width in pixel. Default is 3.
     * 
     * @uxon-property width
     * @uxon-type integer
     * @uxon-default 3
     * 
     * @param integer $width
     */
    public function setWidth(int $width)
    {
        $this->width = $width;
    }

}