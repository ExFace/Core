<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;

/**
 * The Container is a basic widget, that contains other widgets - typically simple ones like inputs. The conainer itself is mostly invisible - it
 * is just a technical grouping element. Use it, if you just need to place multiple widgets somewhere, where only one widget is expected. The
 * Container is also a common base for many other wigdets: the Panel (a visible UI area, that contains other widgets), the Form, Tabs and Splits, etc.
 * 
 * In HTML-templates the container will either be a simple (invisible) <div> or completely invisible - thus, just a list of it's contents without
 * any wrapper.
 * 
 * @author Andrej Kabachnik
 *
 */
class Container extends AbstractWidget implements iContainOtherWidgets {
	private $widgets = array();
	
	protected function do_prefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet){
		$result = parent::do_prefill($data_sheet);
		foreach ($this->get_children() as $widget){
			$widget->prefill($data_sheet);
		}
		return $result;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_read($data_sheet);
		
		if ($this->get_meta_object_id() == $data_sheet->get_meta_object()->get_id()){
			foreach ($this->get_children() as $widget){
				 $data_sheet = $widget->prepare_data_sheet_to_read($data_sheet);
			}
		}
		
		return $data_sheet;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_prefill($data_sheet);
		
		foreach ($this->get_children() as $widget){
			$data_sheet = $widget->prepare_data_sheet_to_prefill($data_sheet);
		}
	
		return $data_sheet;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::add_widget()
	 */
	public function add_widget(AbstractWidget $widget, $position = NULL){
		$widget->set_parent($this);
		if (is_null($position) || !is_numeric($position)){
			$this->widgets[] = $widget;
		} else {
			array_splice($this->widgets, $position, 0, array($widget));
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::add_widgets()
	 */
	public function add_widgets(array $widgets){
		foreach ($widgets as $widget){
			$this->add_widget($widget);
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children() {
		return $this->get_widgets();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::get_widgets()
	 */
	public function get_widgets(){
		return $this->widgets;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::remove_widgets()
	 */
	public function remove_widgets(){
		$this->widgets = array();
		return $this;
	}
	
	/**
	 * Array of widgets in the container: each one is defined as a regular widget object.
	 * 
	 * Widgets will be displayed in the order of definition. By default all widgets will inherit the container's meta object. 
	 * 
	 * @uxon-property disabled
	 * @uxon-type boolean
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::set_widgets()
	 */
	public function set_widgets(array $widget_or_uxon_array){
		if (!is_array($widget_or_uxon_array)) return false;
		foreach ($widget_or_uxon_array as $w){
			if ($w instanceof AbstractWidget){
				$this->add_widget($w);
			} else {
				$page = $this->get_page();
				$widget = WidgetFactory::create_from_uxon($page, UxonObject::from_anything($w), $this);
				$this->add_widget($widget);
			}
		}
		return $this;
	}	 

	/**
	 * If a container is disabled, all children widgets will be disabled too.
	 * 
	 * @uxon-property disabled
	 * @uxon-type boolean
	 * 
	 * @see \exface\Core\Widgets\AbstractWidget::set_disabled()
	 */
	public function set_disabled($value){
		foreach ($this->get_children() as $child){
			$child->set_disabled($value);
		}
		return parent::set_disabled($value);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::count_widgets()
	 */
	public function count_widgets(){
		return count($this->get_widgets());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::find_children_by_attribute()
	 */
	public function find_children_by_attribute(Attribute $attribute){
		$result = array();
		
		foreach ($this->widgets as $widget){
			if ($widget instanceof iShowSingleAttribute && $widget->get_attribute() && $widget->get_attribute()->get_id() == $attribute->get_id()){
				$result[] = $widget;
			}
		}
		
		return $result;
	}
}
?>