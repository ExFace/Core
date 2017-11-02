<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Exceptions\UiPageNotFoundError;
use exface\Core\Exceptions\RuntimeException;

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
interface CmsConnectorInterface extends ExfaceClassInterface
{

    /**
     * Returns the contents of the page specified by the given id
     * 
     * @deprecated use loadPage($id)->getContents() instead
     *
     * @param string $id            
     */
    public function getPageContents($id);

    /**
     * Returns the id of the current page in the CMS
     * 
     * @deprecated use loadPageCurrent()->getAliasWithNamespace() instead
     *
     * @return string
     */
    public function getPageCurrentId();

    /**
     * Returns the title of the CMS page with the given id.
     * If no id specified, the title of the current CMS page is returned.
     * 
     * @deprecated use loadPage($resource_id)->getName() instead
     *
     * @param string $resource_id            
     */
    public function getPageTitle($resource_id = null);

    /**
     * Returns an href-link generated from document id an URL parameters.
     *
     * @param UiPageInterface|string $page_or_id_or_alias
     * @param string $url_params
     *            e.g. &param1=val1&param2=val2
     * @return string
     */
    public function createLinkInternal($page_or_id_or_alias, $url_params = '');

    /**
     * Returns an href-link compilant with the current CMS based on a given URL.
     * This allows to wrap
     * any URL in CMS-specific code, add trackers, etc.
     *
     * @param string $url            
     * @return string
     */
    public function createLinkExternal($url);

    /**
     * Returns an internal file link compliant with the current CMS based on a given URL.
     * This allows to wrap
     * any URL in CMS-specific code, add trackers, etc.
     *
     * @param string $path_absolute            
     * @return string
     */
    public function createLinkToFile($path_absolute);

    /**
     * Removes parameters used by the CMS for internal needs from the given parameter array.
     * $_GET or $_POST
     * can be passed to this method to get rid of all kinds of CMS-specific variables
     *
     * @param array $param_array            
     * @return array
     */
    public function removeSystemRequestParams(array $param_array);

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
     * as a script or template.
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
    public function getSiteUrl();

    /**
     * Returns the page matching the given identifier: UID, namespaced alias or
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
     * NOTE: Instead of directly calling loadPage($page_id_or_alias) you should
     * call exface->ui()->getPage($page_id_or_alias) because the pages are cached
     * there.
     * 
     * @param string $page_id_or_alias
     * @param boolean $ignore_replacements
     * 
     * @throws UiPageNotFoundError if no matching page can be found
     * @throws RuntimeException if there are multiple pages replacing this page
     * 
     * @return UiPageInterface
     */
    public function loadPage($page_id_or_alias, $ignore_replacements = false);

    /**
     * Returns the page matching the given alias (case insensitive!)
     * 
     * NOTE: If there is a page in the CMS, that replaces the matching page, the 
     * replacement will be returned unless $ignore_replacements is TRUE. 
     * 
     * NOTE: Instead of directly calling loadPageByAlias($alias_with_namespace)
     * you should call exface->ui()->getPage($alias_with_namespace) because the
     * pages are cached there.
     * 
     * @param string $alias_with_namespace
     * @param boolean $ignore_replacements
     * 
     * @throws UiPageNotFoundError if no matching page can be found
     * @throws RuntimeException if there are multiple pages replacing this page
     * 
     * @return UiPageInterface
     */
    public function loadPageByAlias($alias_with_namespace, $ignore_replacements = false);

    /**
     * Returns the page matching the given UID (case insensitive!)
     * 
     * NOTE: If there is a page in the CMS, that replaces the matching page, the 
     * replacement will be returned unless $ignore_replacements is TRUE. 
     * 
     * NOTE: Instead of directly calling loadPageById($uid) you should call
     * exface->ui()->getPage($uid) because the pages are cached there.
     * 
     * @param string $uid
     * @param boolean $ignore_replacements
     * 
     * @throws UiPageNotFoundError if no matching page can be found
     * @throws RuntimeException if there are multiple pages replacing this page
     * 
     * @return UiPageInterface
     */
    public function loadPageById($uid, $ignore_replacements = false);

    /**
     * Returns the current page in the CMS.
     *
     * @return UiPageInterface
     */
    public function loadPageCurrent();

    /**
     * Returns the page UID for the given UiPage or UID or alias or CMS ID.
     * 
     * @param UiPageInterface|string $page_or_id_or_alias
     * 
     * @throws UiPageNotFoundError
     * 
     * @return string
     */
    public function getPageId($page_or_id_or_alias);

    /**
     * Returns the page alias for the given UiPage or UID or alias or CMS ID.
     * 
     * @param UiPageInterface|string $page_or_id_or_alias
     * 
     * @throws UiPageNotFoundError
     * 
     * @return string
     */
    public function getPageAlias($page_or_id_or_alias);

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
     * @param UiPageInterface|string $page_or_id_or_alias
     * 
     * @throws UiPageNotFoundError if no matching page is found in the CMS
     *
     * @return CmsConnectorInterface
     */
    public function deletePage($page_or_id_or_alias);

    /**
     * Clears the recycle bin of the CMS (if present)
     * 
     * @return CmsConnectorInterface
     */
    public function clearCmsRecycleBin();

    /**
     * Returns if the page exists in the CMS.
     * 
     * @param UiPageInterface|string $page_or_id_or_alias
     * @return boolean
     */
    public function hasPage($page_or_id_or_alias);

    /**
     * Returns all pages assigned to the given app.
     * 
     * @param AppInterface $app
     * @return UiPageInterface[]
     */
    public function getPagesForApp(AppInterface $app);
}
?>