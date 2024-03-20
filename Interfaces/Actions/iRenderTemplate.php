<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface iRenderTemplate extends ActionInterface
{
    /**
     * Returns an array of the form [file_path => rendered_template].
     *
     * @param DataSheetInterface $inputData
     * @return string[]
     */
    public function renderTemplate(DataSheetInterface $inputData) : array;
}