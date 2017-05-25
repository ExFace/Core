<?php
namespace exface\Core\Actions;

class ShowHeaders extends ShowWidget
{

    public function getResultOutput()
    {
        if ($this->getWidget()) {
            $this->prefillWidget();
            return $this->getApp()->getWorkbench()->ui()->getTemplate()->drawHeaders($this->getWidget());
        } else {
            return '';
        }
    }
}
?>