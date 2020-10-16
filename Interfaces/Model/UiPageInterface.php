<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\ContextBar;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;

/**
 * A page represents on screen of the UI and is basically the model for a web page in most cases.
 * 
 * Pages can contain any number of widgets. Although multiple widget trees 
 * (multiple root containers) are supported, one of them must be set as the
 * main root widget. Additionally there are certain widgets added to every
 * page automatically like the ContextBar. These can never be used as root
 * widgets.
 * 
 * Pages are abstract models. Their actual look is determined by the facade, that
 * renders the page. Facades - in turn - are managed by the CMS. Thus, depending
 * on the facade selection strategy of the CMS every page can be rendered as
 * a mobile or desktop application or even as a REST-API.
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiPageInterface extends UiMenuItemInterface, iCanBeConvertedToUxon
{
    /**
     * 
     * @return UiPageSelectorInterface
     */
    public function getSelector() : UiPageSelectorInterface;

    /**
     *
     * @param string $widget_type            
     * @param WidgetInterface $parent_widget            
     * @param string $widget_id            
     * @return WidgetInterface
     */
    public function createWidget($widget_type, WidgetInterface $parent_widget = null, UxonObject $uxon = null);

    /**
     *
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function getWidgetRoot();

    /**
     * Returns the widget with the given id from this page or FALSE if no matching widget could be found.
     * The search
     * can optionally be restricted to the children of another widget.
     *
     * @param string $widget_id            
     * @param WidgetInterface $parent  
     * 
     * @throws WidgetNotFoundError if no widget with such an id was found
     *           
     * @return WidgetInterface
     */
    public function getWidget($widget_id, WidgetInterface $parent = null);

    /**
     * Removes the widget with the given id from this page.
     * This will not remove child widgets!
     *
     * @see remove_widget() for a more convenient alternative optionally removing children too.
     *     
     * @param string $widget_id            
     * @param boolean $remove_children_too            
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public function removeWidgetById($widget_id);

    /**
     * Removes a widget from the page.
     * By default all children are removed too.
     *
     * Note, that if the widget has a parent and that parent still is on this page, the widget
     * will merely be removed from cache, but will still be accessible through page::getWidget().
     *
     * @param WidgetInterface $widget 
     * @param bool $remove_children_too
     * 
     * @triggers \exface\Core\Events\Widget\OnRemoveEvent
     * 
     * @return UiPageInterface
     */
    public function removeWidget(WidgetInterface $widget, bool $remove_children_too = true) : UiPageInterface;
    
    /**
     * Removes all widgets from the page.
     * 
     * @triggers \exface\Core\Events\Widget\OnRemoveEvent for each widget
     * 
     * @return UiPageInterface
     */
    public function removeAllWidgets() : UiPageInterface;

    /**
     *
     * @return string
     */
    public function getWidgetIdSeparator();

    /**
     *
     * @return string
     */
    public function getWidgetIdSpaceSeparator();

    /**
     * Returns TRUE if the page does not have widgets and FALSE if there is at least one widget.
     *
     * @return boolean
     */
    public function isEmpty();

    /**
     * Returns the context bar widget for this page
     * 
     * @return ContextBar
     */
    public function getContextBar();

    /**
     * Returns FALSE if the page should not be updated automatically when its
     * app is updated and TRUE otherwise (default).
     * 
     * @return boolean
     */
    public function isUpdateable();

    /**
     * If FALSE is passed, the page will not be updated with its app anymore.
     * 
     * @param boolean $true_or_false
     * @return UiPageInterface
     */
    public function setUpdateable($true_or_false);
    
    /**
     * 
     * @param string|NULL $idOrAliasOrNull
     * @return UiPageInterface
     */
    public function setParentPageSelector($idOrAliasOrNull);

    /**
     * Returns the parent page or NULL if this page has no parent.
     * 
     * If there is page, that replaces the actual parent, that page is returned by
     * befault. Set $ignoreReplacement to true to get the actual parent.
     * 
     * NOTE: getParentPageSelector() always returns the selector of the linked page
     * in contrast to this method.
     * 
     * @param bool $ignoreReplacement
     * @return UiPageInterface|null
     */
    public function getParentPage(bool $ignoreReplacement = false) : ?UiPageInterface;
    
    /**
     * Returns the index (position number starting with 0) of this page in the 
     * submenu of its parent.
     * 
     * Defaults to 0 if not set explicitly.
     *  
     * @return integer
     */
    public function getMenuIndex();

    /**
     * Sets the index (position number starting with 0) of this page in the 
     * submenu of its parent.
     *
     * @param integer $number
     * @return UiPageInterface
     */
    public function setMenuIndex($number);
    
    /**
     * 
     * @param string|UiPageSelectorInterface|NULL $selectorOrString
     * @return UiPageInterface
     */
    public function setParentPageSelectorDefault($selectorOrStringOrNull) : UiPageInterface;
    
    /**
     * 
     * @return UiPageSelectorInterface|NULL
     */
    public function getParentPageSelectorDefault() : ?UiPageSelectorInterface;
    
    /**
     * 
     * @return int|NULL
     */
    public function  getMenuIndexDefault() : ?int;
    
    /**
     * 
     * @param int $number
     * @return UiPageInterface
     */
    public function setMenuIndexDefault(int $number) : UiPageInterface;

    /**
     * Returns if the page was moved in the menu tree compared to the default menu position.
     * 
     * @return boolean
     */
    public function isMoved();

    /**
     * Returns if the page is visible in the menu.
     * 
     * @return boolean
     */
    public function getMenuVisible();

    /**
     * Sets if the page is shown in the menu. (Default: true)
     * 
     * @param boolean $menuVisible
     * @return UiPageInterface
     */
    public function setMenuVisible($menuVisible);

    /**
     * 
     * @param string $uid
     * @return UiPageInterface
     */
    public function setUid(string $uid) : UiPageInterface;

    /**
     * Returns the qualified alias of the page, this one should replace when resolving widget links.
     * 
     * @return string
     */
    public function getReplacesPageSelector() : ?UiPageSelectorInterface;

    /**
     * Specifies the alias of the page, this one will replace when resolving widget links.
     * 
     * If the page with the specified alias is referenced by a widget link or
     * an action, this page will be returned instead of the original one, thus
     * replacing it on the fly.
     * 
     * Using this replacement mechanism users can replace pages in an app by
     * their own version without having to modify the incoming links or the
     * original page itself - similarly to replacing a meta object.
     * 
     * @param string $alias_with_namespace
     * @return UiPageInterface
     */
    public function setReplacesPageSelector($alias_with_namespace);

    /**
     * Returns the raw contents of the UI page, that is stored in the CMS (stringified UXON).
     * 
     * NOTE: although similar to getWidgetRoot()->exportUxonOriginal() there
     * still can be differences as the code can create widgets from UXON on
     * the fly.
     * 
     * @return string
     */
    public function getContents();

    /**
     * Replaces the raw contents of the UI page (stringified UXON).
     * 
     * The new contents will be parsed immediately and all widgets in the page
     * will be recreated.
     * 
     * NOTE: This does not change the page in the CMS right away! Use savePage()
     * to save changes permanently!
     * 
     * @param string|UxonObject $contents
     * @return UiPageInterface
     */
    public function setContents($contents);

    /**
     * Generates a copy of the page.
     * 
     * The optional arguments page_alias, page_uid and appUidOrAlias are set on the copy of the page.
     * 
     * @param string $page_alias
     * @param string $page_uid
     * @param string $appUidOrAlias
     * @return UiPageInterface
     */
    public function copy($page_alias = null, $page_uid = null, AppSelectorInterface $appSelector = null) : UiPageInterface;
    
    /**
     * Compares two pages by their UIDs, aliases and CMS-IDs and returns
     * true if they are equal.
     * 
     * If the passed page replaces this page this function also returns true.
     * 
     * @param UiPageInterface|UiPageSelectorInterface|string $pageOrSelectorOrString
     * @return boolean
     */
    public function is($pageOrSelectorOrString) : bool;
    
    /**
     * Compares two pages by their UIDs, aliases and CMS-IDs and returns
     * true if they are equal.
     * 
     * If the passed page replaces this page this function returns false.
     * 
     * @param UiPageInterface|UiPageSelectorInterface|string $pageOrSelectorOrString
     * @return boolean
     */
    public function isExactly($pageOrSelectorOrString) : bool;
    
    /**
     * Compares two pages by their attributes.
     * 
     * The attributes with names contained in the $ignore_properties array are ignored in
     * the comparison.
     * 
     * @param UiPageInterface $page
     * @param string[] $ignore_properties
     * @return boolean
     */
    public function equals(UiPageInterface $page, $ignore_properties);
    
    /**
     * 
     * @return FacadeInterface
     */
    public function getFacade() : FacadeInterface;
    
    /**
     *
     * @param FacadeSelectorInterface|string $value
     * @return UiPageInterface
     */
    public function setFacadeSelector($selectorOrString) : UiPageInterface;
    
    /**
     * Returns TRUE if the page is part of the metamodel and FALSE if it was created programmatically.
     * 
     * Knowing this is important as only modeled pages can be linked to.
     * 
     * @return bool
     */
    public function hasModel() : bool;
}