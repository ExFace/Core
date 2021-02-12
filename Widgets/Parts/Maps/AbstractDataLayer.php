<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

/**
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractDataLayer extends AbstractMapLayer
{
    private $dataWidget = null;
    
    private $dataUxon = null;
    
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
            $this->dataWidget = $this->initDataWidget(WidgetFactory::createFromUxonInParent($this->getMap(), $this->dataUxon, 'Data'));
        }
        return $this->dataWidget;
    }
    
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
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface::getWidgets()
     */
    public function getWidgets() : \Generator
    {
        yield $this->getDataWidget();
    }
    
    public function prepareDataSheetToRead(DataSheetInterface $sheet): DataSheetInterface
    {
        return $this->getDataWidget()->prepareDataSheetToRead($sheet);
    }

    public function prepareDataSheetToPrefill(DataSheetInterface $sheet): DataSheetInterface
    {
        return $this->getDataWidget()->prepareDataSheetToPrefill($sheet);
    }

}