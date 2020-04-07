<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on meta model aliases with optional namespace.
 * 
 * @author Ralf Mulansky
 *
 */
interface AliasSelectorWithOptionalNameSpaceInterface extends AliasSelectorInterface
{
    /**
     * 
     * @return bool
     */
    public function hasNameSpace() : bool;
}