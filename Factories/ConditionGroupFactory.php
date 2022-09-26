<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Parts\ConditionalPropertyConditionGroup;

/**
 * Instantiates condition groups
 * 
 * @author andrej.kabachnik
 *
 */
abstract class ConditionGroupFactory extends AbstractUxonFactory
{

    /**
     * Returns an empty condition group
     *
     * @param Workbench $exface            
     * @param string $group_operator       
     * @param MetaObjectInterface $baseObject
     *      
     * @return ConditionGroup
     */
    public static function createEmpty(Workbench $exface, $group_operator = null, MetaObjectInterface $baseObject = null, bool $ignoreEmptyValues = false) : ConditionGroupInterface
    {
        return new ConditionGroup($exface, $group_operator ?? EXF_LOGICAL_AND, $baseObject, $ignoreEmptyValues);
    }
    
    /**
     * Creates a business object from it's UXON description.
     * If the business object implements iCanBeConvertedToUxon, this method
     * will work automatically. Otherwise it needs to be overridden in the specific factory.
     *
     * @param Workbench $exface
     * @param UxonObject $uxon
     * @param MetaObjectInterface $baseObject
     * 
     * @return ConditionGroupInterface
     */
    public static function createFromUxon(Workbench $exface, UxonObject $uxon, MetaObjectInterface $baseObject = null)
    {
        $result = static::createEmpty($exface, null, $baseObject);
        $result->importUxonObject($uxon);
        return $result;
    }
    
    /**
     * 
     * @param DataSheetInterface $sheet
     * @param string $operator
     * @return ConditionGroupInterface
     */
    public static function createForDataSheet(DataSheetInterface $sheet, string $operator, bool $ignoreEmptyValues = false) : ConditionGroupInterface
    {
        return static::createEmpty($sheet->getWorkbench(), $operator, $sheet->getMetaObject(), $ignoreEmptyValues);
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $operator
     * @return ConditionGroupInterface
     */
    public static function createForObject(MetaObjectInterface $object, string $operator, bool $ignoreEmptyValues = false) : ConditionGroupInterface
    {
        return static::createEmpty($object->getWorkbench(), $operator, $object, $ignoreEmptyValues);
    }
    
    /**
     * 
     * @param string $string
     * @param MetaObjectInterface $object
     * @throws RuntimeException
     * @return ConditionGroupInterface
     */
    public static function createFromString(string $string, MetaObjectInterface $object, string $operator = EXF_LOGICAL_AND, bool $ignoreEmptyValues = false) : ConditionGroupInterface
    {
        $string = trim($string);
        if (substr($string, 0, 1) === '{' && substr($string, -1) === '}') {
            return static::createFromUxon($object->getWorkbench(), UxonObject::fromJson($string));
        }
        /* TODO write a parser
        $grp = null;
        
        $remain = $string;
        $tokens = [EXF_LOGICAL_AND, EXF_LOGICAL_OR, EXF_LOGICAL_XOR, '('];
        $nextPos = false;
        $nextToken = null;
        
        do {
            foreach ($tokens as $t) {
                $pos = stripos($remain, $t);
                if ($pos !== false && ($nextPos === false || $nextPos > $pos)) {
                    $nextPos = $pos;
                    $nextToken = $t;
                }
            }
            
            switch ($nextToken) {
                case null:
                    if ($grp === null) {
                        $grp = static::createForObject($object, $operator);
                    }
                    $remain = '';
                    break;
            }
        } while ($remain !== '');
        */
        
        throw new RuntimeException('Cannot parse conditional expression "' . $string . '": parsing non-UXON conditions not implemented yet!');
    }
    
    /**
     * 
     * @param ConditionalPropertyConditionGroup $prop
     * @param MetaObjectInterface $baseObject
     * @param string $dataRef
     * @throws RuntimeException
     * @return ConditionGroupInterface
     */
    public static function createFromConditionalProperty(ConditionalPropertyConditionGroup $prop, MetaObjectInterface $baseObject, string $dataRef = '=~input!') : ConditionGroupInterface
    {
        $uxon = new UxonObject([
            'base_object_alias' => $baseObject->getAliasWithNamespace()
        ]);
        foreach ($prop->getConditions() as $cond) {
            /* @var $left \exface\Core\Interfaces\Model\ExpressionInterface */
            $left = $cond->getValueLeftExpression();
            if ($left->isReference()) {
                if (! StringDataType::startsWith($left->__toString(), $dataRef)) {
                    throw new RuntimeException('Cannot convert conditional property value "' . $left->__toString() . '" to a model condition: only references to "' . $dataRef . '" allowed!');
                }
                $exprStr = StringDataType::substringAfter($left->__toString(), $dataRef);
            } else {
                $exprStr = $left->__toString();
            }
            
            /* @var $right \exface\Core\Interfaces\Model\ExpressionInterface */
            $right = $cond->getValueRightExpression();
            if ($right->isReference()) {
                throw new RuntimeException('Cannot convert conditional property value "' . $right->__toString() . '" to a model condition: the right value of each condition must not be a reference!');
            } else {
                $valueStr = $right->__toString();
            }
            $uxon->appendToProperty('conditions', new UxonObject([
                'expression' => $exprStr,
                'comparator' => $cond->getComparator(),
                'value' => $valueStr
            ]));
        }
        foreach ($prop->getConditionGroups() as $condGrp) {
            $uxon->appendToProperty('nested_groups', static::createFromConditionalProperty($condGrp, $baseObject, $dataRef));
        }
        return static::createFromUxon($prop->getWorkbench(), $uxon, $baseObject);
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return ConditionGroupInterface
     */
    public static function createAND(MetaObjectInterface $object, bool $ignoreEmptyValues = false) : ConditionGroupInterface
    {
        return static::createEmpty($object->getWorkbench(), EXF_LOGICAL_AND, $object, $ignoreEmptyValues);
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return ConditionGroupInterface
     */
    public static function createOR(MetaObjectInterface $object, bool $ignoreEmptyValues = false) : ConditionGroupInterface
    {
        return static::createEmpty($object->getWorkbench(), EXF_LOGICAL_OR, $object, $ignoreEmptyValues);
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return ConditionGroupInterface
     */
    public static function createXOR(MetaObjectInterface $object, bool $ignoreEmptyValues = false) : ConditionGroupInterface
    {
        return static::createEmpty($object->getWorkbench(), EXF_LOGICAL_XOR, $object, $ignoreEmptyValues);
    }
}