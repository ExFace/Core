<?php
namespace exface\Core\Actions\Traits;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\DataSheets\DataSheetMergeError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;

trait iPrefillWidgetTrait 
{
    private $prefill_with_filter_context = false;
    
    private $prefill_with_input_data = true;
    
    private $prefill_with_prefill_data = true;
    
    private $prefill_with_data_from_widget_link = null;
    
    /** @var DataSheetInterface */
    private $prefill_data_preset = null;
    
    
    
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
        if ($widget->isPrefilled()) {
            $data_sheet = $widget->getPrefillData();
        }
        
        // Prefill with input data if not turned off
        if ($this->getPrefillWithInputData() === true && ($task->hasInputData() === true || $this->hasInputDataPreset() === true)) {
            $input_data = $this->getInputDataSheet($task);
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
        if ($data_sheet && $data_sheet->countRows() > 0 && $data_sheet->hasUidColumn(true)) {
            $data_sheet = $widget->prepareDataSheetToPrefill($data_sheet);
            if (! $data_sheet->isFresh()) {
                // Load fresh data, but make sure only those columns of the original sheet are
                // updated, that do not have any values. This is important, as the prefill sheet
                // can also have modified data, that differs from the data source. That data should
                // not be overwritten. For example, when copying pages in exface.Core.pages, the
                // ALIAS is removed explicitly. Since the copy-action requires more colums, than
                // the input data has, their values will be read here - including the current value
                // of the ALIAS. However, it must not overwrite the explicitly set empty value!
                $freshData = $data_sheet->copy();
                $freshData->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
                $freshData->dataRead();
                $data_sheet->merge($freshData, false);
            }
        }
        
        // Prefill widget using the filter contexts if the widget does not have any prefill data yet
        $data_sheet = $this->getPrefillDataFromFilterContext($widget, $data_sheet);
        
        if ($data_sheet) {
            $widget->prefill($data_sheet);
        }
        if ($prefill_data_merge_failed) {
            $widget->prefill($prefill_data);
        }
        
        return $widget;
    }
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @param WidgetInterface $widget
     * @param TaskInterface $task
     * 
     * @return DataSheetInterface
     */
    protected function getPrefillDataFromFilterContext(WidgetInterface $widget, DataSheetInterface $data_sheet = null, TaskInterface $task = null) : ?DataSheetInterface
    {
        // Prefill widget using the filter contexts if the widget does not have any prefill data yet
        // TODO Use the context prefill even if the widget already has other prefill data: use DataSheet::merge()!
        if ($this->getPrefillWithFilterContext($task) && $widget && $context_conditions = $this->getApp()->getWorkbench()->getContext()->getScopeWindow()->getFilterContext()->getConditions($widget->getMetaObject())) {
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
                             }
                         } catch (\Exception $e) {
                             // Do nothing if anything goes wrong. After all the context prefills are just an attempt the help
                             // the user. It's not a good Idea to throw a real error here!
                         }
                     }
                }
            }
        }
        return $data_sheet;
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
}