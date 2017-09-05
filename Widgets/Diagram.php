<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

/**
 * Widget to display diagrams like planograms, entity-relationships, organigrams, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class Diagram extends Container implements iSupportLazyLoading
{

    private $lazy_loading = false;

    // A diagram will not be loaded via AJAX by default
    private $lazy_loading_action = 'exface.Core.ReadData';

    private $diagram_options_attribute_alias = null;

    private $background_image = null;

    private $background_image_attribute_alias = null;

    private $scale = null;

    private $diagram_object_selector_widget = null;

    private $lazy_loading_group_id = null;

    /**
     * Returns an array of shapes usable in this diagram.
     * Keep in mind, that these are not the actually plotted instances of
     * shapes, but rather "types of shapes".
     *
     * @return DiagramShape[]
     */
    public function getShapes()
    {
        return $this->getWidgets();
    }

    public function setShapes($array_of_uxon_or_widgets)
    {
        $shapes = array();
        foreach ($array_of_uxon_or_widgets as $shape) {
            if ($shape instanceof \stdClass) {
                $uxon = UxonObject::fromAnything($shape);
                if (! $uxon->getProperty('widget_type')) {
                    $uxon->setProperty('widget_type', 'DiagramShape');
                }
                $shapes[] = $uxon;
            } elseif ($shape instanceof DiagramShape) {
                $shapes[] = $shape;
            } else {
                throw new WidgetPropertyInvalidValueError($this, 'Wrong data type for diagram shape: Shapes must be defined as UXON objects or widgets of type DiagramShape: "' . get_class($shape) . '" given!', '6T910B6');
            }
        }
        $this->setWidgets($shapes);
        return $this;
    }

    public function getDiagramOptionsAttributeAlias()
    {
        return $this->diagram_options_attribute_alias;
    }

    public function setDiagramOptionsAttributeAlias($value)
    {
        $this->diagram_options_attribute_alias = $value;
        return $this;
    }

    public function getBackgroundImage()
    {
        return $this->background_image;
    }

    public function setBackgroundImage($value)
    {
        $this->background_image = $value;
        return $this;
    }

    public function getBackgroundImageAttributeAlias()
    {
        return $this->background_image_attribute_alias;
    }

    public function setBackgroundImageAttributeAlias($value)
    {
        $this->background_image_attribute_alias = $value;
        return $this;
    }

    /**
     *
     * @return array ["width" => XX, "height" => YY, "unit" => "cm"]
     */
    public function getScale()
    {
        return $this->scale;
    }

    public function setScale($value)
    {
        $this->scale = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading()
    {
        return $this->lazy_loading;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        $this->lazy_loading = $value;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
    {
        return $this->lazy_loading_action;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction($value)
    {
        $this->lazy_loading_action = $value;
        return $this;
    }

    public function getDiagramObjectSelectorWidget()
    {
        if (is_null($this->diagram_object_selector_widget)) {
            $this->setDiagramObjectSelectorWidget($this->getWorkbench()->createUxonObject());
        }
        
        return $this->diagram_object_selector_widget;
    }

    public function setDiagramObjectSelectorWidget($widget_or_uxon)
    {
        if ($widget_or_uxon instanceof \stdClass) {
            // $this->diagram_object_selector_widget = $this->getPage()->createWidget('Filter', $this);
            /* @var $widget \exface\Core\Widgets\ComboTable */
            
            $widget_or_uxon->widget_type = $widget_or_uxon->widget_type ? $widget_or_uxon->widget_type : 'ComboTable';
            $widget = $this->getPage()->createWidget($widget_or_uxon->widget_type, $this, $widget_or_uxon);
            $widget->setMetaObject($this->getMetaObject());
            $widget->setAttributeAlias($this->getMetaObject()->getUidAttributeAlias());
            $widget->setTableObjectAlias($this->getMetaObject()->getAliasWithNamespace());
            $widget->setCaption($this->getMetaObject()->getName());
            $widget->setDisabled(false);
            // $this->diagram_object_selector_widget->setWidget($widget);
        } elseif ($widget_or_uxon instanceof WidgetInterface) {
            $widget = $widget_or_uxon;
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid diagram selector widget!', '6T910B6');
        }
        $this->diagram_object_selector_widget = $widget;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Container::getChildren()
     */
    public function getChildren()
    {
        return array_merge(parent::getChildren(), array(
            $this->getDiagramObjectSelectorWidget()
        ));
    }

    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
            
            try {
                $attr = $this->getMetaObject()->getAttribute($this->getBackgroundImageAttributeAlias());
                $data_sheet->getColumns()->addFromAttribute($attr);
            } catch (MetaAttributeNotFoundError $e) {
                // Nothing to prefill if it's not an attribute
            }
        }
        return $data_sheet;
    }

    public function getLazyLoadingGroupId()
    {
        return $this->lazy_loading_group_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingGroupId()
     */
    public function setLazyLoadingGroupId($value)
    {
        $this->lazy_loading_group_id = $value;
        return $this;
    }
}

?>
