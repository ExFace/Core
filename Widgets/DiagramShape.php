<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveBorders;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Shapes are what diagrams show: e.g.
 * a blue rectangle. The diagram will show as many rectangles as rows in it's data subwidget.
 *
 * In an editable diagram, there would typically be a toolbar with shapes, that can be dragged on the canvas. Each of them is a DiagramShape widget.
 * Dropping a shape onto the diagram would create an instance of it and fill that instance with data (title, attributes, etc.). The instances of
 * a shape are accessible via the data() subwidget.
 *
 * @author Andrej Kabachnik
 *        
 */
class DiagramShape extends Form implements iUseData, iHaveBorders
{

    const SHAPE_TYPE_POLYGON = 'polygon';

    const SHAPE_CIRCLE = 'circle';

    const SHAPE_DONUT = 'donut';

    private $shape_options_attribute_alias = null;

    private $shape_caption_attribute_alias = null;

    private $shape_type = SHAPE_TYPE_POLYGON;

    private $coordinates = null;

    private $background_color = null;

    private $show_border = true;

    private $show_border_color = null;

    private $transparency = null;

    private $data = null;

    private $data_widget_link = null;

    public function getShapeOptionsAttributeAlias()
    {
        return $this->shape_options_attribute_alias;
    }

    public function setShapeOptionsAttributeAlias($value)
    {
        $this->shape_options_attribute_alias = $value;
        return $this;
    }

    public function getShapeType()
    {
        return $this->shape_type;
    }

    public function setShapeType($value)
    {
        $this->shape_type = $value;
        return $this;
    }

    public function getBackgroundColor()
    {
        return $this->background_color;
    }

    public function setBackgroundColor($value)
    {
        $this->background_color = $value;
        return $this;
    }

    public function getTransparency()
    {
        return $this->transparency;
    }

    public function setTransparency($value)
    {
        $this->transparency = $value;
        return $this;
    }

    /**
     *
     * @return Data
     */
    public function getData()
    {
        if (is_null($this->data)) {
            if ($link = $this->getDataWidgetLink()) {
                return $link->getTargetWidget();
            } else {
                throw new WidgetConfigurationError($this, 'Cannot get data for ' . $this->getWidgetType() . ' "' . $this->getId() . '": either data or data_widget_link must be defined in the UXON description!', '6T90WFX');
            }
        }
        return $this->data;
    }

    public function getDataWidgetLink()
    {
        return $this->data_widget_link;
    }

    public function setDataWidgetLink($value)
    {
        $this->data_widget_link = WidgetLinkFactory::createFromWidget($this, $value);
        return $this;
    }

    public function setData(UxonObject $uxon_object)
    {
        // Force the data to be a DiagramShapeData widget
        $data = $this->getPage()->createWidget('DiagramShapeData', $this);
        $uxon_object->unsetProperty('widget_type');
        // Import it's uxon definition
        $data->importUxonObject($uxon_object);
        $this->data = $data;
    }

    public function getCoordinates()
    {
        return $this->coordinates;
    }

    public function setCoordinates($uxon_object)
    {
        $uxon = UxonObject::fromAnything($uxon_object);
        $this->coordinates = $uxon;
        return $this;
    }

    public function getShowBorder()
    {
        return $this->show_border;
    }

    public function setShowBorder($value)
    {
        $this->show_border = $value;
        return $this;
    }

    public function getShowBorderColor()
    {
        return $this->show_border_color;
    }

    public function setShowBorderColor($value)
    {
        $this->show_border_color = $value;
        return $this;
    }

    public function getChildren() : \Iterator
    {
        foreach (parent::getChildren() as $child) {
            yield $child;
        }
        yield $this->getData();
    }

    /**
     *
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getShapeOptionsAttribute()
    {
        return $this->getMetaObject()->getAttribute($this->getShapeOptionsAttributeAlias());
    }

    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        if ($this->getMetaObject()->is($data_sheet->getMetaObject()) && $this->getShapeOptionsAttribute()) {
            if ($this->getShapeOptionsAttributeAlias()) {
                $data_sheet->getColumns()->addFromAttribute($this->getShapeOptionsAttribute());
            }
            if ($this->getShapeCaptionAttributeAlias()) {
                $data_sheet->getColumns()->addFromAttribute($this->getShapeCaptionAttribute());
            }
        } else {
            // TODO
        }
        return $data_sheet;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Model\RelationPath
     */
    public function getRelationPathToDiagramObject()
    {
        return $this->getObjectRelationPathToParent();
    }

    /**
     *
     * @return Diagram
     */
    public function getDiagram()
    {
        return $this->getParent();
    }

    public function getShapeCaptionAttributeAlias()
    {
        return $this->shape_caption_attribute_alias;
    }

    /**
     *
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getShapeCaptionAttribute()
    {
        return $this->getMetaObject()->getAttribute($this->getShapeCaptionAttributeAlias());
    }

    /**
     *
     * @param string $value            
     * @return \exface\Core\Widgets\DiagramShape
     */
    public function setShapeCaptionAttributeAlias($value)
    {
        $this->shape_caption_attribute_alias = $value;
        return $this;
    }
}

?>
