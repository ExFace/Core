<?php namespace exface\Core\Interfaces;

interface CmsConnectorInterface {

	function get_page_id();
	
	function get_page_name($resource_id = null);
	
	/**
	 * Returns an href-link generated from document id an URL parameters.
	 * @param string $doc_id
	 * @param string $url_params e.g. &param1=val1&param2=val2
	 * @return string
	 */
	function create_link_internal($doc_id, $url_params='');
	
	/**
	 * Returns an href-link compilant with the current CMS based on a given URL. This allows to wrap
	 * any URL in CMS-specific code, add trackers, etc.
	 * @param string $url
	 * @return string
	 */
	function create_link_external($url);
	
	/**
	 * Removes parameters used by the CMS for internal needs from the given parameter array. $_GET or $_POST
	 * can be passed to this method to get rid of all kinds of CMS-specific variables
	 * @param array $param_array
	 * @return array
	 */
	function remove_system_request_params(array $param_array);
	
	/**
	 * Returns the user name of the user currently logged in
	 * @return string
	 */
	function get_user_name();
}
?>