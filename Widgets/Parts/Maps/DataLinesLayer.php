<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\DataTypes\NumberDataType;

/**
 * Draws a straight line between coordinates in a data row
 * 
 * @author Andrej Kabachnik
 *
 */
class DataLinesLayer extends AbstractDataLayer
    implements
    ColoredDataMapLayerInterface
{
    
    use iHaveColorTrait;
    
    use iHaveColorScaleTrait;
    
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
     * Alias of the attribtue that will contain the latitude of the beginning of a line
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
     * Alias of the attribtue that will contain the latitude of the beginning of a line
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
     * Alias of the attribtue that will contain the latitude of the beginning of a line
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
     * Alias of the attribtue that will contain the latitude of the beginning of a line
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
    
    public function getColorColumn(): ?DataColumn
    {
        return null;
    }

    public function isColorScaleRangeBased(): bool
    {
        if ($this->getColorColumn() === null) {
            return false;
        }
        return $this->getColorColumn()->getDataType() instanceof NumberDataType;
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