<?php
namespace exface\Core\CommonLogic\QueryBuilder;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\RelationPath;
/**
 * The filter query part represents one filter within a query (in SQL it translates to a WHERE-statement). Filter query parts
 * implement the general filter interface and thus can be aggregated to filter groups with logical operators like AND, OR, etc.
 * @author Andrej Kabachnik
 *
 */
class QueryPartFilter extends QueryPartAttribute {
	private $compare_value = null;
	private $comparator = null;
	private $condition = NULL;
	private $apply_after_reading = false;
	
	function __construct($alias, AbstractQueryBuilder $query){
		parent::__construct($alias, $query);
		// If we filter over an attribute, which actually is a reverse relation, we need to explicitly tell the query, that
		// it is a relation and not a direct attribute. Concider the case of CUSTOMER<-CUSTOMER_CARD. If we filter CUSTOMERs over 
		// CUSTOMER_CARD, it would look as if the CUSTOMER_CARD is an attribute of CUSTOMER. We need to detect this and transform
		// the filter into CUSTOMER_CARD__UID, which would clearly be a relation.
		if ($this->get_attribute()->is_relation() && $this->get_query()->get_main_object()->get_relation($alias)->get_type() == '1n'){
			$attr = $this->get_query()->get_main_object()->get_attribute(RelationPath::relation_path_add($alias, $this->get_attribute()->get_object()->get_uid_alias()));
			$this->set_attribute($attr);
		}
	}
	
	/**
	 * 
	 * @return string|mixed|NULL
	 */
	public function get_compare_value() {
		if (!$this->compare_value) $this->compare_value = $this->get_condition()->get_value();
		return $this->compare_value;
	}
	
	/**
	 * 
	 * @param mixed $value
	 */
	public function set_compare_value($value) {
		$this->compare_value = trim($value);
	}
	
	/**
	 * Returns the comparator - one of the EXF_COMPARATOR_xxx constants
	 * 
	 * @return string
	 */
	public function get_comparator() {
		if (!$this->comparator) $this->comparator = $this->get_condition()->get_comparator();
		return $this->comparator;
	}
	
	/**
	 * Sets the comparator - one of the EXF_COMPARATOR_xxx constants
	 * 
	 * @param string $value
	 * @return QueryPartFilter
	 */
	public function set_comparator($value) {
		$this->comparator = $value;
		return $this;
	}
	
	/**
	 * 
	 * @return \exface\Core\CommonLogic\Model\Condition
	 */
	public function get_condition() {
		return $this->condition;
	}
	
	/**
	 * 
	 * @param Condition $condition
	 */
	public function set_condition(Condition $condition) {
		$this->condition = $condition;
		return $this;
	}  
	
	/**
	 * 
	 * @return boolean
	 */
	public function get_apply_after_reading() {
		return $this->apply_after_reading;
	}
	
	/**
	 * 
	 * @param boolean $value
	 * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartFilter
	 */
	public function set_apply_after_reading($value) {
		$this->apply_after_reading = \exface\Core\DataTypes\BooleanDataType::parse($value);
		return $this;
	} 
}
?>