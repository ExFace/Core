<?php namespace exface\Core\CommonLogic\Model;

use exface\Core\DataTypes\AbstractDataType;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeCopied;

class Attribute implements ExfaceClassInterface, iCanBeCopied {
	
	// Properties to be dublicated on copy()
	private $id;
	private $object_id;
	private $inherited_from_object_id = null;
	private $alias;
	private $name;
	private $data;
	private $data_address_properties;
	private $data_type;
	private $formatter;
	private $required = false;
	private $hidden = false;
	private $editable = false;
	private $system = false;
	private $default_display_order;
	private $is_relation;
	private $formula;
	private $default_value;
	private $fixed_value;
	private $default_sorter_dir = 'ASC';
	private $short_description;
	private $defaul_aggregate_function = null;
	private $sortable;
	private $filterable;
	private $aggregatable;
	/** @var UxonObject */
	private $default_widget_uxon;
	/** @var RelationPath */
	private $relation_path; 
	
	// Properties NOT to be dublicated on copy()
	/** @var Model */
	private $model;
	
	public function __construct(Model $model){
		$this->model = $model;
	}
	
	/**
	 * Marks this attribute as a relation
	 * @param boolean $value
	 */
	public function set_relation_flag($value){
		$this->is_relation = $value;
	}
	
	/**
	 * Returns TRUE if this attribute actually is a relation and FALSE otherwise. The relation itself can be obtained by calling get_relation().
	 * @see get_relation()
	 * @return boolean
	 */
	public function is_relation(){
		return $this->is_relation;
	}
	
	/**
	 * Returns the relation, this attribute represents if it is a relation attribute and NULL otherwise
	 * @return Relation
	 */
	public function get_relation(){
		return $this->get_object()->get_relation($this->get_alias());
	}
	
	public function get_alias_with_relation_path(){
		return RelationPath::relation_path_add($this->get_relation_path()->to_string(), $this->get_alias());
	}
	
	public function get_id() {
		return $this->id;
	}
	
	public function set_id($value) {
		$this->id = $value;
	}
	
	public function get_alias() {
		return $this->alias;
	}
	
	public function set_alias($value) {
		$this->alias = $value;
	}
	
	/**
	 * Returns the data type of the attribute as an instantiated data type object
	 * @return AbstractDataType
	 */
	public function get_data_type() {
		return $this->data_type;
	}
	
	public function set_data_type($object_or_name) {
		if ($object_or_name instanceof AbstractDataType){
			$this->data_type = $object_or_name;
		} else {
			$exface = $this->get_model()->get_workbench();
			$this->data_type = DataTypeFactory::create_from_alias($exface, $object_or_name);
		}
		return $this;
	}
	
	public function get_default_display_order() {
		return $this->default_display_order;
	}
	
	public function set_default_display_order($value) {
		$this->default_display_order = $value;
	}
	
	/**
	 * Returns TRUE if the attribute can be changed and FALSE if it is read only. Attributes of objects from read-only data sources are never editable!
	 * @return boolean
	 */
	public function is_editable() {	
		if ($this->get_object()->get_data_source()->is_read_only()){
			return false;
		}
		return $this->editable;
	}
	
	public function set_editable($value) {
		$this->editable = $value;
	}
	
	/**
	 * 
	 * @return unknown
	 */
	public function get_formatter() {
		return $this->formatter;
	}
	
	/**
	 * 
	 * @param unknown $value
	 */
	public function set_formatter($value) {
		$this->formatter = $value;
	}
	
	public function is_hidden() {
		return $this->hidden;
	}
	
	public function set_hidden($value) {
		$this->hidden = $value;
	}
	
	public function get_name() {
		return $this->name;
	}
	
	public function set_name($value) {
		$this->name = $value;
	}
	
	public function is_required() {
		return $this->required;
	}
	
	public function set_required($value) {
		$this->required = $value;
	}
	
	public function get_data_address() {
		return $this->data;
	}
	
	public function set_data_address($value) {
		$this->data = $value;
	}
	
	/**
	 * Returns the relation path for this attribute, no matter how deep
	 * the relation is.
	 * E.g. calling it for the attribute PRICE of POSITION__PRODUCT
	 * (POSITION__PRODUCT__PRICE) would result in POSITION__PRODUCT as
	 * path.
	 * Returns NULL if the attribute belongs to the object itself.
	 * 
	 * @return RelationPath
	 */
	public function get_relation_path() {
		if (is_null($this->relation_path)){
			$this->relation_path = RelationPathFactory::create_for_object($this->get_object());
		}
		return $this->relation_path;
	}
	
	protected function set_relation_path(RelationPath $path){
		$this->relation_path = $path;
	}
	
	/**
	 * Returns the meta object to which this attributes belongs to. If the attribute has a relation path, this
	 * will return the last object in that path. 
	 * 
	 * If the attribute is inherited, the inheriting object will be returned. To get the base object, the
	 * attribute was inherited from, use get_object_inherited_from().
	 * 
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function get_object(){
		return $this->get_model()->get_object($this->get_object_id());
	}
	
	/**
	 * Returns the object, this attribute was inherited from. If the attribute was not inherited, returns it's regular object (same as get_object()).
	 * 
	 * If the attribute was inherited multiple times, this method will go back exactly one step. For example, if we have a base object
	 * of a data source, that is extended by OBJECT1, which in turn, is extended by OBJECT2, calling get_object_extended_from() on an
	 * attribute of OBJECT2 will return OBJECT1, while doing so for OBJECT1 will return the base object.
	 * 
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function get_object_inherited_from(){
		if ($this->is_inherited()){
			return $this->get_model()->get_object_by_id($this->get_inherited_from_object_id());
		} else {
			return $this->get_object();
		}
	}
	
	/**
	 * Returns a UXON object for the default editor widget for this attribute. The default widget can be defined
	 * for a data type and extended by a further definition for a specific attribute. If none of the above is defined,
	 * a blank UXON object with merely the overall default widget type (specified in the config) will be returned.
	 * @return UxonObject
	 */
	public function get_default_widget_uxon() {
		if (!$this->default_widget_uxon->get_property('attribute_alias')){
			$this->default_widget_uxon->set_property(attribute_alias, $this->get_alias_with_relation_path());
		}
		return $this->default_widget_uxon;
	}
	
	public function set_default_widget_uxon(UxonObject $uxon_object) {
		$this->default_widget_uxon = $uxon_object;
	}

	public function get_formula() {
		return $this->formula;
	}
	
	public function set_formula($value) {
		if ($value){
			$this->formula = $this->get_model()->parse_expression($value, $this->get_object());
		}
	}
	
	/**
	 * Returns an expression for the default value of this attribute, which is to be used, when saving the attribute without an explicit value given in the data sheet.
	 * @see get_fixed_value() in contrast to the fixed value, the default value is always overridden by any value in the data sheet.
	 * @return \exface\Core\CommonLogic\Model\Expression
	 */
	public function get_default_value() {
		if ($this->default_value && !($this->default_value instanceof expression)){
			$this->default_value = $this->get_model()->parse_expression($this->default_value, $this->get_object());
		} 
		return $this->default_value;
	}
	
	public function set_default_value($value) {
		if ($value){
			$this->default_value = $value;
		}
	}	
	
	/**
	 * Returns an expression for value of this attribute, which is to be set or updated every time the attribute is saved to the data source.
	 * @return \exface\Core\CommonLogic\Model\Expression
	 */
	public function get_fixed_value() {
		if ($this->fixed_value && !($this->fixed_value instanceof expression)){
			$this->fixed_value = $this->get_model()->parse_expression($this->fixed_value, $this->get_object());
		} 
		return $this->fixed_value;
	}
	
	public function set_fixed_value($value) {
			$this->fixed_value = $value;
	}

	public function get_default_sorter_dir() {
		return $this->default_sorter_dir;
	}
	
	public function set_default_sorter_dir($value) {
		$this->default_sorter_dir = $value;
	}
	
	public function get_object_id() {
		return $this->object_id;
	}
	
	public function set_object_id($value) {
		$this->object_id = $value;
	}
	
	public function get_model() {
		return $this->model;
	}
	
	public function set_model(\exface\Core\CommonLogic\Model\Model $model) {
		$this->model = $model;
	}
	
	public function get_short_description() {
		return $this->short_description;
	}
	
	public function set_short_description($value) {
		$this->short_description = $value;
	}
	
	public function get_hint(){
		return '[' . $this->get_data_type()->get_name() . '] ' . $this->get_short_description();
	}	

	/**
	 * Returns the UID of the object, this attribute was inherited from or NULL if it is a direct attribute of it's object
	 * @return string
	 */
	public function get_inherited_from_object_id() {
		return $this->inherited_from_object_id;
	}
	
	/**
	 *
	 * @param string $value
	 */
	public function set_inherited_from_object_id($value) {
		$this->inherited_from_object_id = $value;
	}
	
	/**
	 * Returns TRUE if this Relation was inherited from a parent object
	 * @return boolean
	 */
	public function is_inherited(){
		return is_null($this->get_inherited_from_object_id()) ? true : false;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\UxonObject
	 */
	public function get_data_address_properties() {
		return $this->data_address_properties;
	}
	
	/**
	 * 
	 * @param UxonObject $value
	 * @return Attribute
	 */
	public function set_data_address_properties(UxonObject $value) {
		$this->data_address_properties = $value;
		return $this;
	} 

	/**
	 * Returns the value of a data source specifi object property specified by it's id
	 * @param string $id
	 */
	public function get_data_address_property($id){
		return $this->get_data_address_properties()->get_property($id);
	}
	
	/**
	 * 
	 * @param string $id
	 * @param mixed $value
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function set_data_address_property($id, $value){
		$this->get_data_address_properties()->set_property($id, $value);
		return $this;
	}
	
	/**
	 * Returns TRUE if the attribute is used as the label for it's object or FALSE otherwise
	 * @return boolean
	 */
	public function is_label(){
		if ($this->get_alias() == $this->get_object()->get_label_alias()){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns TRUE if this attribute is used as UID for it's object and FALSE otherwise
	 * @return boolean
	 */
	public function is_uid_for_object(){
		if ($this->get_object()->get_uid_alias() === $this->get_alias()){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns TRUE if this attribute is the same (same UID, same object), as the given attribute, and FALSE otherwise.
	 * 
	 * This method will also return TRUE if the attributes have differen relations paths.
	 * NOTE: comparing the UID is not enough, as inherited attributes will keep their UID.
	 * 
	 * @param Attribute $attribute
	 * @return boolean
	 */
	public function is_exactly(Attribute $attribute){
		if ($this->get_id() == $attribute->get_id() && $this->get_object()->is_exactly($attribute->get_object())){
			return true;
		}
		return false;
	}
	
	/**
	 * Returns TRUE if the given attribute is the same as this one or is inherited from it.
	 * 
	 * For example, if we have a BASE object for a data source holds the UID attribute, and OBJECT1 inherits from that base object,
	 * we will have the following behavior - even if there is a custom UID attribute for OBJECT1, that overrides the default:
	 * - BASE__UID->is(OBJECT1__UID) = FALSE
	 * - OBJECT__UID->is(BASE__UID) = TRUE
	 * - BASE__UID->is(BASE__UID) = TRUE
	 * - OBJECT1__UID->is(OBJECT1__UID) = TRUE
	 * 
	 * @param Attribute $attribute
	 * @return boolean
	 */
	public function is(Attribute $attribute){
		if (strcasecmp($this->get_alias(), $attribute->get_alias()) === 0 && $this->get_object()->is($attribute->get_object())){
			return true;
		}
		return false;
	}
	
	/**
	 * Clones the attribute keeping the model and object
	 * @return Attribute
	 */
	public function copy(){
		$copy = clone $this;
		
		// Explicitly copy properties, that are objects themselves
		$copy->set_relation_path($this->get_relation_path()->copy());
		$copy->set_default_widget_uxon($this->get_default_widget_uxon()->copy());
		return $copy;
	}
	
	/**
	 * Returns TRUE if this attribute is a system attribute. System attributes are required by the internal logic 
	 * (like the UID attribute) an will be loaded by default in all data sheets
	 * 
	 * @return boolean
	 */
	public function is_system() {
		return $this->system;
	}
	
	/**
	 * Marks the attribute as system (TRUE) or non-system (FALSE). 
	 * System attributes are required by the internal logic (like the UID attribute) an will be loaded by default 
	 * in all data sheets
	 * 
	 * @param boolean $value
	 * @return Attribute
	 */
	public function set_system($value) {
		$this->system = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	/**
	 * @return Workbench
	 */
	public function get_workbench(){
		return $this->get_model()->get_workbench();
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_default_aggregate_function() {
		return $this->default_aggregate_function;
	}
	
	/**
	 * 
	 * @param string $value
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function set_default_aggregate_function($value) {
		$this->default_aggregate_function = $value;
		return $this;
	}  
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_sortable() {
		return $this->sortable;
	}
	
	/**
	 * 
	 * @param boolean $value
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function set_sortable($value) {
		$this->sortable = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_filterable() {
		return $this->filterable;
	}
	
	/**
	 * 
	 * @param boolean $value
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function set_filterable($value) {
		$this->filterable = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_aggregatable() {
		return $this->aggregatable;
	}
	
	/**
	 * 
	 * @param boolean $value
	 * @return \exface\Core\CommonLogic\Model\Attribute
	 */
	public function set_aggregatable($value) {
		$this->aggregatable = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	}
	
	      
}
?>