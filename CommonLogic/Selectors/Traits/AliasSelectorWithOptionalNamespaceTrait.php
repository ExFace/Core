<?php
namespace exface\Core\CommonLogic\Selectors\Traits;


/**
 * Trait with shared logic for the AliasSelectorWithNamespaceInterface
 *
 * @author Ralf Mulansky
 *
 */
trait AliasSelectorWithOptionalNamespaceTrait
{
    use AliasSelectorTrait;
    
    /**
     * 
     * @return bool
     */
    public function hasNamespace() : bool
    {
        return $this::getAppAliasFromNamespace($this->toString()) !== false;
    }
}