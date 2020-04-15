<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;

/**
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
    public function hasMenuParent() : bool;

    /**
     * Returns the alias of the parent page (the actual parent - not a page, that replaces the parent!!!).
     * 
     * @return string
     */
    public function getMenuParentPageSelector() : ?UiPageSelectorInterface;

    /**
     * Returns the unique id of the page.
     * 
     * This id is unique across all apps!
     * 
     * @return string
     */
    public function getUid();

    /**
     * Returns the name of the page.
     * 
     * The name is what most facades will show as header and menu title.
     * 
     * @return string
     */
    public function getName();

    /**
     * Overwrites the name of the page.
     * 
     * @param string $string
     * @return UiPageInterface
     */
    public function setName($string);
    
    /**
     * Returns the description of this page.
     * 
     * The description is used as hint, tooltip or similar by most facades.
     * It is a short text describing, what functionality the page offers:
     * e.g. "View an manage meta object of installed apps" for the object-page
     * in the metamodel editor.
     * 
     * @return string
     */
    public function getDescription();
    
    /**
     * Overwrites the description of this page.
     *
     * The description is used as hint, tooltip or similar by most facades.
     * It is a short text describing, what functionality the page offers:
     * e.g. "View an manage meta object of installed apps" for the object-page
     * in the metamodel editor.
     *
     * @return string
     */
    public function setDescription($string);

    /**
     * Returns an introduction text for the page to be used in contextual help, etc.
     * 
     * @return string
     */
    public function getIntro();

    /**
     * Overwrites introduction text for the page.
     * 
     * @param string $string
     * @return UiPageInterface
     */
    public function setIntro($text);
    
    /**
     * 
     * @param bool $true_or_false
     * @return UiPageInterface
     */
    public function setPublished(bool $true_or_false) : UiPageInterface;
    
    /**
     * 
     * @return bool
     */
    public function isPublished() : bool;
}