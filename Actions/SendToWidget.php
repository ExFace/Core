<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * Sends the input data of the action or a single value from it to a linked widget.
 * 
 * NOTE: Data and values are sent as-is. Being a front-end action, `SendToWidget` cannot read additional data or apply mappers.
 * 
 * The receiving widget must be configured to be able to accept the data: it must be based on the same object and must
 * handle all required columns. Columns in the action data, that are not explicitly configured in the receiving widget
 * will be ignored. There is no automation like in the case of lazy-loading-actions of data widgets, where the action
 * automatically reads data required for the widget.
 *
 * @author Andrej Kabachnik
 *        
 */
class SendToWidget extends AbstractAction
{
    const SUPPORTED_AGGREGATORS = [
        AggregatorFunctionsDataType::SUM,
        AggregatorFunctionsDataType::AVG,
        AggregatorFunctionsDataType::MIN,
        AggregatorFunctionsDataType::MAX,
        AggregatorFunctionsDataType::COUNT,
        AggregatorFunctionsDataType::COUNT_DISTINCT,
        AggregatorFunctionsDataType::LIST_ALL,
        AggregatorFunctionsDataType::LIST_DISTINCT
    ];

    const EXISTING_DATA_REPLACE = 'replace';
    const EXISTING_DATA_MERGE_BY_UID = 'merge_by_uid';

    private $target_widget_id = null;
    private $existing_data = self::EXISTING_DATA_REPLACE;
    private $send_value_pointer = null;
    private $send_value_parsed = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setIcon(Icons::SIGN_IN);
        $this->setConfirmationForUnsavedChanges(false);
    }
    
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        if ($task->hasInputData()) {
            return ResultFactory::createDataResult($task, $task->getInputData());
        } 
        return ResultFactory::createMessageResult($task, '');
    }
    
    /**
     *
     * @return boolean
     */
    public function getTargetWidgetId()
    {
        $widgetDefinedIn = $this->isDefinedInWidget() ? $this->getWidgetDefinedIn() : null;
        return WidgetFactory::ensureIdSpace($this->target_widget_id, null, $widgetDefinedIn);
    }
    
    /**
     * The id of the widget to receive the data.
     *
     * @uxon-property target_widget_id
     * @uxon-type uxon:$..id
     *
     * @param boolean $value
     * @return \exface\Core\Actions\ShowLookupDialog
     */
    public function setTargetWidgetId($value)
    {
        $this->target_widget_id = $value;
        return $this;
    }

    /**
     * What to do with data already present in the target widget
     * 
     * - `replace` (default) - the target widget's data is replaced entirely by the data sent
     * - `merge_by_uid` - rows of the data sent are matched to existing rows of the target by their UID. Only cells,
     * that are present in the data sent, are overwritten. Rows without a UID or without a matching row in the target
     * are ignored. All other rows and cells of the target remain untouched.
     * 
     * `merge_by_uid` is performed in the browser because only the front-end knows the current data of the target 
     * widget. It requires a data widget with a UID column and has no effect in combination with `send_value`.
     * 
     * @uxon-property existing_data
     * @uxon-type [replace,merge_by_uid]
     * @uxon-default replace
     * 
     * @param string $value
     * @throws ActionConfigurationError
     * @return SendToWidget
     */
    public function setExistingData(string $value) : SendToWidget
    {
        $value = strtolower($value);
        if (! in_array($value, [self::EXISTING_DATA_REPLACE, self::EXISTING_DATA_MERGE_BY_UID], true)) {
            throw new ActionConfigurationError($this, 'Invalid value "' . $value . '" for `existing_data` in action ' . $this->getAliasWithNamespace() . ': use "replace" or "merge_by_uid"!');
        }
        $this->existing_data = $value;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getExistingData() : string
    {
        return $this->existing_data;
    }

    /**
     * Send a single value instead of the entire data
     * 
     * Instead of passing the entire input data to a widget, you can pass a single value from the data. This will only
     * work for single-value widgets like `Display` or `Input` of course. To extract the value, use the following syntax:
     * 
     * - `!MYCOL` or `!MYCOL:SUM` will send the value of the specified column. If the data has multiple rows, their
     * values will be concatenated using the default value list delimiter of the column (a comma by default).
     * - `!MYCOL:SUM` will aggregate the values of `MYCOL` if the column is part of the data, but does not have an aggregator
     * by itself. This way, you can turn a multi-row column into a single value. You can even do `!MYCOL:COUNT:SUM` to sum
     * values of an aggregated column `MYCOL:COUNT`. 
     * - `!MYCOL:3` - will send the value from the given row (starting with 0)
     * 
     * @uxon-property send_value
     * @uxon-type string
     * 
     * @param string $pointer
     * @return SendToWidget
     */
    public function setSendValue(string $pointer) : SendToWidget
    {
        $this->send_value_pointer = $pointer;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function hasSendValue() : bool
    {
        return $this->send_value_pointer !== null;
    }

    /**
     * 
     * @return string|NULL
     */
    public function getSendValue() : ?string
    {
        return $this->send_value_pointer;
    }

    /**
     * Returns the name of the data column referenced by the `send_value` pointer.
     * 
     * @return string
     */
    public function getSendValueColumnName() : string
    {
        return $this->parseSendValuePointer()['column'];
    }

    /**
     * Returns the aggregator to apply to the column values (e.g. `SUM`) or NULL if none was specified.
     * 
     * @return string|NULL
     */
    public function getSendValueAggregator() : ?string
    {
        return $this->parseSendValuePointer()['aggregator'];
    }

    /**
     * Returns the row index to extract the value from (e.g. `3`) or NULL if none was specified.
     * 
     * @return int|NULL
     */
    public function getSendValueRowIndex() : ?int
    {
        return $this->parseSendValuePointer()['row'];
    }

    /**
     * Parses the `send_value` pointer into its column name, aggregator and row-index parts.
     * 
     * The pointer syntax is `!<column>`, `!<column>:<aggregator>` or `!<column>:<row index>`. Since
     * data sheet column names may themselves contain a colon (e.g. `MYCOL:COUNT` for an aggregated
     * column), only the last colon-separated segment is treated as a modifier - and only if it is
     * either numeric (row index) or a known aggregator function. Otherwise the entire pointer is
     * treated as the column name.
     * 
     * @return array{column: string, aggregator: string|NULL, row: int|NULL}
     */
    protected function parseSendValuePointer() : array
    {
        if ($this->send_value_parsed !== null) {
            return $this->send_value_parsed;
        }
        
        $pointer = ltrim($this->send_value_pointer ?? '', '!');
        $lastColonPos = strrpos($pointer, ':');
        
        switch (true) {
            case $lastColonPos === false:
                $parsed = ['column' => $pointer, 'aggregator' => null, 'row' => null];
                break;
            case is_numeric($modifier = substr($pointer, $lastColonPos + 1)):
                $parsed = ['column' => substr($pointer, 0, $lastColonPos), 'aggregator' => null, 'row' => (int) $modifier];
                break;
            case in_array(strtoupper($modifier), self::SUPPORTED_AGGREGATORS, true):
                $parsed = ['column' => substr($pointer, 0, $lastColonPos), 'aggregator' => strtoupper($modifier), 'row' => null];
                break;
            default:
                $parsed = ['column' => $pointer, 'aggregator' => null, 'row' => null];
        }
        
        $this->send_value_parsed = $parsed;
        return $parsed;
    }
}