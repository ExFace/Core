<?php

namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMergeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\iUsePrefillData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Actions\iReferenceWidget;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Interfaces\Tasks\TaskResultWidgetInterface;
use exface\Core\CommonLogic\Tasks\TaskResultWidget;

/**
 * The ShowWidget action is the base for all actions, that render widgets.
 * 
 * @author Andrej Kabachnik
 *        
 */
class ShowWidget extends AbstractAction implements iShowWidget, iReferenceWidget
{

    private $widget = null;

    private $widget_uxon = null;

    private $widget_id = null;

    private $prefill_with_filter_context = true;

    private $prefill_with_input_data = true;
    
    private $prefill_with_prefill_data = true;

    private $prefill_with_data_from_widget_link = null;

    /** @var DataSheetInterface */
    private $prefill_data_preset = null;

    private $filter_contexts = array();

    private $page_alias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::EXTERNAL_LINK);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     * 
     * @return TaskResultWidgetInterface
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        // Check, if the action has a widget. If not, give it the widget from the task
        /*if (! $this->isWidgetDefined() && $task->isTriggeredOnPage()) {
            $this->setWidget($task->getWidgetTriggeredBy());
        }*/
        
        $widget = $this->getWidget();
        // TODO copy the widget before prefill because otherwise the action cannot hanlde more than one task!
        $widget = $this->prefillWidget($task, $widget);
        
        return new TaskResultWidget($task, $widget);
    }

    /**
     * Returns the widget, that this action will show.
     * 
     * FIXME Currently this will even return a widget if the action links to another page.
     * This means, that all linked pages will be loaded when searching for a widget id -
     * and they will be searched too!!!
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::getWidget()
     */
    public function getWidget()
    {
        if (is_null($this->widget)) {
            switch (true) {
                case $this->getWidgetUxon():
                    $this->widget = WidgetFactory::createFromUxon($this->getWidgetDefinedIn()->getPage(), $this->getWidgetUxon(), ($this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null), $this->getDefaultWidgetType());
                    break;
                case $this->widget_id && ! $this->page_alias:
                    $this->widget = $this->getWidgetDefinedIn()->getPage()->getWidget($this->widget_id);
                    break;
                case $this->page_alias && ! $this->widget_id:
                    // TODO this causes problems with simple links to other pages, as the action attempts to load them here...
                    // $this->widget = $this->getApp()->getWorkbench()->ui()->getPage($this->page_alias)->getWidgetRoot();
                    break;
                case $this->page_alias && $this->widget_id:
                    $this->widget = $this->getPage()->getWidget($this->widget_id);
                    break;
            }
        }
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::getDefaultWidgetType()
     */
    public function getDefaultWidgetType()
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::isWidgetDefined()
     */
    public function isWidgetDefined() : bool
    {
        try {
            $widget = $this->getWidget();
        } catch (\Throwable $e) {
            return false;
        }
        
        return is_null($widget) ? false : true;
    }

    /**
     * 
     * @uxon-property widget
     * @uxon-type \exface\Core\Widgets\Container
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::setWidget()
     */
    public function setWidget($widget_or_uxon_object) : iShowWidget
    {
        if ($widget_or_uxon_object instanceof WidgetInterface) {
            $widget = $widget_or_uxon_object;
        } elseif ($widget_or_uxon_object instanceof UxonObject) {
            $this->setWidgetUxon($widget_or_uxon_object);
            $widget = null;
        } else {
            throw new ActionConfigurationError($this, 'Action "' . $this->getAlias() . '" expects the parameter "widget" to be either an instantiated widget or a valid UXON widget description object!', '6T91H2S');
        }
        $this->widget = $widget;
        return $this;
    }

    /**
     * Prefills the widget of this action with any available data: the action's input data, prefill data from the request and filter contexts.
     *
     * Technically, the method will attempt to create a data sheet from all those sources by merging them, read data for this sheet and perform
     * a $this->getWidget()->prefill(). If the different sources for prefill data cannot be combined into a single data sheet, the widget will
     * first get prefilled by input data and then by prefill data separately. If the context data cannot be combined with the input data, it will
     * be ignored.
     *
     * IDEA The idea of merging all into a single data sheet should save read operations. It makes things less transparent, however. Is it
     * worth it? Maybe do prefills sequentially instead starting with the leas significant data (context) and overwriting it with prefill data
     * and input data subsequently?
     *
     * @return void;
     */
    protected function prefillWidget(TaskInterface $task, WidgetInterface $widget) : WidgetInterface
    {
        // Start with the prefill data already stored in the widget
        if ($widget->getPrefillData()) {
            $data_sheet = $widget->getPrefillData();
        }
        
        // Prefill with input data if not turned off
        if ($this->getPrefillWithInputData() && $input_data = $this->getInputDataSheet($task)) {
            if (! $data_sheet || $data_sheet->isEmpty()) {
                $data_sheet = $input_data->copy();
            } else {
                try {
                    $data_sheet = $data_sheet->merge($input_data);
                } catch (DataSheetMergeError $e) {
                    // If anything goes wrong, use the input data to prefill. It is more important for an action, than
                    // other prefill sources.
                    $data_sheet = $input_data->copy();
                }
            }
        }
        
        // Now prefill with prefill data.
        if ($this->getPrefillWithPrefillData() && ($prefill_data = $this->getPrefillDataSheet($task)) && ! $prefill_data->isBlank()) {
            // Try to merge prefill data and any data already gathered. If the merge does not work, ignore the prefill data
            // for now and use it for a secondary prefill later.
            $prefill_data_merge_failed = false;
            if (! $data_sheet || $data_sheet->isEmpty()) {
                $data_sheet = $prefill_data->copy();
            } else {
                try {
                    $data_sheet = $data_sheet->merge($prefill_data);
                } catch (DataSheetMergeError $e) {
                    // Do not use the prefill data if it cannot be merged with the input data
                    $prefill_data_merge_failed = true;
                }
            }
        }
        
        // See if the widget requires any other columns to be prefilled. If so, add them and check if data needs to be read.
        if ($data_sheet && $data_sheet->countRows() > 0 && $data_sheet->getUidColumn()) {
            $data_sheet = $widget->prepareDataSheetToPrefill($data_sheet);
            if (! $data_sheet->isFresh()) {
                $data_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
                $data_sheet->dataRead();
            }
        }
        
        // Prefill widget using the filter contexts if the widget does not have any prefill data yet
        // TODO Use the context prefill even if the widget already has other prefill data: use DataSheet::merge()!
        if ($this->getPrefillWithFilterContext() && $widget && $context_conditions = $this->getApp()->getWorkbench()->context()->getScopeWindow()->getFilterContext()->getConditions($widget->getMetaObject())) {
            if (! $data_sheet || $data_sheet->isBlank()) {
                $data_sheet = DataSheetFactory::createFromObject($widget->getMetaObject());
            }
            
            // Make sure, the context object fits the data sheet object.
            // TODO Currently we fetch context filters for the object of the action. If data sheet has another object, we ignore the context filters.
            // Wouldn't it be better to add the context filters to the data sheet or maybe even to the data sheet and the prefill data separately?
            if ($widget->getMetaObject()->is($data_sheet->getMetaObject())) {
                /* @var $condition \exface\Core\CommonLogic\Model\Condition */
                foreach ($context_conditions as $condition) {                    
                    /*
                     * if ($widget && $condition->getExpression()->getMetaObject()->getId() == $widget->getMetaObject()->getId()){
                     * // If the expressions belong to the same object, as the one being displayed, use them as filters
                     * // TODO Building the prefill sheet from context in different ways depending on the object of the top widget
                     * // is somewhat ugly (shouldn't the children widgets get the chance, to decide themselves, what they do with the prefill)
                     * $data_sheet->getFilters()->addCondition($condition);
                     * } else
                     */
                    if ($condition->getComparator() == EXF_COMPARATOR_IS || $condition->getComparator() == EXF_COMPARATOR_EQUALS || $condition->getComparator() == EXF_COMPARATOR_IN) {
                        
                        // Double check to see if the data sheet already has filters over the attribute coming from the context.
                        // This could cause strange conflicts especially because the filter contest is added as a row, not as another
                        // filter: i.e. without this code you could not call a page with a filter URL parameter twice in a row with 
                        // different parameter values.
                        $filter_conflict = false;
                        foreach ($data_sheet->getFilters()->getConditions() as $existing){
                            if ($existing->getExpression()->toString() == $condition->getExpression()->toString()){
                                $filter_conflict = true;
                                break;
                            }
                        }
                        if ($filter_conflict) {
                            continue;
                        }
                        
                        // IDEA do we also need to check for conflicts with rows?
                        
                        // Add the filter values as columns to use them in forms
                        // Sinsce the objects of the widget and the prefill are
                        // the same, context filters can be used in two different 
                        // ways: either to prefill filter or to prefill inputs.
                        // How exactly, depends on the widget, so we put them
                        // in both places here.
                        // IDEA Perhaps, we should only place filters in filters
                        // of the data sheet and change the widget to look in 
                        // columns as well as in filters...
                        try {
                            $col = $data_sheet->getColumns()->addFromExpression($condition->getExpression());
                            // Add the value of the filter (if there) as cell value
                            if (! is_null($condition->getValue()) && $condition->getValue() !== ''){
                                $col->setValues(array(
                                    $condition->getValue()
                                ));
                            }
                        } catch (\Exception $e) {
                            // Do nothing if anything goes wrong. After all the context prefills are just an attempt the help
                            // the user. It's not a good Idea to throw a real error here!
                        }
                    }
                }
            }
        }
        
        if ($data_sheet) {
            $widget->prefill($data_sheet);
        }
        if ($prefill_data_merge_failed) {
            $widget->prefill($prefill_data);
        }
        
        return $widget;
    }

    /**
     * Gets the prefill data by merging the preset data with the task data.
     * 
     * @param TaskInterface $task
     * @return DataSheetInterface
     */
    protected function getPrefillDataSheet(TaskInterface $task) : DataSheetInterface
    {
        if ($task->hasPrefillData()) {
            // If the task has some prefill data, use it
            $sheet = $task->getPrefillData();
            // Merge it with the preset if it exists
            if ($this instanceof iUsePrefillData && $this->hasPrefillDataPreset()) {
                $sheet = $this->getPrefillDataPreset($task)->importRows($sheet);
            }
        } elseif ($this->hasInputDataPreset()) {
            // If the task has no data, use the preset data
            $sheet = $this->getInputDataPreset();
        } else {
            // If there is neither task nor preset data, create a new data sheet
            $sheet = DataSheetFactory::createFromObject($task->getMetaObject());
        }
        
        return $sheet;
    }

    /**
     * Sets the prefill data sheet to be used for this action.
     * 
     * Note, the prefill data will be ignored if prefill_with_prefill_data is
     * set to FALSE!
     * 
     * @uxon-property input_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * 
     * @see iUsePrefillData::setPrefillDataSheet()
     */
    public function setPrefillDataSheet(UxonObject $uxon) : iUsePrefillData
    {
        $exface = $this->getWorkbench();
        $data_sheet = DataSheetFactory::createFromUxon($exface, $uxon, $this->getMetaObject());
        if (! is_null($this->prefill_data_preset)) {
            try {
                $data_sheet = $this->prefill_data_preset->merge($data_sheet);
                $this->prefill_data_preset = $data_sheet;
            } catch (DataSheetMergeError $e) {
                // Do nothing, if the sheets cannot be merged
            }
        } else {
            $this->prefill_data_preset = $data_sheet;
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iReferenceWidget::getWidgetId()
     */
    public function getWidgetId()
    {
        if ($this->getWidget()) {
            return $this->getWidget()->getId();
        } else {
            return $this->widget_id;
        }
    }
    
    /**
     * Specifies the id of the widget to be shown. If not set, the main widget of the
     * page will be used.
     * 
     * @uxon-property widget_id
     * @uxon-type string
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iReferenceWidget::setWidgetId()
     */
    public function setWidgetId($value)
    {
        $this->widget_id = $value;
        return $this;
    }

   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\Actions\iShowWidget::getPrefillWithFilterContext()
    */
    public function getPrefillWithFilterContext() : bool
    {
        return $this->prefill_with_filter_context;
    }

    /**
     * Set to FALSE disable context prefills for this action
     * 
     * @uxon-property prefill_with_filter_context
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::setPrefillWithFilterContext()
     */
    public function setPrefillWithFilterContext($value) : iShowWidget
    {
        $this->prefill_with_filter_context = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::getPrefillWithInputData()
     */
    public function getPrefillWithInputData() : bool
    {
        return $this->prefill_with_input_data;
    }

    /**
     * Set to FALSE disable prefilling widgets with action input data.
     * 
     * @uxon-property prefill_with_input_data
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::setPrefillWithInputData()
     */
    public function setPrefillWithInputData($value) : iShowWidget
    {
        $this->prefill_with_input_data = $value;
        return $this;
    }

    /**
     * ShowWidget needs some kind of widget representation in UXON in order to be recreatable from the UXON object.
     * TODO Currently the widget is represented by widget_id and page_alias and there is no action widget UXON saved here. This won't work for generated widgets!
     * 
     * @see \exface\Core\Interfaces\Actions\ActionInterface::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('widget_id', $this->getWidgetId());
        $uxon->setProperty('page_alias', $this->page_alias ? $this->page_alias : $this->getWidgetDefinedIn()->getPage()->getAliasWithNamespace());
        $uxon->setProperty('prefill_with_filter_context', $this->getPrefillWithFilterContext());
        $uxon->setProperty('prefill_with_input_data', $this->getPrefillWithInputData());
        if ($this->hasPrefillDataPreset()) {
            $uxon->setProperty('prefill_data_sheet', $this->getPrefillDataPreset()->exportUxonObject());
        }
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iReferenceWidget::getPage()
     */
    public function getPage()
    {
        if ($this->isWidgetDefined()) {
            return $this->getWidget()->getPage();
        }
        return $this->getWorkbench()->ui()->getPage($this->page_alias);
    }
    
    public function getPageAlias()
    {
        return $this->page_alias;
    }

    /**
     * The alias of the page to get the widget from.
     * 
     * Widget links accept the internal UIDs, the namespaced alias as well as 
     * the CMS-page ids here because the users do not really know the difference
     * and will attempt to specify the id, they see first. Since most CMS show
     * their internal ids, that typically are not UUIDs, we just allow both ids
     * here.
     * 
     * @param string $value
     * @return iReferenceWidget
     */
    public function setPageAlias($value)
    {
        $this->page_alias = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see iUsePrefillData::getPrefillWithDataFromWidgetLink()
     */
    public function getPrefillWithDataFromWidgetLink()
    {
        return $this->prefill_with_data_from_widget_link;
    }
    
    /**
     * If a widget link is defined here, the prefill data for this action will
     * be taken from that widget link and not from the input widget.
     * 
     * The value can be either a string ([page_alias]widget_id!optional_column_id)
     * or a widget link defined as an object.
     * 
     * @uxon-property prefill_with_data_from_widget_link
     * @uxon-type \exface\Core\CommonLogic\WidgetLink
     * 
     * {@inheritdoc}
     * @see iUsePrefillData::setPrefillWithDataFromWidgetLink()
     */
    public function setPrefillWithDataFromWidgetLink($string_or_widget_link) : iUsePrefillData
    {
        $exface = $this->getWorkbench();
        if ($string_or_widget_link) {
            $this->prefill_with_data_from_widget_link = WidgetLinkFactory::createFromAnything($exface, $string_or_widget_link);
        }
        return $this;
    }
    
    /**
     * 
     * @param UxonObject|string $uxon_object_or_string
     * @return \exface\Core\Actions\ShowWidget
     */
    protected function setWidgetUxon($uxon_object_or_string)
    {
        $this->widget_uxon = UxonObject::fromAnything($uxon_object_or_string);
        return $this;
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getWidgetUxon()
    {
        return $this->widget_uxon;
    }

    /**
     * Set to TRUE to disable the prefill for this action entirely.
     *
     * @uxon-property do_not_prefill
     * @uxon-type boolean
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowWidget::setDoNotPrefill($value)
     */
    public function setDoNotPrefill($value) : iShowWidget
    {
        $value = BooleanDataType::cast($value) ? false : true;
        $this->setPrefillWithFilterContext($value);
        $this->setPrefillWithInputData($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iUsePrefillData::getPrefillWithPrefillData()
     */
    public function getPrefillWithPrefillData() : bool
    {
        return $this->prefill_with_prefill_data;
    }

    /**
     * Set to FALSE to make this action ignore prefill data passed along
     * 
     * @uxon-property prefill_with_prefill_data
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iUsePrefillData::setPrefillWithPrefillData()
     */
    public function setPrefillWithPrefillData($prefill_with_prefill_data) : iUsePrefillData
    {
        $this->prefill_with_prefill_data = $prefill_with_prefill_data;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iUsePrefillData::hasPrefillDataPreset()
     */
    public function hasPrefillDataPreset(): bool
    {
        return is_null($this->prefill_data_preset) ? false : true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iUsePrefillData::setPrefillDataPreset()
     */
    public function setPrefillDataPreset(DataSheetInterface $dataSheet): iUsePrefillData
    {
        $this->prefill_data_preset = $dataSheet;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iUsePrefillData::getPrefillDataPreset()
     */
    public function getPrefillDataPreset(): DataSheetInterface
    {
        return $this->prefill_data_preset;
    }

}
?>