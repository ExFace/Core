<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on meta model aliases with optional namespace.
 * 
 * @author Ralf Mulansky
 *
 */
interface AliasSelectorWithOptionalNamespaceInterface extends AliasSelectorInterface
{
    /**
     * 
     * @return bool
     */
    public function hasNamespace() : bool;
}