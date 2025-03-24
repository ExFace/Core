<?php
namespace exface\Core\Interfaces\Uxon;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

interface UxonSnippetParameterInterface extends iCanBeConvertedToUxon
{
    public function getName() : string;

    public function isRequired() : bool;

    public function parseValue($val) : string;
}