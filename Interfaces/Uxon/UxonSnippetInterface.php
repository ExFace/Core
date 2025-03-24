<?php
namespace exface\Core\Interfaces\Uxon;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface UxonSnippetInterface extends WorkbenchDependantInterface, AliasInterface, iCanBeConvertedToUxon
{
    /**
     * 
     * @return string
     */
    public function getName() : string;

    /**
     * 
     * @return MetaObjectInterface|null
     */
    public function getMetaObject() : ?MetaObjectInterface;

    /**
     * Returns all parameters supported by this snippet
     * 
     * @return UxonSnippetParameterInterface[]
     */
    public function getParameters() : array;

    /**
     * Returns TRUE if this snippet expectes parameters and FALSE otherwise
     * @return bool
     */
    public function hasParameters() : bool;

    /**
     * 
     * @param \exface\Core\Interfaces\Uxon\UxonSnippetCallInterface $call
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function render(UxonSnippetCallInterface $call) : UxonObject;
}