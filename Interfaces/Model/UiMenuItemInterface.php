<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Selectors\UiPageGroupSelectorInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * Common interface for anything, that can be put into a UI menu - pages, page tree nodes, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiMenuItemInterface extends WorkbenchDependantInterface, AliasInterface
{    
    /**
     * 
     * @return bool
     */
    public function hasParent() : bool;

    /**
     * Returns the alias of the parent page (the actual parent - not a page, that replaces the parent!!!).
     * 
     * @return string
     */
    public function getParentPageSelector() : ?UiPageSelectorInterface;

    /**
     * Returns the unique id of the page.
     * 
     * This id is unique across all apps!
     * 
     * @return string|NULL
     */
    public function getUid() : ?string;

    /**
     * Returns the name of the page.
     * 
     * The name is what most facades will show as header and menu title.
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * 
     * @param string $name
     * @return UiMenuItemInterface
     */
    public function setName(string $name) : UiMenuItemInterface;
    
    /**
     * Returns the description of this page.
     * 
     * The description is used as hint, tooltip or similar by most facades.
     * It is a short text describing, what functionality the page offers:
     * e.g. "View an manage meta object of installed apps" for the object-page
     * in the metamodel editor.
     * 
     * @return string|NULL
     */
    public function getDescription() : ?string;
    
    /**
     * Overwrites the description of this page.
     *
     * The description is used as hint, tooltip or similar by most facades.
     * It is a short text describing, what functionality the page offers:
     * e.g. "View an manage meta object of installed apps" for the object-page
     * in the metamodel editor.
     *
     * @return string
     * @return UiMenuItemInterface
     */
    public function setDescription(string $string) : UiMenuItemInterface;

    /**
     * Returns an introduction text for the page to be used in contextual help, etc.
     * 
     * @return string|NULL
     */
    public function getIntro() : ?string;

    /**
     * Overwrites introduction text for the page.
     * 
     * @param string $string
     * @return UiMenuItemInterface
     */
    public function setIntro(string $text) : UiMenuItemInterface;
    
    /**
     * 
     * @param bool $true_or_false
     * @return UiMenuItemInterface
     */
    public function setPublished(bool $true_or_false) : UiMenuItemInterface;
    
    /**
     * 
     * @return bool
     */
    public function isPublished() : bool;
    
    /**
     *
     * @param UiPageGroupSelectorInterface $selector
     * @return bool
     */
    public function isInGroup(UiPageGroupSelectorInterface $selector) : bool;
    
    /**
     * 
     * @param UiPageGroupSelectorInterface|string $selectorOrString
     * @return UiMenuItemInterface
     */
    public function addGroupSelector($selectorOrString) : UiMenuItemInterface;
    
    /**
     * 
     * @return UiPageGroupSelectorInterface[]
     */
    public function getGroupSelectors() : array;
    
    /**
     *
     * @param UserSelectorInterface|string $createdBy
     * @return UiMenuItemInterface
     */
    public function setCreatedByUserSelector($createdBy) : UiMenuItemInterface;
    
    /**
     *
     * @return UserSelectorInterface
     */
    public function getCreatedByUserSelector() : UserSelectorInterface;
    
    /**
     *
     * @param UserSelectorInterface|string $createdBy
     * @return UiMenuItemInterface
     */
    public function setModifiedByUserSelector($modifiedBy) : UiMenuItemInterface;
    
    /**
     *
     * @return UserSelectorInterface
     */
    public function getModifiedByUserSelector() : UserSelectorInterface;
    
    /**
     *
     * @param string $dateTimeString
     * @return UiMenuItemInterface
     */
    public function setCreatedOn(string $dateTimeString) : UiMenuItemInterface;
    
    /**
     *
     * @return string
     */
    public function getCreatedOn() : string;
    
    /**
     *
     * @param string $dateTimeString
     * @return UiMenuItemInterface
     */
    public function setModifiedOn(string $dateTimeString) : UiMenuItemInterface;
    
    /**
     *
     * @return string
     */
    public function getModifiedOn() : string;
    
    /**
     * Returns the app, this page belongs to.
     *
     * @throws RuntimeException if the page is not part of any app
     *
     * @return AppInterface
     */
    public function getApp() : AppInterface;
    
    /**
     * Makes the page become part of the app identified by the given selector
     *
     * @param AppSelectorInterface $selector
     *
     * @return UiPageInterface
     */
    public function setApp(AppSelectorInterface $selector) : UiMenuItemInterface;
    
    /**
     * Returns TRUE if the page is part of an app and FALSE if it is not assigned to any app.
     *
     * @return bool
     */
    public function hasApp() : bool;
}