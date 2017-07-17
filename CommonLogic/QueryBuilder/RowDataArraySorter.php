<?php
namespace exface\Core\CommonLogic\QueryBuilder;

class RowDataArraySorter
{

    private $criterias = array();

    public function sort(array $array_of_rows)
    {
        if (! $this->coutCriterias())
            return $array_of_rows;
        
        $arguments = array();
        foreach ($this->getCriterias() as $citeria) {
            $arguments[] = $this->extractColumnData($array_of_rows, $citeria[0]);
            $arguments[] = $citeria[1];
        }
        $arguments[] = &$array_of_rows;
        call_user_func_array('array_multisort', $arguments);
        return $array_of_rows;
    }

    public function addCriteria($field, $direction)
    {
        if ($direction === SORT_DESC || strcasecmp($direction, 'DESC') === 0) {
            $direction = SORT_DESC;
        } else {
            $direction = SORT_ASC;
        }
        $this->criterias[] = array(
            $field,
            $direction
        );
    }

    protected function getCriterias()
    {
        return $this->criterias;
    }

    protected function extractColumnData($array_of_rows, $key)
    {
        return array_column($array_of_rows, $key);
    }

    public function coutCriterias()
    {
        return count($this->getCriterias());
    }
}
?>