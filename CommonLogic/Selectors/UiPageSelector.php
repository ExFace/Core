<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\UidSelectorTrait;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;

/**
 * Default implementation of the UiPageSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class UiPageSelector extends AbstractSelector implements UiPageSelectorInterface
{
    use AliasSelectorTrait;
    use UidSelectorTrait;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     */
    public function __construct(WorkbenchInterface $workbench, string $selectorString)
    {
        parent::__construct($workbench, $selectorString);
        if ($this->toString() === '') {
            $this->isAlias = true;
            $this->isUid = false;
        } else {
            $this->isAlias = ! $this->isUid();
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