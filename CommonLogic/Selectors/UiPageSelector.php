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
        isAlias as isAliasViaTrait;
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
            case (! ($this->isUid() || $this->isCmsId())):
                return $this->toString();
            default:
                return $this->getWorkbench()->getCMS()->getPage($this)->getAliasWithNamespace();
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        return $this->isCmsId() || $this->isUid() ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\UiPageSelectorInterface::isCmsId()
     */
    public function isCmsId()
    {
        // FIXME for some reason asking the CMS is significantly slower, but ideally the CMS should
        // decide, wether the value is a valid cms page id. Although it is very probable, the page
        // ids inside the CMS are numeric.
        /*if (is_null($this->isCmsId)) {
            $this->isCmsId = $this->getWorkbench()->getCMS()->validateCmsPageId($this->toString()) ? true : false;
        }
        return $this->isCmsId;*/
        return is_numeric($this->toString());
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