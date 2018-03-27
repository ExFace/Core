<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\ContextBar;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * A page represents on screen of the UI and is basically the model for a web page in most cases.
 * 
 * Pages can contain any number of widgets. Although multiple widget trees 
 * (multiple root containers) are supported, one of them must be set as the
 * main root widget. Additionally there are certain widgets added to every
 * page automatically like the ContextBar. These can never be used as root
 * widgets.
 * 
 * Pages are abstract models. Their actual look is determined by the template, that
 * renders the page. Templates - in turn - are managed by the CMS. Thus, depending
 * on the template selection strategy of the CMS every page can be rendered as
 * a mobile or desktop application or even as a REST-API.
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiPageInterface extends ExfaceClassInterface, AliasInterface, iCanBeConvertedToUxon
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
     * @return UiPageInterface
     */
    public function removeWidget(WidgetInterface $widget, $remove_children_too = true);

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
     * Returns the app, this page belongs to.
     * 
     * @throws RuntimeException if the page is not part of any app
     * 
     * @return AppInterface
     */
    public function getApp();

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
     * Returns the UID or alias of the parent page.
     * 
     * @return string
     */
    public function getMenuParentPageAlias();

    /**
     * Sets the parent of the page by setting an UID or an alias.
     * 
     * @param string $menuParentPageAlias
     * @return UiPageInterface
     */
    public function setMenuParentPageAlias($menuParentPageAlias);
    
    /**
     * 
     * @param string $id_or_alias
     * @return UiPageInterface
     */
    public function setMenuParentPageSelector($id_or_alias);

    /**
     * Returns the parent ui page or NULL if this page has no parent
     * 
     * @return UiPageInterface|null
     */
    public function getMenuParentPage();

    /**
     * Returns the default menu position of the page in the form 'menuParentPageAlias:menuIndex'.
     * 
     * @return string
     */
    public function getMenuDefaultPosition();

    /**
     * Sets the default menu position of the page in the form 'menuParentPageAlias:menuIndex'.
     * 
     * This is important to determine if the page has been moved manually. If it has been
     * moved, the position in the tree will not be changed during an update.
     * 
     * @param string $menuDefaultPosition
     * @return UiPageInterface
     */
    public function setMenuDefaultPosition($menuDefaultPosition);
    
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
     * Returns the menu position of the page in the form 'menuParentPageAlias:menuIndex'.
     *
     * @return string
     */
    public function getMenuPosition();

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
     * Returns the unique id of the page.
     * 
     * This id is unique across all apps!
     * 
     * @return string
     */
    public function getId();
    
    /**
     * 
     * @param string $uid
     * @return UiPageInterface
     */
    public function setId(string $uid) : UiPageInterface;

    /**
     * Returns the name of the page.
     * 
     * The name is what most templates will show as header and menu title.
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
     * The description is used as hint, tooltip or similar by most templates.
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
     * The description is used as hint, tooltip or similar by most templates.
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
     * Returns the qualified alias of the page, this one should replace when resolving widget links.
     * 
     * @return string
     */
    public function getReplacesPageAlias();

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
    public function setReplacesPageAlias($alias_with_namespace);

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
     * Generates a UID.
     * 
     * @return string
     */
    public static function generateUid();
    
    /**
     * Generates a unique alias.
     * 
     * @param string $prefix
     * @return string
     */
    public static function generateAlias($prefix);
    
    /**
     * Makes the page become part of the app identified by the given selector
     * 
     * @param AppSelectorInterface $selector
     * 
     * @return UiPageInterface
     */
    public function setApp(AppSelectorInterface $selector) : UiPageInterface;
}

?>
