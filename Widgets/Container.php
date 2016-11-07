<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
class Container extends AbstractWidget implements iHaveChildren {
	private $widgets = array();
	
	public function prefill (\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet){
		$result = parent::prefill($data_sheet);
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
	 * @param AbstractWidget $widget
	 * @param int $position
	 * @return Container
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
	 * @param AbstractWidget[] $widgets
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
	 * Returns all widgets the panel contains as an array
	 * @return AbstractWidget[]
	 */
	public function get_widgets(){
		return $this->widgets;
	}
	
	public function remove_widgets(){
		$this->widgets = array();
		return $this;
	}
	
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
	 * If a container is disabled, all children widgets will be disabled too
	 * @see \exface\Core\Widgets\AbstractWidget::set_disabled()
	 */
	public function set_disabled($value){
		foreach ($this->get_children() as $child){
			$child->set_disabled($value);
		}
		return parent::set_disabled($value);
	}
	
	/**
	 * Returns the current number of child widgets
	 * @return int
	 */
	public function count_widgets(){
		return count($this->get_widgets());
	}
	
	/**
	 * 
	 * @param Attribute $attribute
	 * @return AbstractWidget[]
	 */
	public function get_widgets_by_attribute(Attribute $attribute){
		$result = array();
		foreach ($this->widgets as $widget){
			if ($widget instanceof iShowSingleAttribute && $widget->get_attribute()->get_id() == $attribute->get_id()){
				$result[] = $widget;
			}
		}
		return $result;
	}
}
?>