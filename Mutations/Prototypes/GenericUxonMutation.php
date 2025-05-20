<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Mutations\AppliedMutation;
use JsonPath\JsonObject;

class GenericUxonMutation extends AbstractMutation
{
    const MUTATION_COMMENT = '// Mutation';

    private $append = [];
    private $change = [];
    private $replace = [];
    private $remove = [];

    public function apply($subject): AppliedMutationInterface
    {
        if (! $subject instanceof UxonObject) {
            throw new InvalidArgumentException('Cannot apply UXON mutation to ' . get_class($subject));
        }
        $stateBefore = $subject->toJson(true);

        $jsonObj = new JsonObject($subject->toArray());
        foreach ($this->append as $jsonPath => $objects) {
            foreach ($objects as $object) {
                $object = $this->addCommentWithMutationName($object);
                $jsonObj->add($jsonPath, $object);
            }
        }
        foreach ($this->change as $jsonPath => $value) {
            $jsonObj->set($jsonPath, $value);
        }
        foreach ($this->replace as $jsonPath => $object) {
            $object = $this->addCommentWithMutationName($object);
            $jsonObj->set($jsonPath, $object);
        }
        foreach ($this->remove as $jsonPath) {
            // TODO commenting out instead of removed would probably be smarter as it will not
            // change the length of array. We could also add a comment hint about this mutation
            $jsonObj->remove($jsonPath);
        }

        $subject->replace($jsonObj->getValue());

        $stateAfter = $subject->toJson(true);
        return new AppliedMutation($this, $subject, $stateBefore, $stateAfter);
    }

    public function supports($subject): bool
    {
        return $subject instanceof UxonObject;
    }

    /**
     * Change a property defined by the given path
     *
     * ## Examples
     *
     * Hide the first column in a data table:
     *
     * ```
     * {
     *     "change": {
     *          "$.columns.0.hidden": true
     *     }
     * }
     *
     * ```
     *
     * @uxon-property change
     * @uxon-type object
     * @uxon-template {"// JSONpath to change": ""}
     *
     * @param UxonObject $arrayOfSets
     * @return $this
     */
    protected function setChange(UxonObject $arrayOfSets) : GenericUxonMutation
    {
        $this->change = $arrayOfSets->toArray();
        return $this;
    }

    /**
     * Replace an object at the end of the given path with another one
     *
     * ## Examples
     *
     * Hide the first column in a data table:
     *
     * ```
     * {
     *     "replace": {
     *          "$.columns.0": {
     *              "attribute_alias": "ANOTHER_ATTR"
     *          }
     *     }
     * }
     *
     * ```
     *
     * @uxon-property replace
     * @uxon-type object
     * @uxon-template {"// JSONpath to replace": {"": ""}}
     *
     * @param UxonObject $arrayOfSets
     * @return $this
     */
    protected function setReplace(UxonObject $arrayOfSets) : GenericUxonMutation
    {
        $this->replace = $arrayOfSets->toArray();
        return $this;
    }

    /**
     * Append an object to the end of an arrays
     *
     * ## Examples
     *
     * Hide the first column in a data table:
     *
     * ```
     * {
     *     "append": {
     *          "$.columns": [
     *              {
     *                  "attribute_alias": "MY_ATTR"
     *              }
     *          ]
     *     }
     * }
     *
     * ```
     *
     * @uxon-property append
     * @uxon-type object
     * @uxon-template {"// JSONpath to array": [{"":""}]}
     *
     * @param UxonObject $arrayOfObjects
     * @return $this
     */
    protected function setAppend(UxonObject $arrayOfObjects) : GenericUxonMutation
    {
        $this->append = $arrayOfObjects->toArray();
        return $this;
    }

    /**
     * Replace certain paths with the given values
     *
     * ## Examples
     *
     * Remove the second column in a data table:
     *
     * ```
     * {
     *     "remove": [
     *          "$.columns.1"
     *     ]
     * }
     *
     * ```
     *
     * @uxon-property remove
     * @uxon-type object
     * @uxon-template ["// JSONpath to remove"]
     *
     * @param UxonObject $arrayOfRemoves
     * @return $this
     */
    protected function setRemove(UxonObject $arrayOfRemoves) : GenericUxonMutation
    {
        $this->remove = $arrayOfRemoves->toArray();
        return $this;
    }

    /**
     * @param array|null|string|number|bool $object
     * @return array|null|string|number|bool
     */
    protected function addCommentWithMutationName($object) : mixed
    {
        if (is_array($object) && ! ArrayDataType::isSequential($object) && ! array_key_exists(self::MUTATION_COMMENT, $object)) {
            $object = array_merge(
                [self::MUTATION_COMMENT => $this->getName()],
                $object
            );
        }
        return $object;
    }
}