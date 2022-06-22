<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\MarkerMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\DataPointLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngDataColumnMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngWidgetLinkMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\EditableMapLayerInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class DataMarkersLayer extends AbstractDataLayer 
    implements 
    MarkerMapLayerInterface, 
    LatLngDataColumnMapLayerInterface, 
    LatLngWidgetLinkMapLayerInterface, 
    EditableMapLayerInterface,
    iHaveIcon, 
    iHaveColorScale
{
    use DataPointLayerTrait {
        initDataWidget as initDataWidgetForPoints;
    }
    
    use iHaveIconTrait;
    
    use iHaveColorTrait;
    
    use iHaveColorScaleTrait;
    
    private $valueAttributeAlias = null;
    
    private $valueColumn = null;
    
    /**
     * 
     * @return string|NULL
     */
    public function getValueAttributeAlias() : ?string
    {
        return $this->valueAttributeAlias;
    }
    
    /**
     * Alias of the attribtue containing the data to show inside the marker (typically a number)
     *
     * @uxon-property value_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return DataMarkersLayer
     */
    public function setValueAttributeAlias(string $value) : DataMarkersLayer
    {
        $this->valueAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasValue() : bool
    {
        return $this->getValueAttributeAlias() !== null;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getValueColumn() : ?DataColumn
    {
        return $this->valueColumn;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = $this->initDataWidgetForPoints($widget);
        
        if ($this->getValueAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($this->getValueAttributeAlias())) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->getValueAttributeAlias(),
                    'visibility' => WidgetVisibilityDataType::PROMOTED
                ]));
                $widget->addColumn($col, 0);
            }
            $this->valueColumn = $col;
        }
        
        return $widget;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::isColorScaleRangeBased()
     */
    public function isColorScaleRangeBased() : bool
    {
        if (! $this->hasValue()) {
            return false;
        }
        $dataType = $this->getValueColumn()->getDataType();
        switch (true) {
            case $dataType instanceof NumberDataType:
            case $dataType instanceof DateDataType:
                return true;
        }
        
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MarkerMapLayerInterface::isClusteringMarkers()
     */
    public function isClusteringMarkers() : ?bool
    {
        return $this->clustering ?? ($this->isEditable() ? false : null);
    }
    
    /**
     * Set to TRUE to group markers to clusters or to FALSE to disable clustering explicitly.
     * 
     * By default, the facade will decide itself, if clustering is appropriate for this layer.
     * 
     * @uxon-property cluster_markers
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return DataMarkersLayer
     */
    public function setClusterMarkers(bool $value) : DataMarkersLayer
    {
        $this->clustering = $value;
        return $this;
    }
    
    /**
     * @deprecated use the generic setEditByAddingItems()
     * @param bool $value
     * @return DataMarkersLayer
     */
    protected function setAllowToAddMarkers(bool $value) : DataMarkersLayer
    {
        return $this->setEditByAddingItems($value);
    }
    
    /**
     * @deprecated use the generic setEditByMovingItems()
     * @param bool $value
     * @return DataMarkersLayer
     */
    protected function setAllowToMoveMarkers(bool $value) : DataMarkersLayer
    {
        return $this->setEditByMovingItems($value);
    }
}