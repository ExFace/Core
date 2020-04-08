<?php
namespace exface\Core\Interfaces\TemplateRenderers;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

interface TemplateRendererInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function render(array $customPlaceholders = []) : string;
}