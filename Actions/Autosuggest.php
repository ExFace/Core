<?php
namespace exface\Core\Actions;
use exface\Core\Factories\WidgetFactory;

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
	
	protected function perform(){
		// IDEA Include recently used objects in the autosuggest results. But where can we get those object from?
		// Another window context? The filter context?
		
		return parent::perform();
	}
	
	public function get_called_by_widget(){
		// This IF makes sure, the autosuggest works even if the calling widget is not specified.
		// TODO This is a potential security issue as an attacker could get access to some data (UIDs and LABELs)
		// even without his access to a specific page being checked. He would only need to have access to any one page
		// (the login page?). Once we have some kind of reading access control for meta objects, this code should be
		// rewritten.
		// IDEA Once there is some kind of default table widget for meta object, we could use it here instead of
		// simply outputting the UID and LABEL
		if (!parent::get_called_by_widget() && $this->get_workbench()->ui()->get_page_current()){
			/* @var $reading_widget \exface\Core\Widgets\DataTable */
			$reading_widget = WidgetFactory::create($this->get_workbench()->ui()->get_page_current(), 'DataTable');
			$reading_widget->set_meta_object($this->get_meta_object());
			$reading_widget->add_column($reading_widget->create_column_from_attribute($this->get_meta_object()->get_label_attribute()));
			$this->set_called_by_widget($reading_widget);
			$this->set_input_data_sheet($reading_widget->prepare_data_sheet_to_read($this->get_input_data_sheet()));
		}
		return parent::get_called_by_widget();
	}
}
?>