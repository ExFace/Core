<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Events\Widget\OnUiPageInitializedEvent;

/**
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractDataLayer extends AbstractMapLayer implements iUseData
{
    private $dataWidget = null;
    
    private $dataUxon = null;
    
    private $dataWidgetLink = null;
    
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('data', $this->getDataWidget()->exportUxonObject());
        return $uxon;
    }
    
    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface
    {
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
                $data = $this->createDataWidget($this->dataUxon ?? (new UxonObject()));
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
     * @return DataMarkersLayer
     */
    public function setData(UxonObject $uxon) : DataMarkersLayer
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
     * @uxon-property data_widget_link
     * @uxon-type string
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
        $this->getWorkbench()->eventManager()->addListener(OnUiPageInitializedEvent::getEventName(), function(OnUiPageInitializedEvent $event) {
            $this->getDataWidget();
        });
        
        return $this;
    }
}