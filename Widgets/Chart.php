<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iHaveFooter;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Interfaces\Widgets\iShowDataSet;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;

/**
 * A Chart widget draws a chart with upto two axis and any number of series.
 * 
 * Every Chart contains a Data widget, that fetches data visualized by the chart.
 * Chart series as well as axis legends are extracted for columns in that data.
 *
 * @author Andrej Kabachnik
 *        
 */
class Chart extends AbstractWidget implements iShowDataSet, iHaveToolbars, iHaveButtons, iHaveHeader, iHaveFooter, iHaveConfigurator, iSupportLazyLoading, iFillEntireContainer
{
    use iHaveButtonsAndToolbarsTrait;

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
     * @var ChartSeries[]
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

    /**
     *
     * @var boolean
     */
    private $stack_series = false;

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

    /** @var string */
    private $lazy_loading_group_id = null;

    const AXIS_X = 'x';

    const AXIS_Y = 'y';

    public function getChildren()
    {
        $children = array();
        if (! $this->getDataWidgetLink()) {
            $children[] = $this->getData();
        }
        $children = array_merge($children, $this->getAxes(), $this->getSeries(), $this->getToolbars());
        return $children;
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
     * @param ChartAxis|UxonObject|array $axis_or_uxon_object_or_array            
     * @return \exface\Core\Widgets\Chart
     */
    public function setAxisX($axis_or_uxon_object_or_array)
    {
        if ($axis_or_uxon_object_or_array instanceof ChartAxis) {
            $this->addAxis('x', $axis_or_uxon_object_or_array);
        } elseif (is_array($axis_or_uxon_object_or_array)) {
            foreach ($axis_or_uxon_object_or_array as $axis) {
                $this->setAxisX($axis);
            }
        } else {
            $axis = $this->getPage()->createWidget('ChartAxis', $this);
            $axis->importUxonObject($axis_or_uxon_object_or_array);
            $this->addAxis('x', $axis);
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
     * @param unknown $axis_or_uxon_object_or_array            
     * @return \exface\Core\Widgets\Chart
     */
    public function setAxisY($axis_or_uxon_object_or_array)
    {
        if ($axis_or_uxon_object_or_array instanceof ChartAxis) {
            $this->addAxis('y', $axis_or_uxon_object_or_array);
        } elseif (is_array($axis_or_uxon_object_or_array)) {
            foreach ($axis_or_uxon_object_or_array as $axis) {
                $this->setAxisY($axis);
            }
        } else {
            $axis = $this->getPage()->createWidget('ChartAxis', $this);
            $axis->importUxonObject($axis_or_uxon_object_or_array);
            $this->addAxis('y', $axis);
        }
        return $this;
    }

    public function addAxis($x_or_y, ChartAxis $axis)
    {
        $axis->setChart($this);
        $axis->setDimension($x_or_y);
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
        $count = array_push($this->$var, $axis);
        $axis->setNumber($count);
        return $this;
    }

    public function getData()
    {
        if (is_null($this->data)) {
            if ($link = $this->getDataWidgetLink()) {
                return $link->getWidget();
            } else {
                throw new WidgetConfigurationError($this, 'Cannot get data for ' . $this->getWidgetType() . ' "' . $this->getId() . '": either data or data_widget_link must be defined in the UXON description!', '6T90WFX');
            }
        }
        return $this->data;
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
     * @see \exface\Core\Interfaces\Widgets\iShowDataSet::setData()
     */
    public function setData(\stdClass $uxon_object)
    {
        $data = $this->getPage()->createWidget('Data', $this);
        $data->setMetaObjectId($this->getMetaObjectId());
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
     * @return ChartAxis | boolean
     */
    public function findAxisByColumnId($column_id, $x_or_y = null)
    {
        foreach ($this->getAxes($x_or_y) as $axis) {
            if ($axis->getDataColumnId() == $column_id) {
                return $axis;
            }
        }
        return false;
    }

    /**
     *
     * @param string $alias_with_relation_path            
     * @param string $x_or_y            
     * @return ChartAxis | boolean
     */
    public function findAxisByAttributeAlias($alias_with_relation_path, $x_or_y = null)
    {
        foreach ($this->getAxes($x_or_y) as $axis) {
            if ($axis->getDataColumn()->getAttribute() && $axis->getDataColumn()->getAttribute()->getAliasWithRelationPath() == $alias_with_relation_path) {
                return $axis;
            }
        }
        return false;
    }

    /**
     *
     * @return ChartSeries[]
     */
    public function getSeries()
    {
        return $this->series;
    }

    /**
     *
     * @param string $chart_type            
     * @return ChartSeries[]
     */
    public function getSeriesByChartType($chart_type)
    {
        $result = array();
        foreach ($this->getSeries() as $series) {
            if ($series->getChartType() === $chart_type) {
                $result[] = $series;
            }
        }
        return $result;
    }

    /**
     * Sets the series to be displayed in the chart.
     * Multiple series are possible.
     *
     * @uxon-property series
     * @uxon-type \exface\Core\Widget\ChartSeries[]
     *
     * @param unknown $series_or_uxon_object_or_array            
     * @return \exface\Core\Widgets\Chart
     */
    public function setSeries($series_or_uxon_object_or_array)
    {
        if ($series_or_uxon_object_or_array instanceof ChartAxis) {
            $this->addSeries($series_or_uxon_object_or_array);
        } elseif (is_array($series_or_uxon_object_or_array)) {
            foreach ($series_or_uxon_object_or_array as $series) {
                $this->setSeries($series);
            }
        } else {
            $series = $this->createSeries(null, $series_or_uxon_object_or_array);
            $this->addSeries($series);
        }
        return $this;
    }

    /**
     *
     * @param string $chart_type            
     * @param \stdClass $uxon            
     * @return ChartSeries
     */
    public function createSeries($chart_type = null, \stdClass $uxon = null)
    {
        $series = $this->getPage()->createWidget('ChartSeries', $this);
        if ($uxon) {
            $series->importUxonObject($uxon);
        }
        if (! is_null($chart_type)) {
            $series->setChartType($chart_type);
        }
        return $series;
    }

    public function addSeries(ChartSeries $series)
    {
        $series->setChart($this);
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
     * @see \exface\Core\Interfaces\Widgets\iShowDataSet::setDataWidgetLink()
     */
    public function setDataWidgetLink($value)
    {
        $exface = $this->getWorkbench();
        $this->data_widget_link = WidgetLinkFactory::createFromAnything($exface, $value, $this->getIdSpace());
        return $this;
    }

    public function getStackSeries()
    {
        return $this->stack_series;
    }

    /**
     * Set to true to stack all series of this chart
     *
     * @uxon-property stack_series
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\Chart
     */
    public function setStackSeries($value)
    {
        $this->stack_series = $value;
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
     *
     * {@inheritdoc} A Chart can be prefilled just like all the other data widgets, but only if it has it's own data. If the data is fetched from
     *               a linked widget, the prefill does not make sense and will be ignored. But the linked widget will surely be prefilled, so the
     *               the chart will get the correct data anyway.
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
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
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
    public function getLazyLoading()
    {
        return $this->getData()->getLazyLoading();
    }

    /**
     * Set to TRUE for asynchronous data loading (must be supported by template)
     *
     * @uxon-property lazy_loading
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoading()
     */
    public function setLazyLoading($value)
    {
        return $this->getData()->setLazyLoading($value);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingAction()
     */
    public function getLazyLoadingAction()
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
    public function setLazyLoadingAction($value)
    {
        return $this->getData()->setLazyLoadingAction($value);
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

    public function getAlternativeContainerForOrphanedSiblings()
    {
        return null;
    }
    /**
     * 
     */
    public function getConfiguratorWidget()
    {
        return $this->getData()->getConfiguratorWidget();
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::setConfiguratorWidget()
     */
    public function setConfiguratorWidget($widget_or_uxon_object)
    {
        return $this->getData()->setConfiguratorWidget($widget_or_uxon_object);
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType()
    {
        return 'ChartConfigurator';
    }
    
    public function getToolbarWidgetType()
    {
        return 'DataToolbar';
    }
}
?>