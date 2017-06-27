<?php
namespace exface\Core\CommonLogic\QueryBuilder;

class RowDataArrayFilter
{

    private $filters = array();
    
    /**
     * 
     * 
     * @param string $key
     * @param string $value
     * @param string $comparator - one of the EXF_COMPARATOR_xxx constants
     * @param string $listDelimiter
     */
    public function addAnd($key, $value, $comparator, $listDelimiter = EXF_LIST_SEPARATOR)
    {
        $this->filters[] = array(
            'key' => $key,
            'value' => $value,
            'comparator' => $comparator,
            'listDelimiter' => $listDelimiter
        );
    }

    public function filter(array $array_of_rows)
    {
        foreach ($array_of_rows as $rownr => $row) {
            foreach ($this->filters as $filter) {
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
                        if (! $match) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_EQUALS:
                        if (strcasecmp($row[$filter['key']], $filter['value']) !== 0) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_EQUALS_NOT:
                        if (strcasecmp($row[$filter['key']], $filter['value']) === 0) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_IS:
                        if (stripos($row[$filter['key']], (string) $filter['value']) === false) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_IS_NOT:
                        if (stripos($row[$filter['key']], (string) $filter['value']) !== false) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_GREATER_THAN:
                        if ($row[$filter['key']] < $filter['value']) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS:
                        if ($row[$filter['key']] <= $filter['value']) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_LESS_THAN:
                        if ($row[$filter['key']] > $filter['value']) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS:
                        if ($row[$filter['key']] >= $filter['value']) {
                            unset($array_of_rows[$rownr]);
                        }
                        break;
                    default:
                        throw new \InvalidArgumentException('The filter comparator "' . $filter['comparator'] . '" is not supported!');
                }
            }
        }
        return $array_of_rows;
    }
}
?>