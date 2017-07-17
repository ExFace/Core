<?php
namespace exface\Core\Interfaces;

interface CmsConnectorInterface extends ExfaceClassInterface
{

    /**
     * Returns the contents of the page specified by the given id
     *
     * @param string $id            
     */
    public function getPageContents($id);

    /**
     * Returns the id of the current page in the CMS
     *
     * @return string
     */
    public function getPageId();

    /**
     * Returns the title of the CMS page with the given id.
     * If no id specified, the title of the current CMS page is returned.
     *
     * @param unknown $resource_id            
     */
    public function getPageTitle($page_id = null);

    /**
     * Returns an href-link generated from document id an URL parameters.
     *
     * @param string $doc_id            
     * @param string $url_params
     *            e.g. &param1=val1&param2=val2
     * @return string
     */
    public function createLinkInternal($doc_id, $url_params = '');

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
}
?>