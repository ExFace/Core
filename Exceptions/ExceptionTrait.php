<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\WarningExceptionInterface;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\WidgetLinkFactory;

trait ExceptionTrait {
	
	public function export_uxon_object(){
		return new UxonObject();
	}
	
	public function import_uxon_object(UxonObject $uxon){
		foreach ($uxon as $property => $value){
			$method_name = 'set_' . $property;
			if (method_exists($this, $method_name)){
				call_user_func(array($this, $method_name), $value);
			} else {
				// Ignore invalid exception properties. They might originate from earlier versions of the export and should not bother us.
				// IDEA alternatively we can throw an exception here and catch it in those places, where we can accept wrong parameters.
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::is_warning()
	 */
	public function is_warning(){
		if ($this instanceof WarningExceptionInterface){
			return true;
		}
		return false;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::is_error()
	 */
	public function is_error(){
		return $this->is_warning() ? false : true;
	}
	
	public function create_widget(UiPageInterface $page){
		/* @var $tabs \exface\Core\Widgets\Tabs */
		$tabs = WidgetFactory::create($page, 'Tabs');
		$tabs->set_meta_object($page->get_workbench()->model()->get_object('exface.Core.ERROR'));
		$error_tab = $tabs->create_tab();
		$error_tab->set_caption('Error');
		$error_widget = WidgetFactory::create($page, 'Html');
		$error_tab->add_widget($error_widget);
		//$error_widget->set_value($page->get_workbench()->get_debugger()->print_exception($this));
		$error_widget->set_value($this->getMessage());
		$tabs->add_tab($error_tab);
		return $tabs;
	}
}
?>