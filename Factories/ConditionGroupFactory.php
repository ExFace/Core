<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;

abstract class ConditionGroupFactory extends AbstractUxonFactory
{

    /**
     * Returns an empty condition group
     *
     * @param Workbench $exface            
     * @param string $group_operator            
     * @return ConditionGroup
     */
    public static function createEmpty(Workbench $exface, $group_operator = null) : ConditionGroupInterface
    {
        if ($group_operator === null) {
            return new ConditionGroup($exface);
        } else {
            return new ConditionGroup($exface, $group_operator);
        }
    }

    /**
     * Creates a condition group from short notation arrays of the form
     * [ OPERATOR1, [ CONDITION1 ], [ CONDITION2 ], [ OPERATOR2, [ CONDITION3 ], [ CONDITION4] ], ...
     * ]
     *
     * @param Workbench $exface            
     * @param array $array_notation            
     * @return ConditionGroup
     */
    public static function createFromArray(Workbench $exface, array $array_notation) : ConditionGroupInterface
    {
        $group = self::create($exface);
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
     * @throws UnexpectedValueException
     * @return ConditionGroup
     */
    public static function createFromUxonOrArray(Workbench $exface, $uxon_or_array) : ConditionGroupInterface
    {
        if ($uxon_or_array instanceof UxonObject) {
            return self::createFromUxon($exface, $uxon_or_array);
        } elseif (is_array($uxon_or_array)) {
            return self::createFromArray($exface, $uxon_or_array);
        } else {
            throw new UnexpectedValueException('Cannot parse condition "' . print_r($uxon_or_array) . '"!');
        }
    }
}
?>