<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;

/**
 * Default implementation of the UiPageSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class UiPageSelector extends AbstractSelector implements UiPageSelectorInterface
{
    use AliasSelectorTrait {
        getAppAliasFromNamespace as getAppAliasFromNamespaceViaTrait;
    }
    use UidSelectorTrait;
    
    private $isCmsId = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        switch (true) {
            case ($this->isAlias()):
                return $this->toString();
            default:
                return $this->getWorkbench()->getCMS()->loadPage($this->toString())->getAliasWithNamespace();
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\UiPageSelectorInterface::isCmsId()
     */
    public function isCmsId()
    {
        if (is_null($this->isCmsId)) {
            $this->isCmsId = (! $this->isUid() && ! $this->isAlias()) ? true : false;
        }
        return $this->isCmsId;
    }
    
    public static function getAppAliasFromNamespace($aliasWithNamespace)
    {
        return self::getAppAliasFromNamespaceViaTrait($aliasWithNamespace);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\SelectorInterface::getComponentType()
     */
    public function getComponentType() : string
    {
        return 'page';
    }
}