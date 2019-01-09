<?php
namespace exface\Core\Widgets;

class DataSpreadSheet extends DataMatrixOld
{

    private $formulas_enabled = true;

    public function getFormulasEnabled()
    {
        return $this->formulas_enabled;
    }

    /**
     * Set to FALSE to disable excel-like formulas.
     * 
     * @uxon-property formulas_enabled
     * @uxon-type boolean
     * @uxon-defualt true
     * 
     * @param boolean|string $value
     * @return \exface\Core\Widgets\DataSpreadSheet
     */
    public function setFormulasEnabled($value)
    {
        $this->formulas_enabled = $value;
        return $this;
    }
}
?>