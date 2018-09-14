<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\WorkbenchInterface;

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
    
    private $isCmsId = false;
    
    public function __construct(WorkbenchInterface $workbench, string $selectorString)
    {
        parent::__construct($workbench, $selectorString);
        if ($this->toString() === '') {
            $this->isAlias = true;
            $this->isUid = false;
        } else {
            if (! $this->isUid()) {
                $this->isCmsId = $this->getWorkbench()->getCMS()->isCmsPageId($this->toString()) ? true : false;
                $this->isAlias = ! $this->isCmsId;
            } else {
                $this->isAlias = false;
            }
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\AliasSelectorInterface::isAlias()
     */
    public function isAlias()
    {
        return $this->isAlias;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\UiPageSelectorInterface::isCmsId()
     */
    public function isCmsId() : bool
    {
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\UiPageSelectorInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        return $this->toString() === '';
    }
}