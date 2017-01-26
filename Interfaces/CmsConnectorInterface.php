<?php namespace exface\Core\Interfaces;

interface CmsConnectorInterface extends ExfaceClassInterface {
	
	/**
	 * Returns the contents of the page specified by the given id
	 * @param string $id
	 */
	public function get_page_contents($id);
	
	/**
	 * Returns the id of the current page in the CMS
	 * @return string
	 */	
	public function get_page_id();
	
	/**
	 * Returns the title of the CMS page with the given id. If no id specified, the title of the current CMS page is returned.
	 * @param unknown $resource_id
	 */
	public function get_page_title($page_id = null);
	
	/**
	 * Returns an href-link generated from document id an URL parameters.
	 * @param string $doc_id
	 * @param string $url_params e.g. &param1=val1&param2=val2
	 * @return string
	 */
	public function create_link_internal($doc_id, $url_params='');
	
	/**
	 * Returns an href-link compilant with the current CMS based on a given URL. This allows to wrap
	 * any URL in CMS-specific code, add trackers, etc.
	 * @param string $url
	 * @return string
	 */
	public function create_link_external($url);
	
	/**
	 * Removes parameters used by the CMS for internal needs from the given parameter array. $_GET or $_POST
	 * can be passed to this method to get rid of all kinds of CMS-specific variables
	 * @param array $param_array
	 * @return array
	 */
	public function remove_system_request_params(array $param_array);
	
	/**
	 * Returns the user name of the user currently logged in
	 * @return string
	 */
	public function get_user_name();
	
	/**
	 * Returns the locale string for the current user (e.g. "en_US" or only "en" if merely the language is specified within the CMS).
	 * @return string
	 */
	public function get_user_locale();
	
	/**
	 * Returns the currently running instance of the app, the connector belongs to.
	 * @return AppInterface
	 */
	public function get_app();
}
?>