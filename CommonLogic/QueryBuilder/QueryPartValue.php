<?php
namespace exface\Core\CommonLogic\QueryBuilder;

class QueryPartValue extends QueryPartAttribute
{

    private $values = array();

    private $uids = array();

    public function isValid()
    {
        if ($this->getAttribute()->getDataAddress() != '')
            return true;
        return false;
    }

    public function setValue($value)
    {
        $this->values[0] = $value;
    }

    public function setValues(array $values)
    {
        $this->values = $values;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function getUids()
    {
        return $this->uids;
    }

    public function setUids(array $uids_for_values)
    {
        $this->uids = $uids_for_values;
    }
    
    /**
     * Returns TRUE if the query part has non-empty values (i.e. other than '' and null).
     * @return bool
     */
    public function hasValues() : bool
    {
        $nonEmptyVals = array_filter($this->values,
            function($val){
                return $val !== '' && $val !== null;
            }
        );
        return empty($nonEmptyVals) === false;
    }
}
?>