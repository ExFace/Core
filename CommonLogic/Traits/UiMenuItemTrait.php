<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\Interfaces\Selectors\UiPageGroupSelectorInterface;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Selectors\UiPageGroupSelector;

trait UiMenuItemTrait {
    
    private $groupSelectors = [];
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::isInGroup()
     */
    public function isInGroup(UiPageGroupSelectorInterface $selector): bool
    {
        foreach ($this->getGroupSelectors() as $rs) {
            if ($rs->__toString() === $selector->__toString()) {
                return true;
            }
        }
        
        // If the selector is an alias and it's not one of the built-in aliases, look up the
        // the UID and check that.
        if ($selector->isAlias()) {
            $appAlias = $selector->getAppAlias();
            $roleAlias = StringDataType::substringAfter($selector->toString(), $appAlias . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER);
            $roleSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE_GROUP');
            $roleSheet->getColumns()->addFromUidAttribute();
            $roleSheet->getFilters()->addConditionFromString('ALIAS', $roleAlias);
            $roleSheet->getFilters()->addConditionFromString('APP__ALIAS', $appAlias);
            $roleSheet->dataRead();
            if ($roleSheet->countRows() === 1) {
                return $this->hasRole(new UiPageSelector($this->getWorkbench(), $roleSheet->getUidColumn()->getCellValue(0)));
            }
        }
        
        return false;
    }
    
    protected function getGroupSelectors() : array
    {
        return $this->groupSelectors;
    }
    
    public function addGroupSelector($selectorOrString) : UiMenuItemInterface
    {
        if ($selectorOrString instanceof UiPageGroupSelectorInterface) {
            $this->groupSelectors[] = $selectorOrString;
        } else {
            $this->groupSelectors[] = new UiPageGroupSelector($this->getWorkbench(), $selectorOrString);
        }
        return $this;
    }
}