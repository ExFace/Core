<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

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
    public static function createEmpty(Workbench $exface, $group_operator = null, MetaObjectInterface $baseObject = null) : ConditionGroupInterface
    {
        return new ConditionGroup($exface, $group_operator ?? EXF_LOGICAL_AND, $baseObject);
    }

    /**
     * Creates a condition group from short notation arrays of the form
     * [ OPERATOR1, [ CONDITION1 ], [ CONDITION2 ], [ OPERATOR2, [ CONDITION3 ], [ CONDITION4] ], ...
     * ]
     *
     * @param Workbench $exface            
     * @param array $array_notation            
     * @param MetaObjectInterface $baseObject
     * 
     * @return ConditionGroup
     */
    public static function createFromArray(Workbench $exface, array $array_notation, MetaObjectInterface $baseObject = null) : ConditionGroupInterface
    {
        $group = self::createEmpty($exface, null, $baseObject);
        // Short notation
        foreach ($array_notation as $nr => $part) {
            if ($nr === 0) {
                $group->setOperator($part);
            } elseif (is_array($part)) {
                switch ($part[0]) {
                    case EXF_LOGICAL_AND:
                    case EXF_LOGICAL_NOT:
                    case EXF_LOGICAL_OR:
                    case EXF_LOGICAL_XOR:
                        $group->addNestedGroup(self::createFromUxonOrArray($exface, $part));
                        break;
                    default:
                        $group->addCondition(ConditionFactory::createFromUxonOrArray($exface, $part));
                }
            } else {
                throw new UnexpectedValueException('Cannot parse condition "' . print_r($part) . '" of condition group "' . print_r($array_notation) . '"!');
            }
        }
        return $group;
    }

    /**
     *
     * @param Workbench $exface            
     * @param UxonObject|array $uxon_or_array            
     * @param MetaObjectInterface $baseObject
     * 
     * @throws UnexpectedValueException
     * 
     * @return ConditionGroup
     */
    public static function createFromUxonOrArray(Workbench $exface, $uxon_or_array, MetaObjectInterface $baseObject = null) : ConditionGroupInterface
    {
        if ($uxon_or_array instanceof UxonObject) {
            return self::createFromUxon($exface, $uxon_or_array, $baseObject);
        } elseif (is_array($uxon_or_array)) {
            return self::createFromArray($exface, $uxon_or_array, $baseObject);
        } else {
            throw new UnexpectedValueException('Cannot parse condition "' . print_r($uxon_or_array) . '"!');
        }
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
    public static function createForDataSheet(DataSheetInterface $sheet, string $operator) : ConditionGroupInterface
    {
        return static::createEmpty($sheet->getWorkbench(), $operator, $sheet->getMetaObject());
    }
}
?>