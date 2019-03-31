<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\UiPage\UiPageIdNotUniqueError;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;

/**
 * A CMS-connector provides a generic interface for ExFace to communicate with
 * the CMS, that manages and displays web pages containing the widgets.
 * 
 * In particular, a CMS-connector provides methods to load and save UI pages,
 * get information about the currently logged on user, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface CmsConnectorInterface extends WorkbenchDependantInterface
{

    /**
     * Returns an href-link generated from document id an URL parameters.
     *
     * @param UiPageInterface|string $page_or_id_or_alias
     * @param string $url_params
     *            e.g. &param1=val1&param2=val2
     * @return string
     */
    public function buildUrlToPage($page_or_id_or_alias, $url_params = '');

    /**
     * Returns an href-link compilant with the current CMS based on a given URL.
     * This allows to wrap any URL in CMS-specific code, add trackers, etc.
     *
     * @param string $url            
     * @return string
     */
    public function buildUrlExternal($url);

    /**
     * Returns an internal file link compliant with the current CMS based on a given URL.
     * This allows to wrap
     * any URL in CMS-specific code, add trackers, etc.
     *
     * @param string $path_absolute            
     * @return string
     */
    public function buildUrlToFile($path_absolute);

    /**
     * Returns the user name if a user is currently logged in and an empty string otherwise.
     *
     * @return string
     */
    public function getUserName();

    /**
     * Returns TRUE if there is a named user logged in and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isUserLoggedIn();

    /**
     * Returns TRUE if the current user is an administrator.
     * Administrators are those users, that can create/edit dialogs and,
     * thus, need enhanced error reporting, debug-tools, etc.
     *
     * @return boolean
     */
    public function isUserAdmin();

    /**
     * Returns the locale string for the current user (e.g.
     * "en_US" or only "en" if merely the language is specified within the CMS).
     *
     * @return string
     */
    public function getUserLocale();

    /**
     * Returns the currently running instance of the app, the connector belongs to.
     *
     * @return AppInterface
     */
    public function getApp();

    /**
     * Escapes all CMS specific tags in the given string to make sure the string is interpreted as pure text/HTML by the CMS and not
     * as a script or facade.
     *
     * @param string $string  
     * @return string          
     */
    public function sanitizeOutput($string);

    /**
     * Similar to sanitize_output(), but use especially for exception rendering.
     * Stacktraces may easily contain special characters that
     * may be interpreted as tags by the CMS.
     *
     * @param string $string  
     * @return string          
     */
    public function sanitizeErrorOutput($string);

    /**
     * Clears ths cache of the CMS (if present)
     * 
     * @return CmsConnectorInterface
     */
    public function clearCmsCache();

    /**
     * Returns the full URL of the root of the CMS site: e.g. https://www.exface.com/demo
     * 
     * @return string
     */
    public function buildUrlToSiteRoot();
    
    /**
     * Returns the full URL of the root of the plattform API site: e.g. https://www.exface.com/demo/api
     *
     * @return string
     */
    public function buildUrlToApi();
    
    /**
     * Returns the URL to include a given path in the facade code: e.g. for CSS/JS tags in the HTML head.
     * 
     * @param string $pathFromVendorFolder
     * 
     * @return string
     */
    public function buildUrlToInclude(string $pathFromVendorFolder) : string;
    
    /**
     * Returns the CMS-ID of the passed UiPage.
     * 
     * @param UiPageInterface $page
     * @throws UiPageNotFoundError
     * @return integer
     */
    public function getPageIdInCms(UiPageInterface $page);

    /**
     * Returns the page matching the given selector: UID, qualified alias or
     * CMS-ID.
     * 
     * NOTE: If there is a page in the CMS, that replaces the matching page, the 
     * replacement will be returned unless $ignore_replacements is TRUE. 
     * 
     * Although there is extremely low probability, that identifiers of different 
     * types take the same value, it is still possible. The search is performed
     * in the follwing order: UID -> alias -> CMS-ID. Thus, if a value matches
     * the alias of one page and the CMS-ID of another, the page with the matching
     * alias will be returned by this method.
     * 
     * @param UiPageSelectorInterface $selector
     * @param boolean $ignore_replacements
     * 
     * @throws UiPageNotFoundError if no matching page can be found
     * @throws RuntimeException if there are multiple pages replacing this page
     * 
     * @return UiPageInterface
     */
    public function getPage(UiPageSelectorInterface $selector, $ignore_replacements = false) : UiPageInterface;

    /**
     * Returns the current page in the CMS.
     *
     * @return UiPageInterface
     */
    public function getPageCurrent() : UiPageInterface;

    /**
     * Saves the given page to the CMS database by creating a new one or updating
     * an existing page if the UID already exists.
     * 
     * @param UiPageInterface $page
     * 
     * @return CmsConnectorInterface
     */
    public function savePage(UiPageInterface $page);

    /**
     * Creates a new page in the CMS for the given page model
     * 
     * @param UiPageInterface $page
     * 
     * @throws UiPageIdNotUniqueError if a page with the same alias already exists
     * 
     * @return CmsConnectorInterface
     */
    public function createPage(UiPageInterface $page);

    /**
     * Updates the given page in the CMS.
     *
     * @param UiPageInterface $page
     * 
     * @throws UiPageNotFoundError if no page with a matching UID is found in the CMS
     *
     * @return CmsConnectorInterface
     */
    public function updatePage(UiPageInterface $page);

    /**
     * Deletes the given page from the CMS database.
     *
     * @param UiPageInterface $page
     * 
     * @throws UiPageNotFoundError if no matching page is found in the CMS
     *
     * @return CmsConnectorInterface
     */
    public function deletePage(UiPageInterface $page) : CmsConnectorInterface;

    /**
     * Returns if the page exists in the CMS.
     * 
     * @param UiPageSelectorInterface|string $selectorOrString
     * @return bool
     */
    public function hasPage($selectorOrString) : bool;

    /**
     * Returns all pages assigned to the given app.
     * 
     * @param AppInterface $app
     * @return UiPageInterface[]
     */
    public function getPagesForApp(AppInterface $app);
    
    /**
     * Returns the CMS-ID of the root of the menu tree.
     * 
     * @return integer
     */
    public function getPageIdRoot();
    
    /**
     * 
     * @param string $value
     * @return bool
     */
    public function isCmsPageId($value) : bool;
    
    /**
     * Returns an array with favicons structured according to the rules of Web App Manifest (property "icons").
     * 
     * @return array
     */
    public function getFavIcons() : array;
    
    /**
     * Replaces the ServiceWorker of the specified scope with the given code
     * 
     * @param string $jsCode
     * @param string $scope
     * 
     * @return string
     */
    public function setServiceWorker(string $jsCode, string $imports = '') : string;
    
    /**
     * 
     * @param string $scope
     * @return string
     */
    public function buildUrlToServiceWorker() : string;
}
?>