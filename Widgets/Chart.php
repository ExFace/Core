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
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\Parts\Charts\ChartAxis;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Charts\AbstractChartSeries;

/**
 * A Chart widget draws a chart with upto two axis and any number of series.
 * 
 * Every Chart contains a Data widget, that fetches data visualized by the chart.
 * Chart series as well as axis legends are extracted for columns in that data.
 *
 * @author Andrej Kabachnik
 *        
 */
class Chart extends AbstractWidget implements iUseData, iHaveToolbars, iHaveButtons, iHaveHeader, iHaveFooter, iHaveConfigurator, iSupportLazyLoading, iFillEntireContainer
{
    use iHaveButtonsAndToolbarsTrait;
    use iSupportLazyLoadingTrait;

    const AXIS_X = 'x';

    const AXIS_Y = 'y';
    
    /**
     *
     * @var ChartAxis[]
     */
    private $axes_x = array();

    /**
     *
     * @var ChartAxis[]
     */
    private $axes_y = array();

    /**
     *
     * @var AbstractChartSeries[]
     */
    private $series = array();

    /**
     *
     * @var Data
     */
    private $data = null;

    /**
     *
     * @var UxonObject|string
     */
    private $data_widget_link = null;
    
    private $legendAlignment = null;

    /**
     *
     * @var boolean
     */
    private $hide_header = false;

    /**
     *
     * @var boolean
     */
    private $hide_footer = false;

    /** @var Button[] */
    private $buttons = array();
    
    /**
     * 
     * @var bool
     */
    private $dataPrepared = false;

    public function getChildren() : \Iterator
    {
        if (! $this->getDataWidgetLink()) {
            yield $this->getData();
        }
        
        foreach ($this->getSeries() as $series) {
            yield $series;
        }
        
        foreach ($this->getToolbars() as $toolbar) {
            yield $toolbar;
        }
    }

    /**
     *
     * @return ChartAxis[]
     */
    public function getAxesX()
    {
        return $this->axes_x;
    }

    /**
     * Sets X-axis of the chart.
     * Multiple axes are possible, at least one must be provided!
     *
     * @uxon-property axis_x
     * @uxon-type \exface\Core\Widget\ChartAxis[]
     *
     * @param ChartAxis|UxonObject $axis_or_uxon_object            
     * @return \exface\Core\Widgets\Chart
     */
    public function setAxisX($axis_or_uxon_object)
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
    public function getAxesY()
    {
        return $this->axes_y;
    }

    /**
     *
     * @return ChartAxis[]
     */
    public function getAxes($x_or_y = null)
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
     * Sets Y-axis of the chart.
     * Multiple axes are possible, at least one must be provided!
     *
     * @uxon-property axis_y
     * @uxon-type \exface\Core\Widget\ChartAxis[]
     *
     * @param ChartAxis|UxonObject $axis_or_uxon_object_or_array            
     * @return \exface\Core\Widgets\Chart
     */
    public function setAxisY($axis_or_uxon_object)
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
    
    public function addAxisX(ChartAxis $axis) : Chart
    {
        return $this->addAxis(static::AXIS_X, $axis);
    }
    
    public function addAxisY(ChartAxis $axis) : Chart
    {
        return $this->addAxis(static::AXIS_Y, $axis);
    }

    protected function addAxis($x_or_y, ChartAxis $axis)
    {
        if (! $axis->getPosition()) {
            switch ($x_or_y) {
                case $this::AXIS_Y:
                    $axis->setPosition(ChartAxis::POSITION_LEFT);
                    break;
                case $this::AXIS_X:
                    $axis->setPosition(ChartAxis::POSITION_BOTTOM);
                    break;
                default:
                    throw new WidgetPropertyInvalidValueError($this, 'Invalid axis coordinate: "' . $x_or_y . '"! "x" or "y" expected!', '6T90UV9');
            }
        }
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
        
        throw new WidgetLogicError($this, 'Axis not "' . $axis->getCaption() . '" found in chart "' . $this->getId() . '"!');
    }

    public function getData()
    {
        if ($this->data === null) {
            if ($link = $this->getDataWidgetLink()) {
                $data = $link->getTargetWidget();
                if ($this->dataPrepared === false) {
                    $this->prepareData($data);
                    $this->dataPrepared = true;
                }
            } else {
                throw new WidgetConfigurationError($this, 'Cannot get data for ' . $this->getWidgetType() . ' "' . $this->getId() . '": either data or data_widget_link must be defined in the UXON description!', '6T90WFX');
            }
        } else {
            $data = $this->data;
        }
        
        return $data;
    }
    
    protected function prepareData(iShowData $dataWidget) : Chart
    {
        foreach ($this->getSeries() as $series) {
            $series->prepareData($dataWidget);
        }
        
        foreach ($this->getAxes() as $axis) {
            $axis->prepareData($dataWidget);
        }
        
        return $this;
    }

    /**
     * Sets the Data widget (simple table with data), wich will be the source for the chart.
     *
     * Chart axes and series will be bound to columns of this data widget. In the simplest case, there should
     * be a coulum with x-axis-values and one with y-axis-values. The series property is optional, as you can also
     * add a chart_type property to any axis to have get an automatically generated series for values of that axis.
     *
     * @uxon-property data
     * @uxon-type \exface\Core\Widget\Data
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iUseData::setData()
     */
    public function setData(UxonObject $uxon_object)
    {
        $data = $this->getPage()->createWidget('Data', $this);
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
     *
     * @param string $column_id            
     * @param string $x_or_y            
     * @return ChartAxis|NULL
     */
    public function findAxis(DataColumn $column, string $x_or_y = null) : ChartAxis
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
                if ($axis->getDataColumn()->getAttribute()->is($attribute)) {
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
     * @return AbstractChartSeries[]
     */
    public function getSeries()
    {
        return $this->series;
    }

    /**
     * Sets the series to be displayed in the chart.
     * Multiple series are possible.
     *
     * @uxon-property series
     * @uxon-type \exface\Core\Widgets\Parts\ChartsAbstractChartSeries[]
     * @uxon-template [{"type": ""}]
     *
     * @param AbstractChartSeries|UxonObject $series_or_uxon_object            
     * @return \exface\Core\Widgets\Chart
     */
    public function setSeries($series_or_uxon_object)
    {
        if ($series_or_uxon_object instanceof AbstractChartSeries) {
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
            throw new WidgetPropertyInvalidValueError($this, 'Cannot set series in ' . $this->getWidgetType() . ': expecting instantiated AbstractChartSeries widget or its UXON description or an array of UXON descriptions for multiple series - ' . gettype($series_or_uxon_object) . ' given instead!');
        }
        return $this;
    }
    
    public function createAxisFromExpression(string $expression) : ChartAxis
    {
        return new ChartAxis($this, new UxonObject([
            'attribute_alias' => $expression
        ]));
    }

    /**
     *
     * @param string $chart_type            
     * @param UxonObject $uxon            
     * @return AbstractChartSeries
     */
    public function createSeriesFromUxon(UxonObject $uxon = null) : AbstractChartSeries
    {
        $type = mb_strtolower($uxon->getProperty('type'));
        $class = '\\exface\\Core\\Widgets\\Parts\\Charts\\' . ucfirst($type) . 'ChartSeries';
        return new $class($this, $uxon);
    }

    public function addSeries(AbstractChartSeries $series) : Chart
    {
        $this->series[] = $series;
        return $this;
    }

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
     * Set to TRUE to hide axes.
     *
     * @uxon-property hide_axes
     *
     * @param boolean $boolean            
     * @return \exface\Core\Widgets\Chart
     */
    public function setHideAxes($boolean)
    {
        if ($boolean) {
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
     *              
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
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Abstract\exface\Core\Widget\:prepare_data_sheet_to_prefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        return $this->getData()->prepareDataSheetToPrefill($data_sheet);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Abstract\exface\Core\Widget\:prepare_data_sheet_to_read()
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
            if ($btn->getBindToMouseAction() == $mouse_action) {
                $result[] = $btn;
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
     * 
     */
    public function getConfiguratorWidget() : iConfigureWidgets
    {
        return $this->getData()->getConfiguratorWidget();
    }
    
    public function setConfigurator(UxonObject $uxon) : iHaveConfigurator
    {
        $this->getData()->setConfigurator($uxon);
        
        return $this;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::setConfiguratorWidget()
     */
    public function setConfiguratorWidget(iConfigureWidgets $widget) : iHaveConfigurator
    {
        $this->getData()->setConfiguratorWidget($widget);
        return $this;
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
     * @return string
     */
    public function getLegendAlignment()
    {
        return $this->legendAlignment;
    }

    /**
     * 
     * @uxon-property legend_alignment
     * @uxon-type string [ left, right ]
     * 
     * @param string $legendAlignment
     * @return Chart
     */
    public function setLegendAlignment($legendAlignment)
    {
        $this->legendAlignment = $legendAlignment;
        return $this;
    }
    
    /**
     *
     * @param ChartAxis $axis
     * @throws WidgetLogicError
     * @return int
     */
    public function getSeriesIndex(AbstractChartSeries $series) : int
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
            $this->prepareData($this->getData());
            $this->dataPrepared = true;
        }
        return;
    }
}