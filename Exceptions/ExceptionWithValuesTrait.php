<?php

namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionWithValuesInterface;

trait ExceptionWithValuesTrait
{
    protected string $labelSingular = '';
    protected string $labelPlural = '';
    protected string $renderingMode = ExceptionWithValuesInterface::MODE_APPEND;
    protected array $values = [];
    protected ?string $valueToken = null;

    protected ?string $baseMessage = null;
    protected ?string $messageWithoutValues = null;

    private array $modes = [
        ExceptionWithValuesInterface::MODE_PREPEND,
        ExceptionWithValuesInterface::MODE_APPEND,
        ExceptionWithValuesInterface::MODE_INSERT
    ];

    /**
     * @inheritdoc 
     * @see ExceptionWithValuesInterface::setValueLabels()
     */
    public function setValueLabels(
        string $singular = '',
        string $plural = '',
        bool $update = true
    ) : void 
    {
        $this->labelSingular = $singular;
        $this->labelPlural = $plural;
        
        if($update) {
            $this->updateMessage();
        }
    }

    /**
     * @inheritdoc
     * @see ExceptionWithValuesInterface::setRenderingMode()
     */
    public function setRenderingMode(string $mode, bool $update = true) : bool
    {
        if(!in_array($mode, $this->modes)) {
            return false;
        }
        
        $this->renderingMode = $mode;
        
        if($update) {
            $this->updateMessage();
        }
        
        return true;
    }

    /**
     * @inheritdoc
     * @see ExceptionWithValuesInterface::getRenderingMode()
     */
    public function getRenderingMode() : string
    {
        return $this->renderingMode;
    }

    /**
     * @inheritdoc
     * @see ExceptionWithValuesInterface::setValues()
     */
    public function setValues(array $values, bool $update = true) : void 
    {
        $this->values = $values;
        
        if($update) {
            $this->updateMessage();
        }
    }

    /**
     * @inheritdoc
     * @see ExceptionWithValuesInterface::getValues()
     */
    public function getValues() : array
    {
        return $this->values;
    }

    /**
     * @inheritdoc
     * @see ExceptionWithValuesInterface::getMessageWithoutValues()
     */
    public function getMessageWithoutValues() : string
    {
        if($this->messageWithoutValues === null) {
            $this->updateMessage();
        }
        
        return $this->messageWithoutValues;
    }

    /**
     * @inheritdoc
     * @see ExceptionWithValuesInterface::getMessageRaw()
     */
    public function getMessageRaw() : string
    {
        return $this->baseMessage ?? $this->baseMessage = $this->message;
    }

    /**
     * @inheritdoc
     * @see ExceptionWithValuesInterface::getValuesToken()
     */
    public function getValuesToken() : string
    {
        return $this->valueToken ?? $this->updateValuesToken();
    }

    /**
     * Re-renders the message of this instance. 
     * 
     * @return void
     */
    protected function updateMessage() : void
    {
        $base = $this->baseMessage ?? $this->baseMessage = $this->message;
        $values = $this->updateValuesToken();
        
        switch ($this->renderingMode) {
            case ExceptionWithValuesInterface::MODE_PREPEND:
                $this->message = $values . ': ' . $base;
                $this->messageWithoutValues = $base;
                break;
            case ExceptionWithValuesInterface::MODE_APPEND:
                $this->message = $base . ' ' . $values . '.';
                $this->messageWithoutValues = $base;
                break;
            case ExceptionWithValuesInterface::MODE_INSERT:
                $needle = ExceptionWithValuesInterface::INSERT;
                $this->message = str_replace($needle, $values, $base);
                $this->messageWithoutValues = str_replace($needle, '', $base);
                break;
        };
    }

    /**
     * Re-renders the values token.
     * 
     * @return string
     */
    protected function updateValuesToken() : string
    {
        $count = count($this->values);
        if($count === 0) {
            return '';
        }
        
        if(count($this->values) === 1) {
            $label = $this->labelSingular;
            $value = $this->values[0];
        } else {
            $label = $this->labelPlural;
            $value = '(' . implode(',', $this->values) . ')';
        }
        
        return $this->valueToken = $label . ' ' . $value;
    }
}