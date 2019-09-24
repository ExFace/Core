<?php
namespace exface\Core\CommonLogic\QueryBuilder;

class RowDataArrayFilter
{
    const OPERATOR_AND = 'AND';
    const OPERATOR_OR = 'OR';
    const OPERATOR_XOR = 'XOR';

    private $filters = array();
    
    /**
     * 
     * 
     * @param string $key
     * @param string $value
     * @param string $comparator - one of the EXF_COMPARATOR_xxx constants
     * @param string $listDelimiter
     */
    public function addAnd($key, $value, $comparator, $listDelimiter = EXF_LIST_SEPARATOR) : RowDataArrayFilter
    {
        return $this->add($key, $value, $comparator, self::OPERATOR_AND, $listDelimiter);
    }
    
    public function addOr($key, $value, $comparator, $listDelimiter = EXF_LIST_SEPARATOR) : RowDataArrayFilter
    {
        return $this->add($key, $value, $comparator, self::OPERATOR_OR, $listDelimiter);
    }
    
    public function addXor($key, $value, $comparator, $listDelimiter = EXF_LIST_SEPARATOR) : RowDataArrayFilter
    {
        return $this->add($key, $value, $comparator, self::OPERATOR_XOR, $listDelimiter);
    }
    
    private function add($key, $value, $comparator, string $operator = self::OPERATOR_AND, $listDelimiter = EXF_LIST_SEPARATOR) : RowDataArrayFilter
    {
         return $this->addFilter([
            'operator' => $operator,
            'key' => $key,
            'value' => $value,
            'comparator' => $comparator,
            'listDelimiter' => $listDelimiter
        ]);
    }
    
    private function addFilter(array $filter) : RowDataArrayFilter
    {
        $this->filters[] = $filter;
        return $this;
    }

    /**
     * 
     * @param array $array_of_rows
     * @param bool $keepRowNumbers
     * @throws \InvalidArgumentException
     * @return array
     */
    public function filter(array $array_of_rows, bool $keepRowNumbers = true) : array
    {
        $result_rows = $array_of_rows;
        foreach ($this->filters as $pos => $filter) {
            if ($pos > 0 && $filter['operator'] !== self::OPERATOR_AND) {
                $subFilter = new self();
                $subFilter->addFilter($filter);
                for ($i = ($pos+1); $i < count($this->filters); $i++) {
                    $subFilter->addFilter($this->filters[$i]);
                }
                
                switch ($filter['operator']) {
                    case self::OPERATOR_OR:
                        $result_rows = array_replace($result_rows, $subFilter->filter($array_of_rows));  
                        break 2;
                    case self::OPERATOR_OR:
                        $or_rows = $subFilter->filter($array_of_rows);
                        $union_array = array_merge($result_rows, $or_rows);
                        $intersect_array = array_intersect($result_rows, $or_rows);
                        $result_rows = array_diff($union_array, $intersect_array);
                        break 2;
                    default:
                        break 2;
                }
            }
            
            foreach ($result_rows as $rownr => $row) {
                
                switch ($filter['comparator']) {
                    case EXF_COMPARATOR_IN:
                        $match = false;
                        $row_val = $row[$filter['key']];
                        foreach (explode($filter['listDelimiter'], $filter['value']) as $val) {
                            $val = trim($val);
                            if (strcasecmp($row_val, $val) === 0) {
                                $match = true;
                                break;
                            }
                        }
                        if ($match === false) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_EQUALS:
                        if (strcasecmp($row[$filter['key']], $filter['value']) !== 0) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_EQUALS_NOT:
                        if (strcasecmp($row[$filter['key']], $filter['value']) === 0) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_IS:
                        if (stripos($row[$filter['key']], (string) $filter['value']) === false) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_IS_NOT:
                        if (stripos($row[$filter['key']], (string) $filter['value']) !== false) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_GREATER_THAN:
                        if ($row[$filter['key']] < $filter['value']) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS:
                        if ($row[$filter['key']] <= $filter['value']) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_LESS_THAN:
                        if ($row[$filter['key']] > $filter['value']) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS:
                        if ($row[$filter['key']] >= $filter['value']) {
                            unset($result_rows[$rownr]);
                        }
                        break;
                    default:
                        throw new \InvalidArgumentException('The filter comparator "' . $filter['comparator'] . '" is not supported!');
                }
                
            }
        }
        return $result_rows;
    }
}
?>