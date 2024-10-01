<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface iRenderTemplate extends ActionInterface
{
    /**
     * Returns an array of the form [file_path => rendered_template/binary].
     *
     * @param DataSheetInterface $inputData
     * @return string[]
     */
    public function renderTemplate(DataSheetInterface $inputData) : array;

    /**
     * Renders HTML previews for all templates
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $inputData
     * @return string[]
     */
    public function renderPreviewHTML(DataSheetInterface $inputData) : array;
}