<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetLogicError;

/**
 * The KPI widget shows a numeric value with a unit and a scale - especially usefull in dashboards.
 * 
 * It is basically a special Display widget for numeric values. You can add a `scale` to
 * improve the readability: e.g. a `scale` of 2000 will turn 2000 into 1. Facades are 
 * encouraged to auto-scale numbers for better readability: e.g. 1520 -> 1,52K or 1200000 -> 12M.
 * 
 * You can also specify a mesurement `unit` - e.g. kg, mm, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class KPI extends Display implements iUseData
{
    
    /**
     * @var Data
     */
    private $data = null;
    
    /**
     * @var WidgetLinkInterface|NULL
     */
    private $data_widget_link = null;
    
    private $unit = null;
    
    private $scale = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseData::getDataWidgetLink()
     */
    public function getDataWidgetLink()
    {
        return $this->data_widget_link;
    }
    
    /**
     * If a valid link to another data widget is specified, it's data will be used instead of the data property of the KPI itself.
     *
     * This is very handy if you want to visualize the data presented by a table or if you have multiple KPIs, 
     * that can use the same data - bundling them greatly improves performance! 
     * 
     * @uxon-property data_widget_link
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iUseData::setDataWidgetLink()
     */
    public function setDataWidgetLink($value)
    {
        $this->data_widget_link = WidgetLinkFactory::createFromWidget($this, $value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseData::getData()
     */
    public function getData() : iShowData
    {
        if ($this->data === null) {
            if ($link = $this->getDataWidgetLink()) {
                try {
                    $this->data = $link->getTargetWidget();
                } catch (\Throwable $e) {
                    $this->data = null;
                    throw new WidgetConfigurationError($this, 'Error instantiating KPI data. ' . $e->getMessage(), null, $e);
                }
            } else {
                $this->data = WidgetFactory::createFromUxonInParent($this, new UxonObject(['columns_auto_add_default_display_attributes' => false]), 'Data');
            }
        }
        
        return $this->data;
    }
    
    /**
     * Configure the data used for the KPI by specifying filters and aggregations.
     *
     * ## Typical examples
     *
     * Add filters:
     *
     * ```
     * {
     *  "widget_type": "KPI",
     *  "data": {
     *      "filters": [
     *           {
     *              "attribute_alias": ""
     *           }
     *      ]
     *  }
     * }
     *
     * ```
     *
     * @uxon-property data
     * @uxon-type \exface\Core\Widgets\Data
     * @uxon-template {"": ""}
     *
     * @see \exface\Core\Interfaces\Widgets\iUseData::setData()
     */
    public function setData(UxonObject $uxon_object)
    {
        /* @var \exface\Core\Widgets\Data $data */
        $data = WidgetFactory::create($this->getPage(), 'Data', $this);
        $data->setColumnsAutoAddDefaultDisplayAttributes(false);
        $data->setMetaObject($this->getMetaObject());
        $data->importUxonObject($uxon_object);
        $data->getToolbarMain()->setIncludeNoExtraActions(true);
        $data->getPaginator()->setCountAllRows(false);
        
        $data->setAggregateAll(true);
        $data->addColumn($data->createColumnFromUxon(new UxonObject([
            'attribute_alias' => $this->getAttributeAlias()
        ])));
        
        $this->data = $data;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        if ($this->hasData() === true) {
            return $this->getData()->prepareDataSheetToPrefill($data_sheet);
        }
        return parent::prepareDataSheetToPrefill($data_sheet);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $dataSheet = null)
    {
        if ($this->hasData() === true) {
            return $this->getData()->prepareDataSheetToRead($dataSheet);
        }
        return parent::prepareDataSheetToRead($dataSheet);
    }
    
    /**
     * A KPI can be prefilled just like a data widgets, but only if it has it's own data. If the data is fetched from
     * a linked widget, the prefill does not make sense and will be ignored. But the linked widget will surely be 
     * prefilled, so the the KPI will get the correct data anyway.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Data::prefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        if ($this->getDataWidgetLink()) {
            return parent::doPrefill($data_sheet);
        } elseif ($this->hasData() === true) {
            return $this->getData()->prefill($data_sheet);
        }
        return parent::doPrefill($data_sheet);
    }
    
    /**
     * 
     * @throws WidgetLogicError
     * @return DataColumn
     */
    public function getKpiDataColumn() : DataColumn
    {
        if ($this->hasData() === false) {
            throw new WidgetLogicError($this, 'Cannot get a data column for a KPI widget, that is not linked to data!');
        }
        return $this->getData()->getColumnByAttributeAlias($this->getAttributeAlias());
    }
    
    /**
     * Returns TRUE if the KPI is linked to a Data widget - either by data_widget_link or via
     * it's own data property - and FALSE if it will use it's parent data like a regular Display.
     * 
     * @return bool
     */
    public function hasData() : bool
    {
        return $this->data !== null || $this->data_widget_link !== null;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getUnit() : ?string
    {
        return $this->unit;
    }
    
    /**
     * A unit to be displayed next to the number: e.g. kg, mm, etc.
     * 
     * @uxon-property unit
     * @uxon-type string
     * 
     * @param string $value
     * @return KPI
     */
    public function setUnit(string $value) : KPI
    {
        $this->unit = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getScale() : int
    {
        return $this->scale ?? 1;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasScale() : bool
    {
        return $this->scale !== null;
    }
    
    /**
     * Set to 1000 to show the value in thousands: e.g. 2500 -> 2,5.
     * 
     * If not set explicitly, most facades will use auto-scaling.
     * 
     * @uxon-property scale
     * @uxon-type int
     * 
     * @param int $value
     * @return KPI
     */
    public function setScale(int $value) : KPI
    {
        $this->scale = $value;
        return $this;
    }
}