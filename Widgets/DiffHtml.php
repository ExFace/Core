<?php

namespace exface\Core\Widgets;

use exface\Core\Widgets\DiffText;

class DiffHtml extends DiffText
{
    private bool $valueIsOriginal = false;

    public function getValueIsOriginal() : bool
    {
        return $this->valueIsOriginal;
    }

    /**
     * Toggles whether value is treated as the original.
     *
     * @uxon-property value_is_original
     * @uxon-type metamodel:attribute
     *
     * @param bool $value
     * @return DiffHtml
     */
    public function setValueIsOriginal(bool $value) : DiffHtml
    {
        $this->valueIsOriginal = $value;
        return $this;
    }

    public function getOriginalValue() : string
    {
        return $this->valueIsOriginal ?
            $this->getValue() :
            $this->getOriginalValue();
    }

    public function setOriginalValue(string $value) : DiffHtml
    {
        if($this->valueIsOriginal) {
            $this->setValue($value);
        } else {
            $this->setOriginalValue($value);
        }

        return $this;
    }

    public function getCompareValue() : string
    {
        return !$this->valueIsOriginal ?
            $this->getValue() :
            $this->getOriginalValue();
    }

    public function setCompareValue(string $value) : DiffHtml
    {
        if(!$this->valueIsOriginal) {
            $this->setValue($value);
        } else {
            $this->setOriginalValue($value);
        }

        return $this;
    }
}