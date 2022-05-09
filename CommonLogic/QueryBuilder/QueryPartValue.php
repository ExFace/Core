<?php
namespace exface\Core\CommonLogic\QueryBuilder;

/**
 * This query part represents one or multiple values to create or update in a data source
 * 
 * Note, that every value query part in a query may contain a different number of
 * values. This means, that attributes may have new values on different rows. This is important 
 * in case of updates as an update may contain rows, that simply don't have a value for a certain 
 * data column, which does not mean, it should be emptied in the data source!
 * 
 * In addition to the values, every value query part may contain a list of UIDs for each
 * row. This is important to make sure an update hits exactly the item in the data source
 * that it was meant for.
 * 
 * @author Andrej Kabachnik
 *
 */
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

    /**
     * Returns an array with row numbers for keys and values to update for values
     * 
     * @return array
     */
    public function getValues() : array
    {
        return $this->values;
    }

    /**
     * Returns an array with row numbers for keys and UIDs for values if each value is assigned to a UID
     * 
     * NOTE: in a query without UIDs this will return an empty array.
     * 
     * @return array
     */
    public function getUids() : array
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
        foreach ($this->getValues() as $val) {
            if ($val !== null && $val !== '') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Returns TRUE if the query part has non-empty UIDs (i.e. other than '' and null).
     * @return bool
     */
    public function hasUids() : bool
    {
        foreach ($this->getUids() as $val) {
            if ($val !== null && $val !== '') {
                return true;
            }
        }
        return false;
    }
}