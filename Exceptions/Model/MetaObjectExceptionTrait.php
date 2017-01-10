<?php namespace exface\Core\Exceptions\Model;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Widgets\DebugMessage;

/**
 * This trait enables an exception to output meta object specific debug information: properties, attributes, behaviors, etc.
 *
 * @author Andrej Kabachnik
 *
 */
trait MetaObjectExceptionTrait {
	
	use ExceptionTrait {
		create_debug_widget as parent_create_debug_widget;
	}
	
	private $meta_object = null;
	
	public function __construct (Object $meta_object, $message, $alias = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_alias($alias);
		$this->set_meta_object($meta_object);
	}
	
	public function get_meta_object(){
		return $this->meta_object;
	}
	
	public function set_meta_object(Object $object){
		$this->meta_object = $object;
		return $this;
	}
	
	/**
	 * Exceptions for data queries can add extra tabs (e.g. an SQL-tab). Which tabs will be added depends on the implementation of
	 * the data query.
	 *
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::create_debug_widget()
	 *
	 * @param ErrorMessage
	 * @return ErrorMessage
	 */
	public function create_debug_widget(DebugMessage $error_message){
		$error_message = $this->parent_create_debug_widget($error_message);
		
		/* @var $object_editor \exface\Core\Widgets\Tabs */
		
		/* FIXME Implement non-lazy loading for tales, so we don't run into problems because the page, where the error occurs
		 * would deny ajax-requests for a widget, that is not planned there (the error widget)
		 *
		 $object_object = $page->get_workbench()->model()->get_object('exface.Core.OBJECT');
		 $object_editor = WidgetFactory::create_from_uxon($page, $object_object->get_default_editor_uxon());
		 if ($object_editor->is('Tabs')){
		 foreach ($object_editor->get_tabs() as $tab){
		 // Skip unimportant tabs
		 $skip = false;
		 switch ($tab->get_caption()){
		 case 'Default Editor': $skip = true; break;
		 }
		
		 if ($skip) continue;
		
		 // Make sure, every tab has the correct meta object (and will not fall back to the parent meta object, which would be
		 // the object of the ErrorMessage in this case
		 $tab->set_meta_object($tab->get_meta_object());
		
		 // TODO copy tabs before moving to the error message
		
		 foreach ($tab->get_children() as $child){
		 // Remove all buttons, as the ErrorMessage is read-only
		 if ($child instanceof iHaveButtons){
		 foreach ($child->get_buttons() as $button){
		 $child->remove_button($button);
		 }
		 }
		 // Make sure, no widgets use lazy loading, as it won't work for a widget, that is not part of the page explicitly
		 // for security reasons
		 if ($child instanceof iSupportLazyLoading){
		 $child->set_lazy_loading(false);
		 }
		 }
		
		 // Add the tab to the error message
		 $error_message->add_tab($tab);
		 }
		 }
		 */
		return $error_message;
	}	
	
}