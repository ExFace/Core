<?php

namespace exface\Core\CommonLogic\Traits;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Adds two UXON properties (`input_rows_min` and `input_rows_max`) to a class that allow designers to specify their 
 * expectations about the number of rows present in input data. As such, this trait only makes sense for classes with
 * UXON editors that handle input data, such as Behaviors.
 */
trait iCheckInputRowsCountTrait
{
    protected ?int $inputRowsMin = null;
    protected ?int $inputRowsMax = null;

    /**
     * Validates the row count of a given data sheet.
     * 
     * @param DataSheetInterface $dataSheet
     * @return true|string
     * Returns TRUE if the row count is valid and an error message if it isn't.
     */
    protected function validateInputRowCount(DataSheetInterface $dataSheet) : bool|string
    {
        $msg = null;
        
        $count = $dataSheet->countRows();
        if ($this->getInputRowsMin() !== null && $count < $this->getInputRowsMin()) {
            $msg = 'Too few rows: Need at least ' . $this->getInputRowsMin() . ' rows, but received ' . $count . ' rows instead.';
        }

        if ($this->getInputRowsMax() !== null && $count > $this->getInputRowsMax()) {
            $msg = 'Too many rows: Can process at most ' . $this->getInputRowsMax() . ' rows, but received ' . $count .
                ' rows instead.';
        }
        
        return $msg ?? true;
    }

    /**
     * Returns the minimum number of rows the expected in the input data sheet.
     *
     * @return int|null
     */
    public function getInputRowsMin() : ?int
    {
        return $this->inputRowsMin;
    }

    /**
     * Sets the minimum number of rows expected in the input data sheet.
     *
     * @uxon-property input_rows_min
     * @uxon-type integer
     *
     * @param int $value
     * @return static
     */
    public function setInputRowsMin(int $value): static
    {
        $this->inputRowsMin = $value;
        return $this;
    }

    /**
     * Returns the maximum number of rows the expected in the input data sheet.
     *
     * @return int|null
     */
    public function getInputRowsMax() : ?int
    {
        return $this->inputRowsMax;
    }

    /**
     * Sets the maximum number of rows expected in the input data sheet.
     *
     * @uxon-property input_rows_max
     * @uxon-type integer
     *
     * @param int $value
     * @return static
     */
    public function setInputRowsMax(int $value) : static
    {
        $this->inputRowsMax = $value;
        return $this;
    }
}