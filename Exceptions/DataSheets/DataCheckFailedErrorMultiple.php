<?php

namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\TranslationInterface;

/**
 * Can combine any number of `DataCheckFailedError` instances, grouping them by their messages to present a unified
 * coherent error message.
 */
class DataCheckFailedErrorMultiple extends UnexpectedValueException
{
    private const KEY_ERRORS = 'errors';
    private const KEY_ROWS = 'affectedRows';
    
    /**
     * All errors handled by this exception.
     *
     * Grouped by error messages to avoid redundant output lines.
     *
     * ```
     *
     * [$errorMessage =>
     *      ['errors' => [$errors] ],
     *      ['affectedRows' => [$rows] ]
     * ]
     *
     * ```
     *
     * @var array
     */
    protected array $errorGroups = [];

    private string $baseMessage = '';

    private ?TranslationInterface $translator;

    /**
     * @inheritDoc
     */
    public function __construct($message, $alias = null, $previous = null, TranslationInterface $translator = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->baseMessage = $message;
        $this->translator = $translator;
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
            $this->setAffectedRowsForGroup($message, array_merge($this->getAffectedRowsForGroup($message), $other->getAffectedRowsForGroup($message)));
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
        $message = $error->getMessage();

        // Add error to the collection. We group by message, because this removes redundant output lines.
        $this->errorGroups[$message][self::KEY_ERRORS][] = $error;

        // Add row index for this error, if it is valid and does not exist already.
        if($rowIndex > -1 && !in_array($rowIndex, $this->getAffectedRowsForGroup($message), true)) {
            $this->errorGroups[$message][self::KEY_ROWS][] = $rowIndex;
        }

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
        $trsLine = $this->translator ? $this->translator->translate('BEHAVIOR.VALIDATINGBEHAVIOR.LINE') : 'Lines';
        $updatedMessage = empty($this->baseMessage) ? '' : $this->baseMessage.PHP_EOL.PHP_EOL;

        foreach ($this->errorGroups as $errorMessage => $errorData){
            if(empty($affectedRows = $this->getAffectedRowsForGroup($errorMessage))){
                $updatedMessage .= $errorMessage;
            } else {
                $affectedRows = implode(', ', $affectedRows);
                $updatedMessage .= $trsLine . ' (' . $affectedRows . '): ' . $errorMessage;
            }

            $updatedMessage .= PHP_EOL;
        }

        $this->message = $updatedMessage;
    }

    /**
     * Get a list of affected row indices for the specified error message.
     *
     * @param string $message
     * @return array
     */
    public function getAffectedRowsForGroup(string $message) : array
    {
        return $this->errorGroups[$message][self::KEY_ROWS] ?? [];
    }

    /**
     * Set the list of affected row indices for the specified error message.
     *
     * @param string $message
     * @param array $value
     */
    public function setAffectedRowsForGroup(string $message, array $value) : void
    {
        $this->errorGroups[$message][self::KEY_ROWS] = $value;
    }

    /**
     * Get a list of errors for the specified error message.
     *
     * @param string $message
     * @return array
     */
    public function getErrorsForGroup(string $message) : array
    {
        return $this->errorGroups[$message][self::KEY_ERRORS] ?? [];
    }

    /**
     * Set the list of affected errors for the specified error message.
     *
     * @param string $message
     * @param array $value
     */
    public function setErrorForGroup(string $message, array $value) : void
    {
        $this->errorGroups[$message][self::KEY_ERRORS] = $value;
    }
    
    /**
     * @return DataCheckFailedError[]
     */
    public function getAllErrors() : array
    {
        $result = [];
        
        foreach ($this->errorGroups as $errorGroup) {
            $result = array_merge($result, $errorGroup[self::KEY_ERRORS]);
        }
        
        return $result;
    }
}