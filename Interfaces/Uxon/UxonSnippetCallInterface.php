<?php
namespace exface\Core\Interfaces\Uxon;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

interface UxonSnippetCallInterface extends iCanBeConvertedToUxon
{
    public function getParameters() : array;

    public function getSnippetAlias() : string;
}