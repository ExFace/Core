<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iHaveFooter;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\Parts\Charts\ChartAxis;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Widgets\Traits\iHaveConfiguratorTrait;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;

/**
 * A Chart widget draws a chart with upto two axis and any number of series.
 * 
 * Every Chart contains a Data widget, that fetches data visualized by the chart.
 * Chart series as well as axis legends are extracted for columns in that data.
 *
 * @author Andrej Kabachnik
 *        
 */
class Chart extends AbstractWidget implements 
    iUseData, 
    iHaveToolbars, 
    iHaveButtons, 
    iHaveHeader, 
    iHaveFooter, 
    iHaveConfigurator, 
    iSupportLazyLoading, 
    iFillEntireContainer
{
    use iHaveButtonsAndToolbarsTrait;
    use iSupportLazyLoadingTrait;
    use iHaveConfiguratorTrait {
        setConfiguratorWidget as setConfiguratorWidgetViaTrait;
    }

    const AXIS_X = 'x';

    const AXIS_Y = 'y';
    
    private $autoload_disabled_hint = null;
    
    /**
     * @var ChartAxis[]
     */
    private $axes_x = array();

    /**
     * @var ChartAxis[]
     */
    private $axes_y = array();

    /**
     * @var ChartSeries[]
     */
    private $series = array();

    /**
     * @var Data
     */
    private $data = null;

    /**
     * @var WidgetLinkInterface|NULL
     */
    private $data_widget_link = null;
    
    /**
     * @var string
     */
    private $legendPosition = null;
    
    /**
     * @var bool
     */
    private $legenPositionInsideChart = null;

    /**
     * @var bool
     */
    private $hide_header = false;

    /**
     *
     * @var bool
     */
    private $hide_footer = false;
    
    /**
     * @var bool
     */
    private $dataPrepared = false;
    
    private $empty_text = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getConfiguratorWidget();
        
        if (! $this->getDataWidgetLink()) {
            yield $this->getData();
        }
        
        foreach ($this->getToolbars() as $toolbar) {
            yield $toolbar;
        }
    }

    /**
     *
     * @return ChartAxis[]
     */
    public function getAxesX() : array
    {
        return $this->axes_x;
    }

    /**
     * Sets X-axes of the chart.
     *
     * @uxon-property axis_x
     * @uxon-type \exface\Core\Widgets\Parts\Charts\ChartAxis[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @param ChartAxis|UxonObject $axis_or_uxon_object            
     * @return \exface\Core\Widgets\Chart
     */
    public function setAxisX($axis_or_uxon_object) : Chart
    {
        if ($axis_or_uxon_object instanceof ChartAxis) {
            $this->addAxis(static::AXIS_X, $axis_or_uxon_object);
        } elseif ($axis_or_uxon_object instanceof UxonObject) {
            if ($axis_or_uxon_object->isArray()) {
                foreach ($axis_or_uxon_object as $axis) {
                    $this->setAxisX($axis);
                }
            } else {
                $axis = new ChartAxis($this, $axis_or_uxon_object);
                $this->addAxis(static::AXIS_X, $axis);
            }
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot set X axis of ' . $this->getWidgetType() . ': expecting instantiated axis widget or its UXON description or an array of UXON descriptions for multiple axes - ' . gettype($axis_or_uxon_object) . ' given instead!');
        }
        return $this;
    }

    /**
     *
     * @return ChartAxis[]
     */
    public function getAxesY() : array
    {
        return $this->axes_y;
    }

    /**
     *
     * @return ChartAxis[]
     */
    public function getAxes($x_or_y = null) : array
    {
        switch ($x_or_y) {
            case $this::AXIS_X:
                return $this->getAxesX();
            case $this::AXIS_Y:
                return $this->getAxesY();
            default:
                return array_merge($this->getAxesX(), $this->getAxesY());
        }
    }

    /**
     * Sets Y-axes of the chart.
     *
     * @uxon-property axis_y
     * @uxon-type \exface\Core\Widgets\Parts\Charts\ChartAxis[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @param ChartAxis|UxonObject $axis_or_uxon_object_or_array            
     * @return \exface\Core\Widgets\Chart
     */
    public function setAxisY($axis_or_uxon_object) : Chart
    {
        if ($axis_or_uxon_object instanceof ChartAxis) {
            $this->addAxis(static::AXIS_Y, $axis_or_uxon_object);
        } elseif ($axis_or_uxon_object instanceof UxonObject) {
            if ($axis_or_uxon_object->isArray()) {
                foreach ($axis_or_uxon_object as $axis) {
                    $this->setAxisY($axis);
                }
            } else {
                $axis = new ChartAxis($this, $axis_or_uxon_object);
                $this->addAxis(static::AXIS_Y, $axis);
            }
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot set Y axis of ' . $this->getWidgetType() . ': expecting instantiated axis widget or its UXON description or an array of UXON descriptions for multiple axes - ' . gettype($axis_or_uxon_object) . ' given instead!');
        }
        return $this;
    }
    
    /**
     * 
     * @param ChartAxis $axis
     * @return Chart
     */
    public function addAxisX(ChartAxis $axis) : Chart
    {
        return $this->addAxis(static::AXIS_X, $axis);
    }
    
    /**
     * 
     * @param ChartAxis $axis
     * @return Chart
     */
    public function addAxisY(ChartAxis $axis) : Chart
    {
        return $this->addAxis(static::AXIS_Y, $axis);
    }

    /**
     * 
     * @param string $x_or_y
     * @param ChartAxis $axis
     * @throws WidgetPropertyInvalidValueError
     * @return Chart
     */
    public function addAxis(string $x_or_y, ChartAxis $axis) : Chart
    {
        $var = 'axes_' . $x_or_y;
        array_push($this->$var, $axis);
        return $this;
    }
    
    /**
     * 
     * @param ChartAxis $axis
     * @throws WidgetLogicError
     * @return int
     */
    public function getAxisIndex(ChartAxis $axis) : int
    {
        $idx = array_search($axis, $this->axes_x, true);
        if ($idx !== false) {
            return $idx;
        }
        
        $idx = array_search($axis, $this->axes_y, true);
        if ($idx !== false) {
            return $idx;
        }
        
        throw new WidgetLogicError($this, 'Axis not "' . $axis->getCaption() . '" found in chart "' . $this->getId() . '"!');
    }
    
    /**
     * 
     * @param ChartAxis $axis
     * @throws WidgetLogicError
     * @return string
     */
    public function getAxisDimension(ChartAxis $axis) : string
    {
        $idx = array_search($axis, $this->axes_x, true);
        if ($idx !== false) {
            return static::AXIS_X;
        }
        
        $idx = array_search($axis, $this->axes_y, true);
        if ($idx !== false) {
            return static::AXIS_Y;
        }
        
        throw new WidgetLogicError($this, 'Axis "' . $axis->getCaption() . '" not found in chart "' . $this->getId() . '"!');
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
                    throw new WidgetConfigurationError($this, 'Error instantiating chart widget data. ' . $e->getMessage(), null, $e);
                }
            } else {
                $this->data = WidgetFactory::createFromUxonInParent($this, new UxonObject(['columns_auto_add_default_display_attributes' => false]), 'Data');
            }
            
            if ($this->dataPrepared === false) {
                $this->prepareDataWidget($this->data);
                $this->dataPrepared = true;
            }
        }
        
        return $this->data;
    }
    
    /**
     * Makes sure, the given widget includes columns required for the chart.
     * 
     * @param iShowData $dataWidget
     * @return Chart
     */
    protected function prepareDataWidget(iShowData $dataWidget) : Chart
    {
        foreach ($this->getSeries() as $series) {
            $series->prepareDataWidget($dataWidget);
        }
        
        foreach ($this->getAxes() as $axis) {
            $axis->prepareDataWidget($dataWidget);
        }
        
        return $this;
    }

    /**
     * Configure the data used for the chart: filters, pagination, sorting, etc.
     *
     * If not specified explicitly, the data widget will be created automatically using
     * the information from chart series and axes.
     * 
     * Use an explicitly defined data widget to specify filters, sorters, aggregations, etc.
     * In most cases, you do not need to specify any columns - they will be added automatically.
     * 
     * ## Typical examples
     * 
     * Disable pagination:
     * 
     * ```
     * {
     *  "widget_type": "Chart",
     *  "data": {
     *      "paginate": false
     *  }
     * }
     * 
     * ```
     * 
     * Add filters and sorters:
     * 
     * ```
     * {
     *  "widget_type": "Chart",
     *  "data": {
     *      "filters": [
     *           {
     *              "attribute_alias": ""
     *           }
     *      ],
     *      "sorters": [
     *           {
     *              "attribute_alias": "",
     *              "direction": "asc"
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
        $data = WidgetFactory::create($this->getPage(), 'Data', $this);
        $data->setColumnsAutoAddDefaultDisplayAttributes(false);
        $data->setMetaObject($this->getMetaObject());
        $data->importUxonObject($uxon_object);
        // Do not add action automatically as the internal data toolbar will
        // not be shown anyway. The Chart has it's own toolbars.
        // IDEA why create two sets of toolbars? Maybe we can reuse the data
        // toolbars in the chart?
        $data->getToolbarMain()->setIncludeNoExtraActions(true);
        
        $this->data = $data;
        return $this;
    }

    /**
     * Returns the first axis based on the given column.
     * 
     * @param string $column_id            
     * @param string $x_or_y            
     * @return ChartAxis|NULL
     */
    public function findAxis(DataColumn $column, string $x_or_y = null) : ?ChartAxis
    {
        foreach ($this->getAxes($x_or_y) as $axis) {
            try {
                if ($axis->getDataColumn() === $column) {
                    return $axis;
                }
            } catch (\Throwable $e) {
                if ($this->dataPrepared === true) {
                    throw $e;
                }
            }
        }
        return null;
    }
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @param string $dimension
     * @return ChartAxis[]
     */
    public function findAxesByAttribute(MetaAttributeInterface $attribute, string $dimension = null) : array
    {
        $result = [];
        foreach ($this->getAxes($dimension) as $axis) {
            try {
                if ($axis->isBoundToAttribute() === true && $axis->getAttribute()->is($attribute)) {
                    $result[] = $axis;
                }
            } catch (\Throwable $e) {
                if ($this->dataPrepared === true) {
                    throw $e;
                }
            }
        }
        return $result;
    }

    /**
     *
     * @return ChartSeries[]
     */
    public function getSeries() : array
    {
        return $this->series;
    }

    /**
     * Sets the series to be displayed in the chart.
     *
     * @uxon-property series
     * @uxon-type \exface\Core\Widgets\Parts\Charts\ChartSeries[]
     * @uxon-template [{"type": ""}]
     *
     * @param ChartSeries|UxonObject $series_or_uxon_object            
     * @return \exface\Core\Widgets\Chart
     */
    public function setSeries($series_or_uxon_object) : Chart
    {
        if ($series_or_uxon_object instanceof ChartSeries) {
            $this->addSeries($series_or_uxon_object);
        } elseif ($series_or_uxon_object instanceof UxonObject) {
            if ($series_or_uxon_object->isArray()){
                foreach ($series_or_uxon_object as $series){
                    $this->setSeries($series);
                }
            } else {
                $series = $this->createSeriesFromUxon($series_or_uxon_object);
                $this->addSeries($series);
            }
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Cannot set series in ' . $this->getWidgetType() . ': expecting instantiated ChartSeries widget or its UXON description or an array of UXON descriptions for multiple series - ' . gettype($series_or_uxon_object) . ' given instead!');
        }
        return $this;
    }
    
    /**
     * 
     * @param string $expression
     * @return ChartAxis
     */
    public function createAxisFromExpression(string $expression) : ChartAxis
    {
        return new ChartAxis($this, new UxonObject([
            'attribute_alias' => $expression
        ]));
    }
    
    /**
     * 
     * @param string $columnId
     * @return ChartAxis
     */
    public function createAxisFromColumnId(string $columnId) : ChartAxis
    {
        return new ChartAxis($this, new UxonObject([
            'data_column_id' => $columnId
        ]));
    }

    /**
     *
     * @param string $chart_type            
     * @param UxonObject $uxon            
     * @return ChartSeries
     */
    public function createSeriesFromUxon(UxonObject $uxon = null) : ChartSeries
    {
        if ($uxon->hasProperty('type')) {
            $type = mb_strtolower($uxon->getProperty('type'));
        } elseif (empty($this->series) === false) {
            $type = $this->series[count($this->series)-1]->getType();
        } else {
            throw new WidgetLogicError($this, 'Chart series type not set for series ' . count($this->series). '!');
        }
        $class = $this::getSeriesClassName($type);
        return new $class($this, $uxon, $type);
    }
    
    public static function getSeriesClassName(string $chartType) : string
    {
        return '\\exface\\Core\\Widgets\\Parts\\Charts\\' . ucfirst($chartType) . 'ChartSeries';
    }

    public function addSeries(ChartSeries $series) : Chart
    {
        $this->series[] = $series;
        return $this;
    }

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
     * If a valid link to another data widget is specified, it's data will be used instead of the data property of the chart itself.
     *
     * This is very handy if you want to visualize the data presented by a table or so. Using the link will make the chart automatically react to filters
     * and other setting of the target data widget.
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
     * Set to TRUE to hide all axes.
     *
     * @uxon-property hide_axes
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $trueOrFalse            
     * @return \exface\Core\Widgets\Chart
     */
    public function setHideAxes(bool $trueOrFalse) : Chart
    {
        if ($trueOrFalse === true) {
            foreach ($this->getAxes() as $axis) {
                $axis->setHidden(true);
            }
        }
        return $this;
    }

    public function getWidth()
    {
        if (parent::getWidth()->isUndefined()) {
            $this->setWidth('max');
        }
        return parent::getWidth();
    }

    /**
     * Set to true to hide the top toolbar, which generally will contain filters and other settings
     *
     * @uxon-property hide_header
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::getHideHeader()
     */
    public function getHideHeader()
    {
        return $this->hide_header;
    }

    public function setHideHeader($value)
    {
        $this->hide_header = $value;
        return $this;
    }

    public function getHideFooter()
    {
        return $this->hide_footer;
    }

    /**
     * Set to true to hide the bottom toolbar, which generally will contain pagination
     *
     * @uxon-property hide_footer
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveFooter::setHideFooter()
     */
    public function setHideFooter($value)
    {
        $this->hide_footer = $value;
        return $this;
    }

    /**
     * A Chart can be prefilled just like all the other data widgets, but only if it has it's own data. If the data is fetched from
     * a linked widget, the prefill does not make sense and will be ignored. But the linked widget will surely be prefilled, so the
     * the chart will get the correct data anyway.
     * 
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Data::prefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        if ($this->getDataWidgetLink()) {
            return parent::doPrefill($data_sheet);
        } else {
            return $this->getData()->prefill($data_sheet);
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        return $this->getData()->prepareDataSheetToPrefill($data_sheet);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        return $this->getData()->prepareDataSheetToRead($data_sheet);
    }

    /**
     * Returns an array of button widgets, that are explicitly bound to a double click on a data element
     *
     * @param string $mouse_action            
     * @return DataButton[]
     */
    public function getButtonsBoundToMouseAction($mouse_action)
    {
        $result = array();
        foreach ($this->getButtons() as $btn) {
            if ($btn instanceof DataButton) {
                if ($btn->getBindToMouseAction() == $mouse_action) {
                    $result[] = $btn;
                }
            }        
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoading()
     */
    public function getLazyLoading($default = true) : bool
    {
        return $this->getData()->getLazyLoading($default);
    }

    /**
     * Set to TRUE for asynchronous data loading (must be supported by facade)
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading(bool $value) : iSupportLazyLoading
    {
        $this->getData()->setLazyLoading($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction() : ActionInterface
    {
        return $this->getData()->getLazyLoadingAction();
    }

    /**
     * Sets the action alias to be used for lazy loading
     *
     * @uxon-property lazy_loading_action
     * @uxon-type string
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction(UxonObject $uxon) : iSupportLazyLoading
    {
        $this->getData()->setLazyLoadingAction($uxon);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'DataButton';
    }

    public function getAlternativeContainerForOrphanedSiblings()
    {
        return null;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType() : string
    {
        return 'ChartConfigurator';
    }
    
    public function getToolbarWidgetType()
    {
        return 'DataToolbar';
    }
    
    /**
     * @return string|NULL
     */
    public function getLegendPosition() : ?string
    {
        return $this->legendPosition;
    }

    /**
     * Position of the legend relative to the chart: left, right, top, bottom.
     * 
     * Using the property `legend_position_inside_chart` you can make the
     * legend appear inside (over) the chart to save space or next to it.
     * 
     * If the position of the legend is not defined explicitly, the facade
     * will pick an option automatically.
     * 
     * @uxon-property legend_position
     * @uxon-type string [left,right,top,bottom]
     * 
     * @param string $legendPosition
     * @return Chart
     */
    public function setLegendPosition(string $leftRightTopBottom) : Chart
    {
        $this->legendPosition = $leftRightTopBottom;
        return $this;
    }

    /**
     *
     * @return bool
     */
    public function getLegendPositionInsideChart() : bool
    {
        return $this->legenPositionInsideChart;
    }
    
    /**
     * Position the legend inside (true) or outside (false) of the chart.
     * 
     * If the position of the legend is not defined explicitly, the facade
     * will pick an option automatically. Mobile facades will mostly position 
     * legends inside the chart canvas to save space, while desktop facades 
     * can place it next to the chart in order to avoid overlapping.
     * 
     * In any case, the property `legend_position` controls the position
     * relative to the center of the chart. 
     * 
     * @uxon-property legend_position_inside_chart
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return Chart
     */
    public function setLegendPositionInsideChart(bool $value) : Chart
    {
        $this->legenPositionInsideChart = $value;
        return $this;
    }
    
    /**
     * returns true if legend should be hidden
     *
     * @return bool
     */
    public function getHideLegend() : bool
    {
        if ($this->legendHidden === null) {
            return false;
        }
        return $this->legendHidden;
    }
    
    /**
     * Configuration to hide the legend
     *
     * @uxon-property hide_legend
     * @uxon-type bool
     * @uxon-default false
     *
     * @param bool $hidden
     * @return Chart
     */
    public function setHideLegend(bool $hidden) : Chart
    {
        $this->legendHidden = $hidden;
        return $this;
    }    
    
    /**
     *
     * @param ChartAxis $axis
     * @throws WidgetLogicError
     * @return int
     */
    public function getSeriesIndex(ChartSeries $series) : int
    {
        $idx = array_search($series, $this->series, true);
        if ($idx !== false) {
            return $idx;
        }
        
        throw new WidgetLogicError($this, 'Series not "' . $series->getCaption() . '" found in chart "' . $this->getId() . '"!');
    }
    
    public function ImportUxonObject(UxonObject $uxon)
    {
        parent::importUxonObject($uxon);
        if ($uxon->hasProperty('data') === true) {
            $this->prepareDataWidget($this->getData());
            $this->dataPrepared = true;
        }
        return;
    }
    
    public function getEmptyText()
    {
        if (! $this->empty_text) {
            $this->empty_text = $this->translate('WIDGET.DATA.NO_DATA_FOUND');
        }
        return $this->empty_text;
    }
    
    /**
     * Sets a custom text to be displayed in the Data widget, if not data is found.
     *
     * The text may contain any facade-specific formatting: e.g. HTML for HTML-facades.
     *
     * @uxon-property empty_text
     * @uxon-type string|metamodel:formula
     *
     * @param string $value
     * @return Data
     */
    public function setEmptyText($value)
    {
        $this->empty_text = $this->evaluatePropertyExpression($value);
        return $this;
    }
    
    /**
     * TODO #chart-configurator make sure, only a ChartConfigurator can be used for charts!
     * This is tricky because a chart with a data link must have the same configurator, as
     * the linked data, so that they are update simultaniously. It relly MUST be the same
     * widget. However, if the linked data widget was already instantiated, it already has
     * a DataConfigurator, which now needs to get transformed into a chart configurator. This
     * transformation is basically the TODO. Since a ChartConfigurator also is a DataConfigurator,
     * it should be possible to use it back in the data widget. Of course, if there will be 
     * more DataConfigurators (e.g. the already existing DataTableConfigurator), it might be
     * better to make the ChartConfigurator wrap a DataConfigurator - to be discussed!
     *  
     * @see \exface\Core\Widgets\Traits\iHaveConfiguratorTrait::setConfiguratorWidget()
     */
    public function setConfiguratorWidget(iConfigureWidgets $widget) : iHaveConfigurator
    {
        if (! $widget instanceof ChartConfigurator && $widget instanceof DataConfigurator) {
            $configurator = WidgetFactory::create($this->getPage(), $this->getConfiguratorWidgetType(), $this);
            $configurator->setDataConfigurator($widget);
        } else {
            $configurator = $widget;
        }
        return $this->setConfiguratorWidgetViaTrait($configurator);
    }
    
    /**
     * Returns a text which can be displayed if initial loading is prevented.
     *
     * @return string
     */
    public function getAutoloadDisabledHint()
    {
        if ($this->autoload_disabled_hint === null) {
            return $this->translate('WIDGET.DATA.NOT_LOADED');
        }
        return $this->autoload_disabled_hint;
    }
    
    /**
     * Overrides the text shown if autoload_data is set to FALSE or required filters are missing.
     *
     * Use `=TRANSLATE()` to make the text translatable.
     *
     * @uxon-property autoload_disabled_hint
     * @uxon-type string|metamodel:formula
     *
     * @param string $text
     * @return Data
     */
    public function setAutoloadDisabledHint(string $text) : Chart
    {
        $this->autoload_disabled_hint = $this->evaluatePropertyExpression($text);
        return $this;
    }
}