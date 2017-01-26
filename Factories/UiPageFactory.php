<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\TemplateInterface;
use exface\Core\CommonLogic\UiPage;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\UiManagerInterface;

class UiPageFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param TemplateInterface $template
	 * @return \exface\Core\CommonLogic\UiPage
	 */
	public static function create(UiManagerInterface $ui, $page_id){
		$page = new UiPage($ui);
		$page->set_id($page_id);
		return $page;
	}
	
	/**
	 * Creates an empty page with a simple root container without any meta object
	 * 
	 * @param UiManagerInterface $ui
	 * @param number $page_id
	 * @return \exface\Core\CommonLogic\UiPage
	 */
	public static function create_empty(UiManagerInterface $ui, $page_id = 0){
		$page = static::create($ui, $page_id);
		$root_container = WidgetFactory::create($page, 'Container');
		$page->add_widget($root_container);
		return $page;
	}
	
	/**
	 * 
	 * @param TemplateInterface $template
	 * @param string $page_id
	 * @param string $page_text
	 * @return \exface\Core\CommonLogic\UiPage
	 */
	public static function create_from_string(UiManagerInterface $ui, $page_id, $page_text){
		$page = static::create($ui, $page_id);
		WidgetFactory::create_from_uxon($page, UxonObject::from_anything($page_text));
		return $page;
	}
	
	/**
	 * TODO This method is still unfinished!
	 * @param TemplateInterface $template
	 * @param string $page_id
	 * @return \exface\Core\CommonLogic\UiPage
	 */
	public static function create_from_cms_page(UiManagerInterface $ui, $page_id){
		$page_text = $ui->get_workbench()->CMS()->get_page_contents($page_id);
		return static::create_from_string($ui, $page_id, $page_text);
	}
}

?>