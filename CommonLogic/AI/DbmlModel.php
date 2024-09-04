<?php
namespace exface\Core\CommonLogic\AI;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class DbmlModel
{
    private $workbench = null;

    private $objectFilter = null;

    private $objectCache = null;

    public function __construct(WorkbenchInterface $workbench, callable $objectFilter = null)
    {
        $this->workbench = $workbench;
        $this->objectFilter = $objectFilter;
    }

    public function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }

    protected function getObjectAliases() : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.OBJECT');
        $aliasCol = $ds->getColumns()->addFromExpression('ALIAS_WITH_NS');
        $ds->dataRead();
        return $aliasCol->getValues();
    }

    protected function getObjects() : array
    {
        if (null === $this->objectCache) {
            $this->objectCache = [];
            $failedObjects = [];
                $filterCallback = $this->objectFilter;
            foreach ($this->getObjectAliases() as $alias) {
                try {
                    $obj = MetaObjectFactory::createFromString($this->getWorkbench(), $alias);
                    if ($filterCallback === null || $filterCallback($obj) === true) {
                        $this->objectCache[] = $obj;
                    }
                } catch (\Throwable $e) {
                    $failedObjects[] = $alias;
                }
                
            }
        }
        return $this->objectCache;
    }

    public function toDBML() : string
    {
        $indent = '  ';
        $dbml = '';
        $array = $this->toArray();
        foreach ($array['Tables'] as $alias => $table) {
            $dbml .= PHP_EOL . 'Table ' . $table['data_address'] . ' {';
            foreach ($table['columns'] as $attrProps) {
                $dbml .= PHP_EOL . $indent . implode(' ', $attrProps);
            }
            $dbml .= PHP_EOL . '}';
        }
        return $dbml;
    }

    /**
     * Renders DBML as an array
     * 
     * ```
     * [
     *   "Tables": [
     *     "exface.Core.PAGE" => [
     *       "data_address": "exf_page",
     *       "columns": [
     *          "name": "UID",
     *          "type": "string",
     *       ]
     *     ]
     *   ],
     *   "Enumerations":[],
     *   "References": []
     * ]
     * ```
     * 
     * @return array
     */
    public function toArray() : array
    {
        $array = [];
        foreach ($this->getObjects() as $obj) {
            $array = array_merge_recursive($array, $this->getDBMLArrayForObject($obj));
        }
        return $array;
    }

    protected function getDBMLArrayForObject(MetaObjectInterface $obj) : array
    {
        $table = [
            'data_address' => $obj->getDataAddress(),
            'columns' => []
        ];
        foreach ($obj->getAttributes() as $attribute) { 
            $table['columns'][] = [
                'name'=> $attribute->getDataAddress(),
                'description' => $attribute->getName()
            ];
        }
        return [
            'Tables' => [$obj->getAliasWithNamespace() => $table]
        ];
    }
}