<?php
namespace exface\Core\Widgets;

class DataSpreadSheet extends DataMatrixOld
{

    private $formulas_enabled = true;

    public function getFormulasEnabled()
    {
        return $this->formulas_enabled;
    }

    public function setFormulasEnabled($value)
    {
        $this->formulas_enabled = $value;
        return $this;
    }
}
?>