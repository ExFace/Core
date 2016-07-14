<?php
namespace exface\Core\Actions;
/**
 * The autosuggest action is similar to the general ReadData, but it does not affect the current window filter context because the user
 * does not really perform an explicit serch here - it's merely the system helping the user to speed up input. The context, the user is
 * working it does not changed just because the system wishes to help him! 
 * 
 * Another difference is, that the autosuggest result also includes mixins like previously used entities, etc. - even if they are not
 * included in the regular result set of the ReadData action.
 * 
 * @author Andrej Kabachnik
 *
 */
class Autosuggest extends ReadData {
	protected function init(){
		$this->set_update_filter_context(false);
	}	

	// IDEA override the perform() method to include recently used object in the autosuggest results. But where can we get those object from?
	// Another window context? The filter context?
}
?>