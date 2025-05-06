<?php

namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\ExceptionWithValuesTrait;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\ExceptionWithValuesInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Can combine any number of `DataCheckFailedError` instances, grouping them by their messages to present a unified
 * coherent error message.
 */
class DataCheckFailedErrorMultiple 
    extends UnexpectedValueException
    implements ExceptionWithValuesInterface
{
    use ExceptionWithValuesTrait;
    
    /**
     * All errors handled by this exception.
     *
     * Grouped by error messages to avoid redundant output lines.
     * @var array
     */
    protected array $errorGroups = [];

    /**
     * @param        $message
     * @param null   $alias
     * @param null   $previous
     * @param string $singular
     * @param string $plural
     */
    public function __construct(
        $message, 
        $alias = null, 
        $previous = null, 
        string $singular = 'Row',
        string $plural = 'Rows'
    )
    {
        parent::__construct($message, $alias, $previous);
        $this->renderingMode = self::MODE_PREPEND;
        $this->labelSingular = $singular;
        $this->labelPlural = $plural;
        $this->baseMessage = $message;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultLogLevel() : string
    {
        return LoggerInterface::ERROR;
    }

    /**
     * Merges two instances of `DataCheckFailedErrorMultiple`.
     *
     * @param DataCheckFailedErrorMultiple $other
     * @param bool $updateMessage
     * If set to TRUE the error message will be updated with the new data.
     * @return void
     */
    public function merge(DataCheckFailedErrorMultiple $other, bool $updateMessage = true) : void
    {
        foreach ($other->errorGroups as $message => $data) {
            $this->setErrorForGroup($message, array_merge($this->getErrorsForGroup($message), $other->getErrorsForGroup($message)));
        }

        if($updateMessage) {
            $this->updateMessage();
        }
    }

    /**
     * Append a new error to this collection.
     *
     * @param DataCheckFailedError $error
     * @param int $rowIndex
     * Optional index of the datasheet row where the appended error occurred. Values smaller than 0 will be ignored.
     * @param bool $updateMessage
     * If set to TRUE the error message will be updated with the new data.
     * @return void
     */
    public function appendError(DataCheckFailedError $error, int $rowIndex = -1, bool $updateMessage = true) : void
    {
        if($rowIndex > -1) {
            $error->setValues([$rowIndex]);
        }
        
        // Add error to the collection. We group by message, because this removes redundant output lines.
        $this->errorGroups[$error->getMessageWithoutValues()][] = $error;

        if($updateMessage) {
            $this->updateMessage();
        }
    }

    /**
     * Regenerates the message of this error.
     *
     * You only need to call this if you used `merge()` or `appendError()` with `$updateMessage = false` before to
     * update the message with your changes.
     *
     * @return void
     */
    public function updateMessage() : void
    {
        $message = empty($this->baseMessage) ? '' : $this->baseMessage.PHP_EOL.PHP_EOL;
        $messageWithoutValues = empty($this->baseMessage) ? '' : $this->baseMessage.PHP_EOL.PHP_EOL;

        foreach ($this->errorGroups as $errorMessage => $errors){
            $rows = [];
            foreach ($errors as $error) {
                $rows = array_merge($rows, $error->getValues());
            }
            $proxy = new DataCheckFailedError($errors[0]->getDataSheet(), $errorMessage);
            $proxy->setValueLabels($this->labelSingular, $this->labelPlural, false);
            $proxy->setRenderingMode($this->getRenderingMode(), false);
            $proxy->setValues(array_unique($rows, SORT_NUMERIC));

            $message .= $proxy->getMessage() . PHP_EOL;
            $messageWithoutValues .= $proxy->getMessageWithoutValues() . PHP_EOL;
        }

        $this->message = $message;
        $this->messageWithoutValues = $messageWithoutValues;
    }

    /**
     * Get a list of errors for the specified error message.
     *
     * @param string $message
     * @return array
     */
    public function getErrorsForGroup(string $message) : array
    {
        return $this->errorGroups[$message] ?? [];
    }

    /**
     * Set the list of affected errors for the specified error message.
     *
     * @param string $message
     * @param array $value
     */
    public function setErrorForGroup(string $message, array $value) : void
    {
        $this->errorGroups[$message] = $value;
    }
    
    /**
     * @return DataCheckFailedError[]
     */
    public function getAllErrors() : array
    {
        $result = [];
        
        foreach ($this->errorGroups as $errorGroup) {
            $result = array_merge($result, $errorGroup);
        }
        
        return $result;
    }

    /**
     * STUB - does not perform any work.
     */
    public function setValues(array $values): void
    {
        // STUB
    }

    /**
     * STUB - Same as `getAllErrors()`.
     * 
     * @return DataCheckFailedError[]
     */
    public function getValues() : array
    {
        return $this->getAllErrors();
    }

    /**
     * STUB - returns an empty string.
     * 
     * @return string
     */
    public function getValuesToken(): string
    {
        return '';
    }
}