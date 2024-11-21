<?php

namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\TranslationInterface;

/**
 * Can combine any number of `DataCheckFailedError` instances, grouping them by their messages to present a unified coherent error message.
 */
class DataCheckFailedErrorMultiple extends UnexpectedValueException
{
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
    protected array $errors = [];

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
        foreach ($other->errors as $message => $data) {
            $this->setErrors($message, array_merge($this->getErrors($message), $other->getErrors($message)));
            $this->setAffectedRows($message, array_merge($this->getAffectedRows($message), $other->getAffectedRows($message)));
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
        $this->errors[$message]['errors'][] = $error;

        // Add row index for this error, if it is valid and does not exist already.
        if($rowIndex > -1 && !in_array($rowIndex, $this->getAffectedRows($message), true)) {
            $this->errors[$message]['affectedRows'][] = $rowIndex;
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

        foreach ($this->errors as $errorMessage => $errorData){
            if(empty($affectedRows = $this->getAffectedRows($errorMessage))){
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
    public function getAffectedRows(string $message) : array
    {
        return $this->errors[$message]['affectedRows'] ?? [];
    }

    /**
     * Set the list of affected row indices for the specified error message.
     *
     * @param string $message
     * @param array $value
     */
    public function setAffectedRows(string $message, array $value) : void
    {
        $this->errors[$message]['affectedRows'] = $value;
    }

    /**
     * Get a list of errors for the specified error message.
     *
     * @param string $message
     * @return array
     */
    public function getErrors(string $message) : array
    {
        return $this->errors[$message]['errors'] ?? [];
    }

    /**
     * Set the list of affected errors for the specified error message.
     *
     * @param string $message
     * @param array $value
     */
    public function setErrors(string $message, array $value) : void
    {
        $this->errors[$message]['errors'] = $value;
    }
}