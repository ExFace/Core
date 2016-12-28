<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Widgets\iHaveColumnGroups;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class Data extends AbstractWidget implements iHaveColumns, iHaveColumnGroups, iHaveButtons, iHaveFilters, iSupportLazyLoading {
	// properties
	private $paginate = true;
	private $paginate_page_sizes = array(10, 20, 30, 40, 50);
	private $paginate_default_page_size = 20;
	private $aggregate_by_attribute_alias = null;
	private $lazy_loading = true; // Data should be loaded lazily by defaul (via AJAX) - of course, only if the used template supports this
	private $lazy_loading_action = 'exface.Core.ReadData';
	
	/** @var DataColumnGroup[] */
	private $column_groups = array();
	/** @var Button[] */
	private $buttons = array();
	/** @var Filter[] */
	private $filters = array();
	/** @var Filter[] */
	private $quick_search_filters = array();
	// other stuff
	/** @var \stdClass[] */
	private $sorters = array();
	
	/** @var boolean */
	private $is_editable = false;
	
	/** @var WidgetLinkInterface */
	private $refresh_with_widget = null;
	
	/**
	 * @uxon text_empty The text to be displayed, if there are no data records
	 * @var string
	 */
	private $text_empty = null;
	
	protected function init(){
		parent::init();
		if (count($this->get_column_groups()) == 0){
			$this->add_column_group($this->get_page()->create_widget('DataColumnGroup', $this));
		}
	}
	
	public function add_column(DataColumn $column){
		$this->get_column_group_main()->add_column($column);
		return $this;
	}
	
	function create_column_from_attribute(Attribute $attribute, $caption=null, $hidden=null){
		return $this->get_column_group_main()->create_column_from_attribute($attribute, $caption, $hidden);
	}
	
	/**
	 * Returns the id of the column holding the UID of each row. By default it is the column with the UID attribute of
	 * the meta object displayed in by the data widget, but this can be changed in the UXON description if required.
	 * @return string
	 */
	function get_uid_column_id(){
		return $this->get_column_group_main()->get_uid_column_id();
	}
	
	/**
	 * Sets the id of the column to be used as UID for each data row
	 * @param string $value
	 */
	function set_uid_column_id($value){
		$this->get_column_group_main()->set_uid_column_id($value);
		return $this;
	}
	
	public function get_uid_column(){
		return $this->get_column_group_main()->get_uid_column();
	}

	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_read($data_sheet);
		
		// Columns & Totals
		if ($this->get_meta_object_id() == $data_sheet->get_meta_object()->get_id()){
			foreach ($this->get_columns() as $col) {
				// Only add columns, that actually have content. The other columns exist only in the widget
				// TODO This check will get more complicated, once the content can be specified not only via attribute_alias
				// but also with properties like formula, etc.
				if (!$col->get_attribute_alias()) continue;
				$data_column = $data_sheet->get_columns()->add_from_expression($col->get_attribute_alias(), $col->get_data_column_name(), $col->is_hidden());
				// Add a total to the data sheet, if the column has a footer
				// TODO wouldn't it be better to use the column id here?
				if ($col->has_footer()) {
					$total = DataColumnTotalsFactory::create_from_string($data_column, $col->get_footer());
					$data_column->get_totals()->add($total);
				}
			}
		}
		
		// Aggregations
		foreach ($this->get_aggregations() as $attr){
			$data_sheet->get_aggregators()->add_from_string($attr);
		}
		
		return $data_sheet;
	}
	
	/**
	 * {@inheritDoc}
	 * 
	 * To prefill a dataSet we need to filter it's results, so that they are related to the object we prefill
	 * with. Thus, the prefill data needs to contain the UID of that object.
	 * 
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_prefill($data_sheet);
		if ($data_sheet->get_meta_object()->get_id() == $this->get_meta_object_id()){
			// If trying to prefill with an instance of the same object, we actually just need the uid column in the resulting prefill
			// data sheet. It will probably be there anyway, but we still add it here (just in case).
			$data_sheet->get_columns()->add_from_expression($this->get_meta_object()->get_uid_alias());
		} else {		
			// If trying to prefill with a different object, we need to find a relation to that object somehow.
			// First we check for filters based on the prefill object. If filters exists, we can be sure, that those 
			// are the ones to be prefilled. 
			$relevant_filters = $this->find_filters_by_object($data_sheet->get_meta_object());
			$uid_filters_found = false;
			// If there are filters over UIDs of the prefill object, just get data for these filters for the prefill, 
			// because it does not make sense to fetch prefill data for UID-filters and attribute filters at the same
			// time. If data for the other filters will be found in the prefill sheet when actually doing the prefilling,
			// it should, of course, be applied too, but we do not tell ExFace to always fetch this data.
			foreach ($relevant_filters as $fltr){
				if ($fltr->get_attribute()->is_relation() && $fltr->get_attribute()->get_relation()->get_related_object()->is_exactly($data_sheet->get_meta_object())){
					$data_sheet = $fltr->prepare_data_sheet_to_prefill($data_sheet);
					$uid_filters_found = true;
				}
			}
			// If thre are no UID-filters, than we can request data for the other filters.
			if (count($relevant_filters) > 0 && !$uid_filters_found){
				foreach ($relevant_filters as $fltr){
					$data_sheet = $fltr->prepare_data_sheet_to_prefill($data_sheet);
				}
			}
			
			// If there is no filter defined explicitly, try to find a relation and create a corresponding filter
			if (!$fltr){
				// TODO currently this only works for direct relations, not for chained ones.
				// FIXME check, if a filter on the current relation is there already, and add it only in this case
				/* @var $rel \exface\Core\CommonLogic\Model\relation */
				if ($rel = $this->get_meta_object()->find_relation($data_sheet->get_meta_object())){
					$fltr = $this->create_filter_from_relation($rel);
					$data_sheet = $fltr->prepare_data_sheet_to_prefill($data_sheet);
				}
			}
		}
		return $data_sheet;
	}
	
	/**
	 * IDEA Separate DataColumnFooter widget??
	 * @return NULL[]
	 */
	function get_totals() {
		$totals = array();
		foreach ($this->columns as $col) {
			if ($col->has_footer()) {
				$totals[$col->get_attribute_alias()] = $col->get_footer();
			}
		}
		return $totals;
	}
	
	/**
	 * Returns an array with all columns of the grid. If no columns have been added yet,
	 * default display attributes of the meta object are added as columns automatically.
	 * @return DataColumn[]
	 */
	public function get_columns() {
		// If no columns explicitly specified, add the default columns
		if (count($this->get_column_groups()) == 1 && $this->get_column_group_main()->is_empty()){
			$this->add_columns_for_default_display_attributes();
		}
		
		$columns = array();
		if (count($this->get_column_groups()) == 1){
			return $this->get_column_group_main()->get_columns();
		} else {
			foreach ($this->get_column_groups() as $group){
				$columns = array_merge($columns, $group->get_columns());
			}
		}
		return $columns;
	}
	
	/**
	 * Returns the number of currently contained columns over all column groups. 
	 * NOTE: This does not trigger the creation of any default columns!
	 * @return number
	 */
	public function count_columns(){
		$count = 0;
		foreach ($this->get_column_groups() as $group){
			$count += $group->count_columns();
		}
		return $count;
	}
	
	/**
	 * Creates and adds columns based on the default attributes of the underlying meta object (the ones marked with default_display_order)
	 * @return Data
	 */
	public function add_columns_for_default_display_attributes(){
		// add the default columns
		$def_attrs = $this->get_meta_object()->get_attributes()->get_default_display_list();
		foreach ($def_attrs as $attr) {
			$alias = ($attr->get_relation_path()->to_string() ? $attr->get_relation_path()->to_string() . RelationPath::RELATION_SEPARATOR : '') . $attr->get_alias();
			$this->add_column($this->create_column_from_attribute($this->get_meta_object()->get_attribute($alias)));
		}
		return $this;
	}
	
	function get_column($column_id){
		foreach ($this->get_columns() as $col){
			if ($col->get_id() === $column_id){
				return $col;
			}
		}
		return false;
	}
	
	/**
	 * Returns the first column with a matching attribute alias.
	 * @param string $alias_with_relation_path
	 * @return \exface\Core\Widgets\DataColumn|boolean
	 */
	public function get_column_by_attribute_alias($alias_with_relation_path){
		foreach ($this->get_columns() as $col){
			if ($col->get_attribute_alias() === $alias_with_relation_path){
				return $col;
			}
		}
		return false;
	}
	
	public function get_column_by_data_column_name($data_sheet_column_name){
		foreach ($this->get_columns() as $col){
			if ($col->get_attribute_alias() === $data_sheet_column_name){
				return $col;
			}
		}
		return false;
	}
	
	/**
	 * Returns an array with columns containing system attributes
	 * @return \exface\Core\Widgets\DataColumn[]
	 */
	function get_columns_with_system_attributes(){
		$result = array();
		foreach ($this->get_columns() as $col){
			if ($col->get_attribute() && $col->get_attribute()->is_system()){
				$result[] = $col;
			}
		}
		return $result;
	}
	
	/**
	 * The columns array of a data widget can contain columns or column groups or a mixture of those. 
	 * At this point, we must sort them apart
	 * and make sure, all columns get wrappen in groups. Directly specified columns will get a generated
	 * group, which won't have anything but the column list. If we have a user specified column group
	 * somewhere in the middle, there will be two generated groups left and right of it. This makes sure,
	 * that the get_columns() method, which lists all columns from all groups will list them in exact the
	 * same order as the user had specified!
	 * 			
	 * @see \exface\Core\Interfaces\Widgets\iHaveColumns::set_columns()
	 */
	public function set_columns(array $columns) {
		$column_groups = array();
		$last_element_was_a_column_group = false;
		// Loop through all uxon elements in the columns array and separate columns and column groups
		// This is nesseccary because column groups can be created in short notation (just like a regular
		// column with a nested column list and an optional caption). 
		// Additionally we will make sure, that all columns are within column groups, so we can jus instatiate
		// the groups, not each column separately. The actual instantiation of the corresponding widgets will 
		// follow in the next step.
		foreach ($columns as $c) {
			if (is_array($c)){
				// If the element is an array itself (nested in columns), it is a column group
				$column_groups[] = $c;
				$last_element_was_a_column_group = true;
			} elseif (is_object($c)) {
				// If not, check to see if it's widget type is DataColumnGroup or it has an array of columns itself
				// If so, it still is a column group
				if ($c->widget_type == 'DataColumnGroup' || is_array($c->columns)){
					$column_groups[] = $c;
					$last_element_was_a_column_group = true;
				} else {
					// If none of the above applies, it is a regular column, so we need to put it into a column group
					// We start a new group, if the last element added was a columnt group or append it to the last
					// group if that was built from single columns already
					if (!count($column_groups) || $last_element_was_a_column_group){
						$column_groups[] = new \stdClass();
					}
					$column_groups[(count($column_groups)-1)]->columns[] = $c;
					$last_element_was_a_column_group = false;
				}
			} else {
				throw new WidgetPropertyInvalidValueError($this, 'The elements of "columns" in a data widget must be objects or arrays, "' . gettype($c) . '" given instead!', '6T91RQ5');
			}
		}
		
		// Now that we have put all column into groups, we can instatiate these as widgets.
		foreach ($column_groups as $nr => $group){
			// The first column group is always treated as the main one. So check to see, if there is a main
			// column group already and, if so, simply make it load the uxon description of the first column
			// group.
			if ($nr == 0 && count($this->get_column_groups()) > 0){
				$this->get_column_group_main()->import_uxon_object($group);
			} else {
				// Set the widget type explicitly if it was not defined by the user
				if (!$group->widget_type){
					$group->widget_type = 'DataColumnGroup';
				}
				$page = $this->get_page();
				$column_group = WidgetFactory::create_from_uxon($page, UxonObject::from_anything($group), $this);
				$this->add_column_group($column_group);
			}
		}
		return $this;
	}

	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::get_buttons()
	 * @return DataButton
	 */
	public function get_buttons() {
		return $this->buttons;
	}
	
	/**
	 * Returns an array of button widgets, that are explicitly bound to a double click on a data element
	 * @param string $mouse_action
	 * @return DataButton[]
	 */
	public function get_buttons_bound_to_mouse_action($mouse_action){
		$result = array();
		foreach ($this->get_buttons() as $btn){
			if ($btn->get_bind_to_mouse_action() == $mouse_action){
				$result[] = $btn;
			}
		}
		return $result;
	}
	
	/**
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::set_buttons()
	 */
	public function set_buttons(array $buttons_array) {
		if (!is_array($buttons_array)) return false;
		foreach ($buttons_array as $b){
			$button = $this->get_page()->create_widget('DataButton', $this, UxonObject::from_anything($b));
			$this->add_button($button);
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::add_button()
	 */
	public function add_button(Button $button_widget){
		$button_widget->set_parent($this);
		$button_widget->set_meta_object_id($this->get_meta_object()->get_id());
		$this->buttons[] = $button_widget;
	}

	/**
	 * Returns an array with all filter widgets.
	 * @return Filter[]
	 */
	public function get_filters() {
		if (count($this->filters) == 0){
			$this->add_required_filters();
		}
		return $this->filters;
	}
	
	/**
	 * Returns the filter widget matching the given widget id
	 * @param string $filter_widget_id
	 * @return \exface\Core\Widgets\Filter
	 */
	public function get_filter($filter_widget_id) {
		foreach ($this->get_filters() as $fltr){
			if ($fltr->get_id() == $filter_widget_id){
				return $fltr;
			}
		}
	}
	
	/**
	 * Returns all filters, that have values and thus will be applied to the result
	 * @return \exface\Core\Widgets\AbstractWidget[] array of widgets
	 */
	public function get_filters_applied(){
		$result = array();
		foreach ($this->filters as $id => $fltr){
			if (!is_null($fltr->get_value())){
				$result[$id] = $fltr;
			}
		}
		return $result;
	}

	/**
	 * Creates widgets for filters. The filter object is treated as a new input widget for the attribute,
	 * specified in the "attribute_alias" property. Hence all the normal widget options can be applied to
	 * filters too. If the widget type for the filter is not explicitly defined, it is determined from the
	 * data type of the attribute, we use to filter.
	 * 
	 * @param array $filters_array
	 * @return boolean
	 */
	public function set_filters(array $filters_array) {
		if (!is_array($filters_array)) return false;
		foreach ($filters_array as $f){
			$include_in_quick_search = false;
			// Add to quick search if required
			if ($f->include_in_quick_search === true){
				$include_in_quick_search = true;
			}
			unset($f->include_in_quick_search);
			
			$filter = $this->create_filter_widget($f->attribute_alias, $f);
			$this->add_filter($filter, $include_in_quick_search);
		}
		$this->add_required_filters();
		return true;
	}
	
	public function create_filter_widget($attribute_alias, \stdClass $uxon_object = null){
		// a filter can only be applied, if the attribute alias is specified and the attribute exists
		if (!$attribute_alias) throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for an empty attribute alias in widget "' . $this->get_id() . '"!', '6T91AR9');
		if (!$attr = $this->get_meta_object()->get_attribute($attribute_alias)) throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for attribute alias "' . $attribute_alias . '" in widget "' . $this->get_id() . '": attribute not found for object "' . $this->get_meta_object()->get_alias_with_namespace() . '"!', '6T91AR9');
			
		// determine the widget for the filte
		$uxon = $attr->get_default_widget_uxon()->copy();
		if ($uxon_object){
			$uxon = $uxon->extend(UxonObject::from_stdClass($uxon_object));
		}
		// Set a special caption for filters on relations, which is derived from the relation itself
		// IDEA this might be obsolete since it probably allways returns the attribute name anyway, but I'm not sure
		if ($attr->is_relation()){
			$uxon->set_property('caption', $this->get_meta_object()->get_relation($attribute_alias)->get_name());
		}
		$page = $this->get_page();
		if ($uxon->comparator){
			$comparator = $uxon->comparator;
			unset($uxon->comparator);
		}
		$filter_input = WidgetFactory::create_from_uxon($page, $uxon, $this);
		
		$filter = $this->get_page()->create_widget('Filter', $this);
		$filter->set_widget($filter_input);
		$filter->set_comparator($comparator);
		
		return $filter;
	}
	
	/**
	 * @see \exface\Core\Widgets\AbstractWidget::prefill()
	 */
	public function prefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet){
		// First, take care of basic stuff like set_prefill_data()
		parent::prefill($data_sheet);
		// Now do the widget specific prefill
		if ($data_sheet->get_meta_object()->is_exactly($this->get_meta_object())){
			// If the prefill data is based on the same object as the widget, inherit the filter conditions from the prefill
			foreach ($data_sheet->get_filters()->get_conditions() as $condition){
				// For each filter condition look for filters over the same attribute
				$attribute_filters = $this->find_filters_by_attribute($condition->get_expression()->get_attribute());
				// If no filters are there, create one
				if (count($attribute_filters) == 0){
					$filter = $this->create_filter_widget($condition->get_expression()->get_attribute()->get_alias_with_relation_path());
					$this->add_filter($filter);	
					$filter->set_value($condition->get_value());
				} else {
				// If matching filters were found, prefill them
					$prefilled = false;
					foreach ($attribute_filters as $filter){
						if ($filter->get_comparator() == $condition->get_comparator()){
							$filter->set_value($condition->get_value());
							$prefilled = true;
						} 
					}
					if ($prefilled == false){
						$attribute_filters[0]->set_value($condition->get_value());
					}
				}
			}		
		} else {
			// if the prefill contains data for another object, than this data set contains, see if we try to find a relation to
			// the prefill-object. If so, show only data related to the prefill (= add the prefill object as a filter)
			
			// First look if the user already specified a filter with the object we are looking for
			foreach ($this->find_filters_by_object($data_sheet->get_meta_object()) as $fltr){
				$fltr->prefill($data_sheet);
			}
			
			// Otherwise, try to find a suitable relation via generic relation searcher
			// TODO currently this only works for direct relations, not for chained ones.
			if (!$fltr && $rel = $this->get_meta_object()->find_relation($data_sheet->get_meta_object())){
				$filter_widget = $this->create_filter_from_relation($rel);
				$filter_widget->prefill($data_sheet);
			}
		}
	}
	
	/**
	 * Creates and adds a filter based on the given relation
	 * @param relation $relation
	 * @return \exface\Core\Widgets\AbstractWidget
	 */
	protected function create_filter_from_relation(Relation $relation){
		$filter_widget = $this->find_filter_by_relation($relation);
		// Create a new hidden filter if there is no such filter already
		if (!$filter_widget){
			$page = $this->get_page();
			$filter_widget = WidgetFactory::create_from_uxon($page, $relation->get_main_object_key_attribute()->get_default_widget_uxon(), $this);
			$filter_widget->set_attribute_alias($relation->get_foreign_key_alias());
			$this->add_filter($filter_widget);
		}
		return $filter_widget;
	}
	
	/**
	 * Returns an array of filters, that filter over the given attribute. It will mostly contain only one filter, but if there
	 * are different filters with different comparators (like from+to for numeric or data values), there will be multiple filters
	 * in the list.
	 * @param Attribute $attribute
	 * @return Filter[]
	 */
	protected function find_filters_by_attribute(Attribute $attribute){
		$result = array();
		foreach ($this->get_filters() as $filter_widget){
			if ($filter_widget->get_attribute_alias() == $attribute->get_alias_with_relation_path()){
				$result[] = $filter_widget;
			}
		}
		return $result;
	}
	
	/**
	 * TODO Make the method return an array like find_filters_by_attribute() does
	 * @param Relation $relation
	 * @return Filter
	 */
	protected function find_filter_by_relation(Relation $relation){
		foreach ($this->get_filters() as $filter_widget){
			if ($filter_widget->get_attribute_alias() == $relation->get_alias()){
				$found = $filter_widget;
				break;
			} else {
				$found = null;
			}
		}
		if ($found){
			return $found;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns the first filter based on the given object or it's attributes
	 * TODO Make the method return an array like find_filters_by_attribute() does
	 * @param Object $object
	 * @return \exface\Core\Widgets\Filter|boolean
	 */
	protected function find_filters_by_object(Object $object){
		$result = array();
		foreach ($this->get_filters() as $filter_widget){
			$filter_object = $this->get_meta_object()->get_attribute($filter_widget->get_attribute_alias())->get_object();
			$filter_relation = $filter_widget->get_attribute()->get_relation();
			if ($object->is($filter_object)){
				$result[] = $filter_widget;
			} elseif ($filter_relation && $object->is($filter_relation->get_related_object())){
				$result[] = $filter_widget;
			}
		}
		return $result;
	}
	
	/**
	 * Adds a widget as a filter. Any widget, that can be used to input a value, can be used for filtering. It will automatically be wrapped in a filter
	 * widget. The second parameter (if set to TRUE) will make the filter automatically get used in quick search queries.
	 * @param AbstractWidget $filter_widget
	 * @param boolean $include_in_quick_search 
	 * @see \exface\Core\Interfaces\Widgets\iHaveFilters::add_filter()
	 */
	public function add_filter(\exface\Core\Widgets\AbstractWidget $filter_widget, $include_in_quick_search = false){
		if ($filter_widget instanceof \exface\Core\Widgets\Filter){
			$filter = $filter_widget;
		} else {
			$filter = $this->get_page()->create_widget('Filter', $this);
			$filter->set_widget($filter_widget);
		}
		$this->filters[] = $filter;
		if ($include_in_quick_search){
			$this->add_quick_search_filter($filter);
		}
		return $this;
	}
	
	protected function add_required_filters(){
		// Check for required filters
		foreach ($this->get_meta_object()->get_data_address_required_placeholders() as $ph){
			// Special placeholders referencing properties of the meta object itself
			// TODO find a better notation for special placeholders to separate them clearly from other attributes
			if ($ph == 'alias' || $ph == 'id') continue;
		
			// If the placeholder is an attribute, add a required filter on it (or make an existing filter required)
			if ($ph_attr = $this->get_meta_object()->get_attribute($ph)){
				if (count($this->filters)){
					$ph_filters = $this->find_filters_by_attribute($ph_attr);
					foreach ($ph_filters as $ph_filter){
						$ph_filter->set_required(true);
					}
				} else {
					$ph_filter = $this->create_filter_widget($ph);
					$ph_filter->set_required(true);
					$this->add_filter($ph_filter);
				}
			}
		}
		return $this;
	}
	
	function has_filters(){
		if (count($this->filters) == 0){
			$this->add_required_filters();
		}
		if (count($this->filters)) return true;
		else return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	function has_buttons() {
		if (count($this->buttons)) return true;
		else return false;
	}
	
	public function get_children(){
		$children = array_merge($this->get_filters(), $this->get_buttons(), $this->get_columns());
		return $children;
	}
	
	public function get_paginate() {
		return $this->paginate;
	}
	
	public function set_paginate($value) {
		$this->paginate = $value;
	}
	
	public function get_paginate_default_page_size() {
		return $this->paginate_default_page_size;
	}
	
	public function set_paginate_default_page_size($value) {
		$this->paginate_default_page_size = $value;
	}
	
	public function get_paginate_page_sizes() {
		return $this->paginate_page_sizes;
	}
	
	public function set_paginate_page_sizes(array $value) {
		$this->paginate_page_sizes = $value;
	}
	
	/**
	 * Returns an all data sorters applied to this sheet as an array.
	 * @return \stdClass[]
	 */
	public function get_sorters() {
		return $this->sorters;
	}
	
	public function set_sorters(array $sorters) {
		$this->sorters = $sorters;
	}	  
	
	public function get_aggregate_by_attribute_alias() {
		return $this->aggregate_by_attribute_alias;
	}
	
	public function set_aggregate_by_attribute_alias($value) {
		$this->aggregate_by_attribute_alias = str_replace(', ', ',', $value);
	} 
	
	/**
	 * Returns aliases of attributes used to aggregate data
	 * @return array
	 */
	public function get_aggregations(){
		if ($this->get_aggregate_by_attribute_alias()){
			return explode(',', $this->get_aggregate_by_attribute_alias());
		} else {
			return array();
		}
	}
	
	/**
	 * Returns an array of aliases of attributes, that should be used for quick search relative to the meta object of the widget
	 * @return array
	 */
	public function get_attributes_for_quick_search(){
		$aliases = array();
		foreach ($this->get_quick_search_filters() as $fltr){
			$aliases[] = $fltr->get_attribute_alias();
		}
		return $aliases;
	}
	
	public function get_quick_search_filters() {
		return $this->quick_search_filters;
	}
	
	/**
	 * Replaces the current set of filters used for quick search queries by the given filter array
	 * @param Filter[] $filters
	 */
	public function set_quick_search_filters(array $filters) {
		$this->quick_search_filters = $filters;
		return $this;
	}
	
	/**
	 * Registers a filter for the quick search queries. The filter is passed by reference because it is also contained in the
	 * retular filters.
	 * @param Filter $widget
	 */
	public function add_quick_search_filter(Filter &$widget){
		$this->quick_search_filters[] = $widget;
	}
	  
	
	/**
	 * Returns an array of editor widgets. One for every editable data column.
	 * @return AbstractWidget[]
	 */
	public function get_editors(){
		$editors = array();
		foreach ($this->get_columns() as $col){
			if ($col->is_editable()){
				$editors[] = $col->get_editor();
			}
		}
		return $editors;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading()
	 */
	public function get_lazy_loading() {
		return $this->lazy_loading;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading()
	 */
	public function set_lazy_loading($value) {
		$this->lazy_loading = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::get_lazy_loading_action()
	 */
	public function get_lazy_loading_action() {
		return $this->lazy_loading_action;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::set_lazy_loading_action()
	 */
	public function set_lazy_loading_action($value) {
		$this->lazy_loading_action = $value;
		return $this;
	}
	
	/**
	 * Returns TRUE if the table has a footer with total values and FALSE otherwise
	 * @return boolean
	 */
	public function has_footer(){
		$result = false;
		foreach ($this->get_columns() as $col){
			if ($col->has_footer()) {
				$result = true;
				break;
			}
		}
		return $result;
	}
	
	public function get_text_empty() {
		if (!$this->text_empty){
			$this->text_empty = 'No data could be found for the current set of filters!';
		}
		return $this->text_empty;
	}
	
	public function set_text_empty($value) {
		$this->text_empty = $value;
		return $this;
	}
	
	/**
	 * @return DataColumnGroup
	 */
	public function get_column_groups(){
		return $this->column_groups;
	}
	
	public function get_column_group_main(){
		return $this->get_column_groups()[0];
	}
	
	public function add_column_group(DataColumnGroup $column_group){
		$this->column_groups[] = $column_group;
		return $this;
	}
	
	/**
	 * Adds columns with system attributes of the main object or any related object. This is very usefull for editable tables as
	 * system attributes are needed to save the data.
	 * @param unknown $relation_path
	 */
	public function add_columns_for_system_attributes($relation_path = null){
		$object = $relation_path ? $this->get_meta_object()->get_related_object($relation_path) : $this->get_meta_object();
		foreach ($object->get_attributes()->get_system()->get_all() as $attr){
			$system_alias = RelationPath::relation_path_add($relation_path, $attr->get_alias());
			// Add the system attribute only if it is not there already.
			// Counting the columns first allows to add the system column without searching for it. If we would search over
			// empty data widgets, we would automatically trigger the creation of default columns, which is absolute nonsense
			// at this point - especially since add_columns_for_system_attributes() can get called before all column defintions
			// in UXON are processed.
			if ($this->count_columns() == 0 || !$this->get_column_by_attribute_alias($system_alias)){
				$col = $this->create_column_from_attribute($this->get_meta_object()->get_attribute($system_alias), null, true);
				$this->add_column($col);
			}
		}
	}
	
	/**
	 * Returns true, if the data table contains at least one editable column
	 * @return boolean
	 */
	public function is_editable(){
		return $this->is_editable;
	}
	
	public function set_editable(){
		$this->editable = true;
		return $this;
	}
	
	/**
	 * 
	 * @return \exface\Core\Interfaces\Widgets\WidgetLinkInterface
	 */
	public function get_refresh_with_widget() {
		return $this->refresh_with_widget;
	}
	
	/**
	 * 
	 * @param WidgetLinkInterface|UxonObject|string $value
	 * @return \exface\Core\Widgets\Data
	 */
	public function set_refresh_with_widget($widget_link_or_uxon_or_string) {
		$exface = $this->get_workbench();
		if ($link = WidgetLinkFactory::create_from_anything($exface, $widget_link_or_uxon_or_string)){
			$this->refresh_with_widget = $link;
		}
		return $this;
	}	
}

?>