<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Events\Widget\OnUiPageInitEvent;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\DataMapLayerInterface;

/**
 * Base implementation for a map layer showing data
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractDataLayer extends AbstractMapLayer implements DataMapLayerInterface
{
    private ?iShowData $dataWidget = null;
    private ?WidgetLinkInterface $dataWidgetLink = null;
    private ?UxonObject $dataUxon = null;
    private ?MetaObjectInterface $object = null;
    
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('data', $this->getDataWidget()->exportUxonObject());
        return $uxon;
    }
    
    /**
     * {@inheritDoc}
     * @see DataMapLayerInterface::getMetaObject()
     */
    public function getMetaObject() : MetaObjectInterface
    {
        if ($this->object !== null) {
            return $this->object;
        } 
        return $this->dataWidget !== null ? $this->dataWidget->getMetaObject() : parent::getMetaObject();
    }
    
    /**
     * 
     * @return iShowData
     */
    public function getDataWidget() : iShowData
    {
        if ($this->dataWidget === null) {
            if ($link = $this->getDataWidgetLink()) {
                try {
                    $data = $link->getTargetWidget();
                } catch (\Throwable $e) {
                    throw new WidgetConfigurationError($this->getMap(), 'Error instantiating map layer data. ' . $e->getMessage(), null, $e);
                }
            } else {
                $uxon = $this->dataUxon ?? (new UxonObject());
                if (! $uxon->hasProperty('object_alias') && $this->object !== null) {
                    $uxon->setProperty('object_alias', $this->object->getAliasWithNamespace());
                }
                $data = $this->createDataWidget($uxon);
            }
            $this->dataWidget = $this->initDataWidget($data);
        }
        return $this->dataWidget;
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @return iShowData
     */
    protected function createDataWidget(UxonObject $uxon) : iShowData
    {
        return WidgetFactory::createFromUxonInParent($this->getMap(), $uxon, 'Data');
    }
    
    /**
     * 
     * @param iShowData $widget
     * @return iShowData
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        return $widget;
    }
    
    /**
     * The data to be used in this layer
     * 
     * @uxon-property data
     * @uxon-type \exface\Core\Widgets\Data
     * @uxon-template {"":""}
     * 
     * @param UxonObject $uxon
     * @return iUseData
     */
    public function setData(UxonObject $uxon) : iUseData
    {
        $this->dataUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseData::getData()
     */
    public function getData()
    {
        return $this->getDataWidget();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractMapLayer::getWidgets()
     */
    public function getWidgets() : \Generator
    {
        yield $this->getDataWidget();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractMapLayer::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $sheet): DataSheetInterface
    {
        return $this->getDataWidget()->prepareDataSheetToRead($sheet);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractMapLayer::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $sheet): DataSheetInterface
    {
        return $this->getDataWidget()->prepareDataSheetToPrefill($sheet);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseData::getDataWidgetLink()
     */
    public function getDataWidgetLink()
    {
        return $this->dataWidgetLink;
    }
    
    /**
     * The id of the widget to take the data from instead of loading the data explicitly for the map.
     *
     * This is very handy if you want to visualize the data presented by a table or so. 
     * Using the link will make the chart automatically react to filters and other setting 
     * of the target data widget.
     * 
     * However, the map will show ONLY what is visible in the linked widget. In particular, if linked to a
     * table with pagination, the map will only show the current page. This may or may not be the expected
     * behavior. If you want the map to show all data without pagination, use a linked configurator instead.
     * This will load all data matching the filters of the linked widget into the map - not pagination, but
     * it will also mean, the map will perform its own ReadData request.
     * 
     * ```
     * {
     *      "type": "DataMarkers",
     *      "object_alias": "// object of the linked widget",
     *      "data": {
     *          "configurator_widget_link": "=IdOfTable"
     *      }   
     * }
     * 
     * ```
     * 
     * @uxon-property data_widget_link
     * @uxon-type uxon:$..id
     *
     * @see \exface\Core\Interfaces\Widgets\iUseData::setDataWidgetLink()
     */
    public function setDataWidgetLink($widgetId)
    {
        $this->dataWidgetLink = WidgetLinkFactory::createFromWidget($this->getMap(), $widgetId);
        $this->dataWidget = null;
        
        // Call getDataWidget() once the page is fully initialized to make sure
        // additional columns are created even if getDataWidget() is not called
        // later explicitly (which won't happen when reading linked data for
        // example as the data widget does not know anything about the link).
        $this->getWorkbench()->eventManager()->addListener(OnUiPageInitEvent::getEventName(), function(OnUiPageInitEvent $event) {
            $this->getDataWidget();
        });
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        $caption = parent::getCaption();
        if (! $this->getHideCaption()) {
            if ($caption === null) {
                $caption = $this->getDataWidget()->getMetaObject()->getName();
            }
        }
        return $caption;
    }

    /**
     * Alias or UID of the object to be used in this data layers
     * 
     * By default, data layers will be based on the object of the map itself. However, they
     * can also have their own objects if the use `data`, `data_widget_link`, etc.
     * 
     * If linking the layer to other widgets data, make sure to use the same `object_alias`
     * in the layer and in the linked widget!
     * 
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * 
     * @param string $aliasOrUid
     * @return DataMapLayerInterface
     */
    protected function setObjectAlias(string $aliasOrUid) : DataMapLayerInterface
    {
        $this->object = MetaObjectFactory::createFromString($this->getWorkbench(), $aliasOrUid);
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see AbstractMapLayer::supportsAutoZoom()
     */
    public function supportsAutoZoom() : bool
    {
        return true;
    }
}