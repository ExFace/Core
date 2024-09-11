<?php
namespace exface\Core\CommonLogic\AI;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
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
        $dbml .= 'Table ' . $table['Table'] . ' {' . PHP_EOL;
        
        foreach ($table['columns'] as $attrProps) {
            $dbml .= $indent . $attrProps['Data Address'] . ' ' . $attrProps['Data Type'] . ' [';
            if ($attrProps['Primary Key']) {
                $dbml .= 'primary key, ';
            }
            $dbml .= "note: 'Attribute Name: " . $attrProps['Object Name'] . ", Attribute Alias: " . $attrProps['Object Alias'];
            if (!empty($attrProps['Description'])) {
                $dbml .= ', Description: ' . $attrProps['Description'];
            }
            $dbml .= "']" . PHP_EOL;
        }
        $dbml .= '}' . PHP_EOL; 
    }
    foreach ($array['Enums'] as $alias => $values) 
    {
        foreach ($values['values'] as $key => $value) {
            
            $enumValues[] = "$key [note:  '$value']";
        }
        $dbml .= 'Enum '. $alias . '{' . PHP_EOL .  implode(PHP_EOL , $enumValues) .'}' . PHP_EOL ;
        $dbml .= '//Description: '. $values['description']. PHP_EOL . PHP_EOL;
    }
    $dbml .= PHP_EOL;
    foreach ($array['Relations'] as $alias => $table) {
        

        foreach ($table['details'] as $details) {
            $addRelation = 'Ref: ' . $this->buildRelation($details) . PHP_EOL;
            if(!strpos($dbml, $addRelation)){
                $dbml .='Ref: ' . $this->buildRelation($details) . PHP_EOL;
            }
        }
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
     *   "Enums":[],
     *   "Relations": [
     *      
     * 
     *  ]
     * ]
     * ```
     * 
     * @return array
     */

    public function toArray() : array{
    $array = [];
    foreach ($this->getObjects() as $obj) {
        if ($this->shouldIncludeTable($obj)) {
            $array = array_merge_recursive($array, $this->getDBMLArrayForObject($obj));
        }
    }
    return $array;
    }

    protected function getDBMLArrayForObject(MetaObjectInterface $obj) : array
    {
        $enums = [];
        $table = [
            'Table' => $obj->getDataAddress(),
            'columns' => []
        ];
        $relation_table = [
            'Table' => $obj->getDataAddress(),
            'details' => []
        ];
        foreach ($obj->getAttributes() as $attribute) { 
            
            $primary_key = false;
            $data_address = StringDataType:: stripLineBreaks($attribute->getDataAddress());


            if ($data_address === 'oid') {
                $primary_key = true;
            }


            
            
            $table['columns'][] = [
                'Primary Key' => $primary_key,
                'Data Address' => $data_address,
                'Object Name' => $attribute->getName(),
                'Object Alias' => $attribute->getAlias(),
                'Data Type' => $this->getDmblDatatype($attribute->getDataType()),
                'Description' => $attribute->getShortDescription(),
                
                
                
            ];
            $datatype = $attribute->getDataType();
            if ($datatype instanceof EnumDataTypeInterface) {
                
                foreach($datatype->getLabels() as $value => $name){
                    $enums [$attribute->getDataType()->getAliasWithNamespace()][]= 
                    [
                        
                    'value' => $value,
                    'name' => $name,
                    
                    ];
                
                
                }
                
                
                
                $enums [$attribute->getDataType()->getAliasWithNamespace()] = ['values' => $attribute-> getDataType()->getlabels(),'description' => $datatype->getShortDescription(),];
                //$enums = [$datatype->getLabels()];
            }

           if ($attribute->isRelation()) {
            $relation_table["details"] [] = [
                
                "Column" => $attribute->getDataAddress(),
                "Right Table" => $attribute->getRelation()->getRightObject()->getAliasWithNamespace(),
                "Left Table" => $attribute->getRelation()->getLeftObject()->getAliasWithNamespace(),
                "Relation Type" => $attribute->getRelation()->getType()->__toString(),
                "Right Element" => $attribute->getRelation()->getRightKeyAttribute()->getDataAddress(),
                "Left Element" => $attribute->getRelation()->getLeftKeyAttribute()->getDataAddress(),
            ];
            
        }

        }
        return [
            'Tables' => [$obj->getAliasWithNamespace() => $table],
            'Enums'=> $enums, 
            'Relations' => [$obj->getAliasWithNamespace() => $relation_table]
        ];
    }


    //Test class to reduce the output
    protected function shouldIncludeTable(MetaObjectInterface $obj) : bool
    {
    return strpos($obj->getDataAddress(), 'user') !== false;
    }
    protected function getDmblDatatype(DataTypeInterface $dataType) : string 
    {

        switch (true) {
            
            case $dataType instanceof IntegerDataType:
            case $dataType instanceof TimeDataType:
                $schema = 'integer';
                break;
            case $dataType instanceof NumberDataType:
                $schema = 'number';
                break;
            case $dataType instanceof BooleanDataType:
                $schema = 'boolean';
                break;
            case $dataType instanceof ArrayDataType:
                $schema = 'array';
                break;
            case $dataType instanceof EnumDataTypeInterface:
                $schema = 'enum';
                $dataType-> getValues();
                break;
            case $dataType instanceof DateTimeDataType:
                $schema = 'datetime';
                break;
            case $dataType instanceof DateDataType:
                $schema = 'date';
                break;
            case $dataType instanceof BinaryDataType:
                $schema ='string';
                break;
            case $dataType instanceof StringDataType:
                $schema = 'string';
                break;
            default:
                throw new InvalidArgumentException('Datatype: ' . $dataType->getAlias() . ' not recognized.');
        }
        return $schema;
        
    }

   protected function buildRelation(array $table) : String {
    $relation = "<";

    switch (true) {
        case $table['Relation Type'] === '11':
            $relation = '-';
            break;
        case $table['Relation Type'] === 'N1':
            $relation = '<';
            break;    
        case $table['Relation Type'] === 'NM':
            $relation = '<>';
            break;
        case $table['Relation Type'] === '1N':
            $relation = '>';
            break; 
    }
      return $table['Left Table'] . '.' . $table['Left Element'] . ' ' . $relation . ' ' . $table['Right Table'] . '.' . $table['Right Element'] ;
   }
}