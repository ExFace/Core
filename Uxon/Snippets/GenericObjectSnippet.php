<?php
namespace exface\Core\Uxon\Snippets;

use exface\Core\CommonLogic\Uxon\AbstractUxonSnippet;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;

class GenericObjectSnippet extends AbstractUxonSnippet
{
    /**
     * The snippet (template)
     * 
     * @uxon-property snippet
     * @uxon-type object
     * @uxon-required true
     * @uxon-template {"": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return UxonSnippetInterface
     */
    protected function setSnippet($uxonArrayOrString) : UxonSnippetInterface
    {
        return parent::setSnippet($uxonArrayOrString);
    }
}