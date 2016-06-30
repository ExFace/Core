<?php namespace exface\Core\Interfaces\DataSheets;

interface DataSheetSubsheetInterface extends DataSheetInterface {
	
	/**
	 * @return DataSheetInterface
	 */
	public function get_parent_sheet();
    
	/**
	 * 
	 * @param DataSheetInterface $value
	 */
    public function set_parent_sheet($value) {
    	$this->parent_sheet = $value;
    	return $this;
    }
    
    /**
     * @return string
     */
    public function get_join_parent_on_column_id() {
    	return $this->join_parent_on_column_id;
    }
    
    /**
     * 
     * @param string $value
     * @return DataSheetSubsheetInterface
     */
    public function set_join_parent_on_column_id($value) ;
}

?>