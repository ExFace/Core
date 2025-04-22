<?php
namespace exface\Core\Uxon\Snippets;

use exface\Core\CommonLogic\Uxon\AbstractUxonSnippet;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Uxon\UxonArraySnippetInterface;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;

class GenericArraySnippet extends AbstractUxonSnippet implements UxonArraySnippetInterface
{
    protected function setSnippet($uxonArrayOrString) : UxonSnippetInterface
    {
        return parent::setSnippet($uxonArrayOrString);
    }
}