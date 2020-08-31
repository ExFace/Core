<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\Interfaces\Selectors\UiPageGroupSelectorInterface;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Selectors\UiPageGroupSelector;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Exceptions\UiPage\UiPageNotPartOfAppError;
use exface\Core\Exceptions\UiPage\UiPageLoadingError;
use exface\Core\Interfaces\AppInterface;

trait UiMenuItemTrait {
    
    private $groupSelectors = [];
    
    private $published = true;
    
    private $created_by_user = null;
    
    private $modified_by_user = null;
    
    private $created_on = null;
    
    private $modified_on = null;
    
    private $appSelector = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::isInGroup()
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
            $groupSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE_GROUP');
            $groupSheet->getColumns()->addFromUidAttribute();
            $groupSheet->getFilters()->addConditionFromString('ALIAS', $roleAlias);
            $groupSheet->getFilters()->addConditionFromString('APP__ALIAS', $appAlias);
            $groupSheet->dataRead();
            if ($groupSheet->countRows() === 1) {
                return $this->isInGroup(new UiPageGroupSelector($this->getWorkbench(), $groupSheet->getUidColumn()->getCellValue(0)));
            }
        }
        
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::getGroupSelectors()
     */
    public function getGroupSelectors() : array
    {
        return $this->groupSelectors;
    }
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::addGroupSelector()
     */
    public function addGroupSelector($selectorOrString) : UiMenuItemInterface
    {
        if ($selectorOrString instanceof UiPageGroupSelectorInterface) {
            $this->groupSelectors[] = $selectorOrString;
        } else {
            $this->groupSelectors[] = new UiPageGroupSelector($this->getWorkbench(), $selectorOrString);
        }
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::setCreatedByUserSelector()
     */
    public function setCreatedByUserSelector($selectorOrString) : UiMenuItemInterface
    {
        if (! (is_string($selectorOrString) || $selectorOrString instanceof UserSelectorInterface)) {
            throw new InvalidArgumentException('Invalid user selector given as creator of UI page: expecting UserSelectorInterface or string, received ' . gettype($selectorOrString));
        }
        $this->created_by_user = $selectorOrString;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::getCreatedByUserSelector()
     */
    public function getCreatedByUserSelector() : UserSelectorInterface
    {
        if ($this->created_by_user === null) {
            $this->created_by_user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid();
        }
        if (! ($this->created_by_user instanceof UserSelectorInterface)) {
            $this->created_by_user = new UserSelector($this->getWorkbench(), $this->created_by_user);
        }
        return $this->created_by_user;
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::setModifiedByUserSelector()
     */
    public function setModifiedByUserSelector($selectorOrString) : UiMenuItemInterface
    {
        if (! (is_string($selectorOrString) || $selectorOrString instanceof UserSelectorInterface)) {
            throw new InvalidArgumentException('Invalid user selector given as last modifier of UI page: expecting UserSelectorInterface or string, received ' . gettype($selectorOrString));
        }
        $this->modified_by_user = $selectorOrString;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::getCreatedByUserSelector()
     */
    public function getModifiedByUserSelector() : UserSelectorInterface
    {
        if ($this->modified_by_user === null) {
            $this->modified_by_user = $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid();
        }
        if (! ($this->modified_by_user instanceof UserSelectorInterface)) {
            $this->modified_by_user = new UserSelector($this->getWorkbench(), $this->modified_by_user);
        }
        return $this->modified_by_user;
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::setCreatedOn()
     */
    public function setCreatedOn(string $dateTimeString) : UiMenuItemInterface
    {
        $this->created_on = $dateTimeString;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::getCreatedOn()
     */
    public function getCreatedOn() : string
    {
        if ($this->created_on === null) {
            $this->created_on = DateTimeDataType::now();
        }
        return DateTimeDataType::cast($this->created_on);
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::setModifiedOn()
     */
    public function setModifiedOn(string $dateTimeString) : UiMenuItemInterface
    {
        $this->modified_on = $dateTimeString;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see UiMenuItemInterface::getModifiedOn()
     */
    public function getModifiedOn() : string
    {
        if ($this->modified_on === null) {
            $this->modified_on = DateTimeDataType::now();
        }
        return DateTimeDataType::cast($this->modified_on);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::setPublished()
     */
    public function setPublished(bool $true_or_false) : UiMenuItemInterface
    {
        $this->published = $true_or_false;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::isPublished()
     */
    public function isPublished() : bool
    {
        return $this->published;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::getApp()
     */
    public function getApp() : AppInterface
    {
        if (! is_null($this->appSelector)) {
            try {
                return $this->getWorkbench()->getApp($this->appSelector);
            } catch (\Throwable $e) {
                throw new UiPageLoadingError('Cannot load app "' . $this->appSelector->__toString() . '" for page "' . $this->getAliasWithNamespace() . '"!', null, $e);
            }
        } else {
            throw new UiPageNotPartOfAppError('The page "' . $this->getAliasWithNamespace() . '" is not part of any app!');
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::hasApp()
     */
    public function hasApp() : bool
    {
        return $this->appSelector !== null;
    }
    
    /**
     * The app, the page or menu item belongs to (if any)
     *
     * @uxon-property app
     * @uxon-type metamodel:app
     *
     * @see \exface\Core\Interfaces\Model\UiMenuItemInterface::setApp()
     */
    public function setApp(AppSelectorInterface $selector) : UiMenuItemInterface
    {
        $this->appSelector = $selector;
        return $this;
    }
}