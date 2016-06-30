<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\TemplateInterface;
use exface\Core\UiPage;
use exface\Core\UxonObject;
use exface\Core\Interfaces\UiManagerInterface;

class UiPageFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param TemplateInterface $template
	 * @return \exface\Core\UiPage
	 */
	public static function create(UiManagerInterface &$ui, $page_id){
		$page = new UiPage($ui);
		$page->set_id($page_id);
		return $page;
	}
	
	/**
	 * 
	 * @param TemplateInterface $template
	 * @param string $page_id
	 * @param string $page_text
	 * @return \exface\Core\UiPage
	 */
	public static function create_from_string(UiManagerInterface &$ui, $page_id, $page_text){
		$page = static::create($ui, $page_id);
		WidgetFactory::create_from_uxon($page, UxonObject::from_anything($page_text));
		return $page;
	}
	
	/**
	 * TODO This method is still unfinished!
	 * @param TemplateInterface $template
	 * @param string $page_id
	 * @return \exface\Core\UiPage
	 */
	public static function create_from_cms_page(UiManagerInterface &$ui, $page_id){
		$page_text = $ui->exface()->CMS()->get_page($page_id);
		return static::create_from_string($ui, $page_id, $page_text);
	}
}

?>