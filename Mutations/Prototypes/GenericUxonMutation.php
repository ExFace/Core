<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\Utils\JsonObject;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Mutations\AppliedMutation;

/**
 * Allows to modify UXON configurations using JSONpath based operations
 *
 * You can define multiple operations like append, replace, etc. Each will require a JSONpath pointing
 * to the place in the UXON where the operation is to be applied and a new value (if required by the
 * operation).
 *
 * - `append` - adds one or more UXON objects at the end of the array located by the JSONpath
 * - `change` - changes a scalar property at the end of the JSONpath - e.g. to set a widget to `hidden:true`
 * - `replace` - replaces a UXON object with another one. The JSONpath should point to an object
 * - `remove` - removes all keys corresponding to the JSONpath entirely
 *
 * @author Andrej Kabachnik
 */
class GenericUxonMutation extends AbstractMutation
{
    const MUTATION_COMMENT = '// Mutation';

    private $append = [];
    private $insert = [];
    private $change = [];
    private $replace = [];
    private $remove = [];

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        if (! $subject instanceof UxonObject) {
            throw new InvalidArgumentException('Cannot apply UXON mutation to ' . get_class($subject));
        }
        $stateBefore = $subject->toJson(true);

        $jsonObj = new JsonObject($subject->toArray());
        // append
        foreach ($this->append as $jsonPath => $objects) {
            foreach ($objects as $object) {
                $object = $this->addCommentWithMutationName($object);
                $jsonObj->add($jsonPath, $object);
            }
        }
        foreach ($this->insert as $jsonPath => $objects) {
            $array = [];
            foreach ($objects as $object) {
                $object = $this->addCommentWithMutationName($object);
                $array[] = $object;
            }
            $jsonObj->insert($jsonPath, $array, true);
        }
        // change
        foreach ($this->change as $jsonPath => $value) {
            $jsonObj->set($jsonPath, $value);
        }
        // replace
        foreach ($this->replace as $jsonPath => $object) {
            $object = $this->addCommentWithMutationName($object);
            $jsonObj->set($jsonPath, $object);
        }
        // remove
        foreach ($this->remove as $jsonPath) {
            // TODO commenting out instead of removed would probably be smarter as it will not
            // change the length of array. We could also add a comment hint about this mutation
            $jsonObj->remove($jsonPath);
        }

        $subject->replace($jsonObj->getValue());

        $stateAfter = $subject->toJson(true);
        return new AppliedMutation($this, $subject, $stateBefore, $stateAfter);
    }

    /**
     * @see MutationInterface::supports()
     */
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
     * Append on or more object to the end of an array
     *
     * ## Examples
     *
     * Add some columns to a table
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
     * Insert one or more values at a specified position in an array
     *
     * ## Examples
     *
     * Insert a column into a table after the first column (positions start with 0, so `.1` would
     * mean the second position).
     *
     * ```
     * {
     *     "insert": {
     *          "$.columns.1": [
     *              {
     *                  "attribute_alias": "MY_ATTR"
     *              }
     *          ]
     *     }
     * }
     *
     * ```
     *
     * @uxon-property insert
     * @uxon-type object
     * @uxon-template {"// JSONpath to position - e.g. $.columns.1": [{"":""}]}
     *
     * @param UxonObject $arrayOfObjects
     * @return $this
     */
    protected function setInsert(UxonObject $arrayOfObjects) : GenericUxonMutation
    {
        $this->insert = $arrayOfObjects->toArray();
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