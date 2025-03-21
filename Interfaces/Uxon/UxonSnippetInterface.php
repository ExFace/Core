<?php
namespace exface\Core\Interfaces\Uxon;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface UxonSnippetInterface extends WorkbenchDependantInterface, AliasInterface, iCanBeConvertedToUxon
{
    public function getMetaObject() : ?MetaObjectInterface;

    /**
     * 
     * @return string
     */
    public function getName() : string;

    /**
     * 
     * @param \exface\Core\Interfaces\Uxon\UxonSnippetCallInterface $call
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function render(UxonSnippetCallInterface $call) : UxonObject;
}