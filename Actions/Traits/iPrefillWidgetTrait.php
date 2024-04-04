<?php
namespace exface\Core\Actions\Traits;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\DataSheets\DataSheetMergeError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Events\Widget\OnPrefillDataLoadedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\ActionLogBook;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
trait iPrefillWidgetTrait 
{
    private $prefill_with_filter_context = false;
    
    private $prefill_with_input_data = true;
    
    private $prefill_with_prefill_data = true;
    
    private $prefill_with_defaults = null;
    
    private $prefill_with_data_from_widget_link = null;
    
    /** @var DataSheetInterface */
    private $prefill_data_preset = null;
    
    private $prefill_data_refresh = iPrefillWidget::REFRESH_AUTO;
    
    abstract protected function getLogBook(TaskInterface $task) : ActionLogBook;
    
    /**
     * Prefills the widget of this action with any available data: the action's input data, prefill data from the request and filter contexts.
     *
     * @return WidgetInterface
     */
    protected function prefillWidget(TaskInterface $task, WidgetInterface $widget) : WidgetInterface
    {
        $logBook = $this->getLogBook($task);
        $logBook->addSection('Prefilling widget "' . $widget->getWidgetType() . '"');
        $logBook->addCodeBlock('[#diagram_prefill#]', 'mermaid');
        $prefillSheets = $this->getPrefillDataFromTask($widget, $task, $logBook);
        
        // Add data from the filter contexts if possible.
        // Do this ONLY for the main prefill sheet
        // Do this AFTER the regular prefill data was fully read because otherwise the filter values
        // will get eventually overwritten!
        if ($mainSheetWithContext = $this->getPrefillDataFromFilterContext($widget, $task, $logBook, ($prefillSheets[0] ?? null))) {
            $prefillSheets[0] = $mainSheetWithContext;
        }
        
        foreach ($prefillSheets as $i => $sheet) {
            $logBook->addDataSheet(($i > 0 ? 'Secondary prefill' : 'Final prefill'), $sheet);
            $event = new OnPrefillDataLoadedEvent($widget, $sheet, $this, $logBook);
            $this->getWorkbench()->EventManager()->dispatch($event);
            $widget->prefill($sheet);
        }
        
        return $widget;
    }
    
    /**
     * Returns the prefill data derived from the task as an array  of data sheets ordered by importance descendingly.
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
     * @param WidgetInterface $widget
     * @param TaskInterface $task
     * @param DataLogBookInterface $logBook
     * 
     * @throws ActionInputMissingError
     * 
     * @return DataSheetInterface[]
     */
    protected function getPrefillDataFromTask(WidgetInterface $widget, TaskInterface $task, DataLogBookInterface $logBook) : array
    {
        $logBook->addLine('Prefill from task data');
        $logBook->addIndent(+1);
        $diagram = 'flowchart LR';
        $diagram .= "\n\t InputPrefill(\"Task input\")";
        $diagram .= "\n\t PrefillData(\"Task prefill\")";
        $diagram .= "\n\t CollectPrefill[\"Collect\ntask data\"]";
        $diagram .= "\n\t Prefill(\"Prefill\ndata\")";
        
        // Start with the prefill data already stored in the widget
        if ($widget->isPrefilled()) {
            $logBook->addLine('Using current widget prefill data as base.');
            $data_sheet = $widget->getPrefillData()->copy();
            
            $diagram .= "\n\t WidgetPrefill(Widget prefill data) -->|" . DataLogBook::buildMermaidTitleForData($data_sheet) . "| CollectPrefill";
            $logBook->addDataSheet('Current prefill data in widget', $data_sheet);
        }
        
        // Add (merge) input data if not explicitly disabled
        $logBook->addLine('Property `prefill_with_input_data` is `' . ($this->getPrefillWithInputData() ? 'true' : 'false') . '`.');
        if ($this->getPrefillWithInputData() === true && ($task->hasInputData() === true || $this->hasInputDataPreset() === true)) {
            $input_data = $this->getInputDataSheet($task);
            
            $diagram .= "\n\t InputPrefill -->|" . DataLogBook::buildMermaidTitleForData($input_data) . "| CollectPrefill";
            $logBook->setIndentActive(1);
            $logBook->addLine('Input data found:');
            $logBook->addIndent(1);
            $logBook->addLine('Object: ' . $input_data->getMetaObject()->__toString());
            $logBook->addLine('Rows: ' . $input_data->countRows());
            $logBook->addLine('Filters: ' . ($input_data->getFilters()->countConditions() + $input_data->getFilters()->countNestedGroups()));
            
            if (! $data_sheet || $data_sheet->isEmpty()) {
                $logBook->addLine('Using input data for prefill.');
                $data_sheet = $input_data->copy();
            } else {
                try {
                    $data_sheet = $data_sheet->merge($input_data);
                    $logBook->addLine('Merged input data with current prefill data of the widget.');
                } catch (DataSheetMergeError $e) {
                    // If anything goes wrong, use the input data to prefill. It is more important for an action, than
                    // other prefill sources.
                    $data_sheet = $input_data->copy();
                    $logBook->addLine('Merging input data with current prefill data of the widget failed - using just the input data instead!');
                }
            }
            $logBook->addIndent(-1);
        } else {
            $diagram .= "\n\t InputPrefill -.->|No data| CollectPrefill";
            $logBook->addLine('No input data to use.', +1);
        }
        
        // Add (merge) prefill data if not explicitly disabled. The prefill data is a merge from the
        // task's prefill data and the `prefill_data` preset from the action's config.
        $logBook->addLine('Property `prefill_with_prefill_data` is `' . ($this->getPrefillWithPrefillData() ? 'true' : 'false') . '`.');
        if ($this->getPrefillWithPrefillData() && ($prefill_data = $this->getPrefillDataSheet($task))) {
            $logBook->addDataSheet('Provided prefill data', $prefill_data);
            $logBook->addLine('Prefill data found:');
            $logBook->addIndent(1);
            $logBook->addLine('Object: ' . $prefill_data->getMetaObject()->__toString());
            $logBook->addLine('Rows: ' . $prefill_data->countRows());
            $logBook->addLine('Filters: ' . ($prefill_data->getFilters()->countConditions() + $prefill_data->getFilters()->countNestedGroups()));
            
            // Try to merge prefill data and any data already gathered. If the merge does not work, ignore the prefill data
            // for now and use it for a secondary prefill later.
            $prefill_data_merge_failed = false;
            if ($prefill_data->isBlank()) {
                $diagram .= "\n\t PrefillData -.->|No data| CollectPrefill";
                $logBook->addLine('Cannot use prefill data - data sheet is blank (no rows, no filters)!');
            } else {
                $diagram .= "\n\t PrefillData -->|" . DataLogBook::buildMermaidTitleForData($prefill_data) . "| CollectPrefill";
                if (! $data_sheet || $data_sheet->isEmpty()) {
                    $logBook->addLine('Using prefill data for prefill.');
                    $data_sheet = $prefill_data->copy();
                } else {
                    try {
                        $data_sheet = $data_sheet->merge($prefill_data);
                        $logBook->addLine('Merged prefill data with data collected above (input data, current prefill).');
                    } catch (DataSheetMergeError $e) {
                        // Do not use the prefill data if it cannot be merged with the input data
                        $prefill_data_merge_failed = true;
                        
                        $diagram .= "\n\t PrefillData -->|" . DataLogBook::buildMermaidTitleForData($prefill_data) . "| PrefillSecondary(\"Secondary\nprefill data\")";
                        $logBook->addLine('Merging prefill data failed - will try to use prefill data additionally below.');
                    }
                }
            }
            $logBook->addIndent(-1);
        } else {
            $logBook->addLine('No prefill data to use', +1);
        }
        
        // See if the data should be re-read from the data source
        if ($data_sheet) {
            $logBook->addLine('Potential prefill data found - now finding out if a refresh/read is needed.');
            $diagram .= "\n\t CollectPrefill -->|" . DataLogBook::buildMermaidTitleForData($data_sheet) . "| ";
            
            $refresh = $this->getPrefillDataRefresh();
            
            $logBook->addLine('Property `prefill_data_refresh` is `' . $refresh . '`:');
            $logBook->addIndent(1);
            
            // If `prefill_data_refresh` is set to `auto`, pick one of the other options
            // according to the current situation.
            if ($refresh === iPrefillWidget::REFRESH_AUTO) {
                // Silently ignore empty prefills, those without UIDs and non-readable data
                if ($data_sheet->countRows() === 0 || ! $data_sheet->hasUidColumn(true) || ! $data_sheet->getMetaObject()->isReadable()) {
                    $logBook->addLine('No refresh for empty prefills, those without UIDs and non-readable data');
                    $refresh = iPrefillWidget::REFRESH_NEVER;
                } else {
                    // Ask the widget for expected data to see if a refresh is required
                    $colsBefore = $data_sheet->getColumns()->count();
                    $data_sheet = $widget->prepareDataSheetToPrefill($data_sheet);
                    $logBook->addLine('Getting required prefill columns from widget: found ' . ($data_sheet->getColumns()->count() - $colsBefore) . ' additional columns.');
                    switch (true) {
                        // Don't read the data source if we have all data required. This is a controlversal
                        // decision, but that's the way it was done in former versions. On the one hand,
                        // this saves us a data source read that probably won't give us any new data (which
                        // is good for high-latency sources like web services). On the other hand, we may
                        // end up with potentially outdated data if the input data was not refreshed recently.
                        // Of course, from the point of view of a human, a widget (e.g. Form) with data implies,
                        // that that data was correct at the time when the widget was displayed, but reality
                        // the input data is relatively fresh for simple objects while more complex objects
                        // will probably require additional fields causing a refresh anyway. So this option
                        // basically saves a data source request for simple use-cases.
                        case $data_sheet->isFresh():
                            $logBook->addLine('Prefill data is fresh');
                            $refresh = iPrefillWidget::REFRESH_NEVER;
                            break;
                            // Only refresh missing values if input data was used and a mapper was applied.
                            // In this case the user intended to change certain columns, so we should not
                            // overwrite them with data source values, but we should still read missing
                            // values because the user was probably too lazy to mapp all required columns.
                            case $input_data && $this->getInputMapperUsed($input_data) !== null:
                                $refresh = iPrefillWidget::REFRESH_ONLY_MISSING_VALUES;
                                $logBook->addLine('An `input_mapper` was used');
                                break;
                                // Refresh in all other cases
                            default:
                                $refresh = iPrefillWidget::REFRESH_ALWAYS;
                                break;
                    }
                }
            } elseif ($refresh !== iPrefillWidget::REFRESH_NEVER) {
                // If $refresh is not `auto` and not explicitly disabled, ask the widget for
                // expected data
                $colsBefore = $data_sheet->getColumns()->count();
                $data_sheet = $widget->prepareDataSheetToPrefill($data_sheet);
                $logBook->addLine('Getting required prefill columns from widget: found ' . ($data_sheet->getColumns()->count() - $colsBefore) . ' additional columns.');
            }
            
            // Refresh data if required
            switch (true) {
                // Refresh in any case on `always`
                case $refresh === iPrefillWidget::REFRESH_ALWAYS:
                    // Refresh if not fresh on `only_missing_values` (= empty columns were added)
                case $refresh === iPrefillWidget::REFRESH_ONLY_MISSING_VALUES && ! $data_sheet->isFresh():
                    if (! $data_sheet->hasUidColumn(true)) {
                        throw new ActionInputMissingError($this, 'Cannot refresh prefill data for action "' . $this->getAliaswithNamespace() . '": UID values for every prefill row required!');
                    }
                    $diagram .= "RefreshPrefill";
                    $freshData = $data_sheet->copy();
                    $freshData->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
                    // Improve performance by disabling the row counter
                    $freshData->setAutoCount(false);
                    $logBook->addLine('Reading fresh data with filter `' . $freshData->getFilters()->__toString() . '`');
                    $freshData->dataRead();
                    $logBook->addDataSheet('Missing data loaded', $freshData);
                    // Merge and overwrite existing values unless refresh `only_missing_values`
                    if ($refresh === iPrefillWidget::REFRESH_ONLY_MISSING_VALUES) {
                        $diagram .= "\n\t RefreshPrefill[\"Read missing\ndata\"] -->|" . DataLogBook::buildMermaidTitleForData($data_sheet) . "| Prefill";
                        $logBook->addLine('Refreshing only missing values');
                    } else {
                        $diagram .= "\n\t RefreshPrefill[\"Refresh\nall data\"] -->|" . DataLogBook::buildMermaidTitleForData($data_sheet) . "| Prefill";
                        $logBook->addLine('Refreshing all data');
                    }
                    $data_sheet->merge($freshData, $refresh !== iPrefillWidget::REFRESH_ONLY_MISSING_VALUES);
                    break;
                default:
                    $logBook->addLine('Will not refresh');
                    $diagram .= "Prefill";
            }
        } else {
            $diagram .= "\n\t CollectPrefill -.->|No data| Prefill";
        }
        
        $logBook->addIndent(-1);
        
        // Add the combined prefill data to the result array and add the explicit prefill
        // data additionally if they could not be merged.
        // Make sure to disable auto-count as it is does not make sense to count prefill
        // data and we want to avoid the possibly costly count operation in further processing.
        $result_sheets = [];
        if ($data_sheet) {
            $logBook->addLine('Regular prefill data prepared.');
            $data_sheet->setAutoCount(false);
            $result_sheets[] = $data_sheet;
        }
        if ($prefill_data_merge_failed === true) {
            $logBook->addLine('Additional prefill data prepared because merging input and prefill data failed.');
            $prefill_data->setAutoCount(false);
            $result_sheets[] = $prefill_data;
        }
        
        $logBook->addPlaceholderValue('diagram_prefill', $diagram);
        $logBook->addIndent(-1);
        
        return $result_sheets;
    }
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param TaskInterface $task
     * @param DataLogBookInterface $logBook
     * @param DataSheetInterface $data_sheet
     * 
     * @return DataSheetInterface|NULL
     */
    protected function getPrefillDataFromFilterContext(WidgetInterface $widget, TaskInterface $task, DataLogBookInterface $logBook, DataSheetInterface $data_sheet = null) : ?DataSheetInterface
    {
        $logBook->addLine('Prefill from filter context');
        $logBook->addIndent(1);
        $logBook->addLine('Property `prefill_with_filter_context` is `' . ($this->getPrefillWithFilterContext($task) ? 'true' : 'false') . '`');
        $widgetObj = $widget->getMetaObject();
        // Prefill widget using the filter contexts if the widget does not have any prefill data yet
        // TODO Use the context prefill even if the widget already has other prefill data: use DataSheet::merge()!
        if ($this->getPrefillWithFilterContext($task) && $widget && $context_conditions = $this->getApp()->getWorkbench()->getContext()->getScopeWindow()->getFilterContext()->getConditions($widgetObj)) {
            $diagram = $logBook->getPlaceholderValue('diagram_prefill');
            $diagram .= "\n\t FilterContext(Filter context)";
            if (! $data_sheet || $data_sheet->isBlank()) {
                $noDataProvided = true;
                $logBook->addLine('Creating new data sheet for the widgets object since no usable data found so far');
                $data_sheet = DataSheetFactory::createFromObject($widgetObj);
            }
            $dataObj = $data_sheet->getMetaObject();
            
            // Make sure, the context object fits the data sheet object.
            // TODO Currently we fetch context filters for the object of the action. If data sheet has another object, we ignore the context filters.
            // Wouldn't it be better to add the context filters to the data sheet or maybe even to the data sheet and the prefill data separately?
            if ($noDataProvided === true || $widgetObj->is($dataObj)) {
                /* @var $condition \exface\Core\CommonLogic\Model\Condition */
                foreach ($context_conditions as $condition) {
                    $condStr = $condition->toString();
                    $log = 'Found condition "' . $condStr . '" - ';
                    /*
                     * if ($widget && $condition->getExpression()->getMetaObject()->getId() == $widgetObj->getId()){
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
                             $log .= 'conflict detected - ignoring';
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
                             // Add the value of the filter (if there) as cell value
                             $value = $condition->getValue();
                             if ($value !== null && $value !== ''){
                                 $data_sheet->getFilters()->addCondition($condition);
                                 if (! $col = $data_sheet->getColumns()->getByExpression($condition->getExpression())) {
                                     $col = $data_sheet->getColumns()->addFromExpression($condition->getExpression());
                                 }
                                 if ($col->isEmpty(true)) {
                                     if ($data_sheet->isEmpty()) {
                                         $col->setValues([$value]);
                                     } else {
                                         $col->setValueOnAllRows($value);
                                     }
                                 }
                                 if ($noDataProvided === true) {
                                    $diagram .= "\n\t FilterContext -->|\"{$condStr}\"| FilterDataCreate";
                                 } else {
                                     $diagram .= "\n\t FilterContext --->|\"{$condStr}\"| Prefill";
                                 }
                                 $log .= 'applied';
                             } else {
                                 $log .= 'value empty - ignoring';
                             }
                         } catch (\Exception $e) {
                             $log .= 'error ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
                             // Do nothing if anything goes wrong. After all the context prefills are just an attempt the help
                             // the user. It's not a good Idea to throw a real error here!
                         }
                     } else {
                         $log .= 'not applicable';
                     }
                     $logBook->addLine($log);
                }
                if (strpos($diagram, 'FilterContext -') === false) {
                    if ($noDataProvided === true) {
                        $diagram .= "\n\t FilterContext -.->|Not applicable| FilterDataCreate";
                    } else {
                        $diagram .= "\n\t FilterContext -..->|Not applicable| Prefill";
                    }
                }
                if ($noDataProvided) {
                    $diagram .= "\n\t FilterDataCreate[\"Create\ncontext data\"] -->|" . DataLogBook::buildMermaidTitleForData($data_sheet) . "| Prefill";
                } 
                // If the diagram includes a refresh step in its input section, make all arrows longer!
                if (strpos($diagram, 'RefreshPrefill') !== false) {
                    $diagram = str_replace(['FilterContext -.', 'FilterContext --'], ['FilterContext -..', 'FilterContext ---'], $diagram);
                }
                $logBook->addPlaceholderValue('diagram_prefill', $diagram);
            } else {
                $logBook->addLine('Context prefill not possible: object of potential prefill data (' . $dataObj->__toString() . ') does not match widget object(' . $widgetObj->__toString() . ')');
            }
        } else {
            $logBook->addLine('No context conditions found for widget object ' . $widgetObj->__toString());
        }
        $logBook->addIndent(-1);
        return $data_sheet;
    }
    
    /**
     * Gets the prefill data by merging the preset data with the task data.
     *
     * @param TaskInterface $task
     * @return DataSheetInterface
     */
    public function getPrefillDataSheet(TaskInterface $task) : DataSheetInterface
    {
        if ($task->hasPrefillData()) {
            // If the task has some prefill data, use it
            $sheet = $task->getPrefillData();
            // Merge it with the preset if it exists
            if ($this instanceof iPrefillWidget && $this->hasPrefillDataPreset()) {
                $sheet = $this->getPrefillDataPreset($task)->importRows($sheet);
            }
        } elseif ($this->hasPrefillDataPreset()) {
            // If the task has no data, use the preset data
            $sheet = $this->getPrefillDataPreset();
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
     * @uxon-property prefill_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "columns": [{"attribute_alias":"", "formula": "="}]}
     *
     * @see iPrefillWidget::setPrefillDataSheet()
     */
    public function setPrefillDataSheet(UxonObject $uxon) : iPrefillWidget
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
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::getPrefillWithFilterContext()
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
     * @uxon-default true
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::setPrefillWithFilterContext()
     */
    public function setPrefillWithFilterContext($value) : iPrefillWidget
    {
        $this->prefill_with_filter_context = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::getPrefillWithInputData()
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
     * @uxon-default true
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::setPrefillWithInputData()
     */
    public function setPrefillWithInputData($value) : iPrefillWidget
    {
        $this->prefill_with_input_data = $value;
        return $this;
    }
    
    /**
     * Same as prefill_disabled.
     *
     * Since widgets have a property `do_not_prefill`, it is convenient to have
     * this options for actions too.
     *
     * @uxon-property do_not_prefill
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return iPrefillWidget
     */
    public function setDoNotPrefill(bool $value) : iPrefillWidget
    {
        return $this->setPrefillDisabled($value);
    }
    
    /**
     * Set to TRUE to disable the prefill for this action entirely.
     *
     * @uxon-property prefill_disabled
     * @uxon-type boolean
     * @uxon-default false
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::setDoNotPrefill($value)
     */
    public function setPrefillDisabled(bool $value) : iPrefillWidget
    {
        $value = ! $value;
        $this->setPrefillWithFilterContext($value);
        $this->setPrefillWithInputData($value);
        $this->setPrefillWithPrefillData($value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::getPrefillWithPrefillData()
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
     * @uxon-default true
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::setPrefillWithPrefillData()
     */
    public function setPrefillWithPrefillData($prefill_with_prefill_data) : iPrefillWidget
    {
        $this->prefill_with_prefill_data = $prefill_with_prefill_data;
        return $this;
    }
    
    /**
     * 
     * @return bool|NULL
     */
    public function getPrefillWithDefaults() : ?bool
    {
        return $this->prefill_with_defaults;
    }
    
    /**
     * Set to TRUE to include default values of widgets in prefill data
     * 
     * If not set explicitly, this option will be up to the facade: some will set defaults via
     * prefill, others - when generating the widget.
     * 
     * @uxon-property prefill_with_defaults
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return iPrefillWidget
     */
    public function setPrefillWithDefaults(bool $value) : iPrefillWidget
    {
        $this->prefill_with_defaults = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::hasPrefillDataPreset()
     */
    public function hasPrefillDataPreset(): bool
    {
        return is_null($this->prefill_data_preset) ? false : true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::setPrefillDataPreset()
     */
    public function setPrefillDataPreset(DataSheetInterface $dataSheet): iPrefillWidget
    {
        $this->prefill_data_preset = $dataSheet;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::getPrefillDataPreset()
     */
    public function getPrefillDataPreset(): ?DataSheetInterface
    {
        return $this->prefill_data_preset;
    }
    
    /**
     * Controls when data should be re-read from the data source before being used as prefill.
     * 
     * The prefill data structure is computed first from the input data, prefill data, various presets, 
     * etc. Right before the widget is actually prefilled, the resulting data sheet may or may not
     * be read from the data source. By default (`auto`) a best-fit scenario is determined depending
     * on whether values are missing (e.g. the input data is less, than required by the widget), an
     * input mapper was used, etc. This option allows to override this for a specific action:
     * 
     * - `always` - The data is refreshed completely based on the UID column. Any values in the
     * input/prefill data other than the UID will be overwritten with their current values from
     * the data source. This makes sure, the data in the widget is as up-to-date as possible, but
     * makes it impossible to pass changed values with the input data.
     * - `never` - The input/prefill data is not refreshed at all. This prevents additional queries
     * to the data source, but eventually missing values remain empty possibly causing inconsistent
     * data in the prefilled widget.
     * - `only_missing_values` - reads only those values from the data source, that a required for
     * the prefilled widget, but are not present in the passed input/prefill data. This means, any
     * changes in the input data remain and do not get overwritten by the data source while the
     * autoloading of missing values can still be used. This handy to use with input mappers, that
     * set default values, etc. but may produce inconsistent data in the widget if not used carefully.
     * - `auto` - **default** - one of the above options is selected as follows:
     *      - Empty input/prefill data is silently ignored => `never`
     *      - Prefill data without UIDs (more precisely if a UID is missing in at least one row) is used as-is => `never`
     *      - If the input/prefill data is enough to prefill the widget, it will not be refreshed => `never`
     *      - If an input mapper was used (and none of the above apply), `only_missing_values` will be
     *  refreshed
     *      - Otherwise the data will be refreshed before being used for prefill => `always`.
     * 
     * **NOTE:** a refresh is only possible if the input/prefill data includes UID values. If this is not
     * the case, `auto` and `never` will silently leave the data as-is, while all other options will
     * produce errors.
     * 
     * @uxon-property prefill_data_refresh
     * @uxon-type [auto,always,never,all_if_values_missing,only_missing_values]
     * @uxon-default auto
     * 
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::setPrefillDataRefresh()
     */
    public function setPrefillDataRefresh(string $value) : iPrefillWidget
    {
        $const = iPrefillWidget::class . '::REFRESH_' . strtoupper($value);
        
        if (! defined($const)) {
            throw new ActionConfigurationError($this, 'Invalid value "' . $value . '" for property "prefill_data_refresh" in action "' . $this->getAliasWithNamespace() . '"!');
        }
        
        $this->prefill_data_refresh = constant($const);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iPrefillWidget::getPrefillDataRefresh()
     */
    public function getPrefillDataRefresh() : string
    {
        return $this->prefill_data_refresh;
    }
}