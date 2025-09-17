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
 * - `insert` - adds one or more UXON objects to an array before the element, the JSONpath points to
 * - `change` - changes a scalar property at the end of the JSONpath - e.g. to set a widget to `hidden:true`
 * - `replace` - replaces a UXON object with another one. The JSONpath should point to an object
 * - `remove` - removes all keys corresponding to the JSONpath entirely
 * 
 * ## Using JSONpath
 * 
 * In most cases, you will need a JSONpath pointer to the place in the UXON, that you want to change.
 * 
 * To check, where exactly you JSONpath points to, use the online evaluator at https://jsonpath.com/.
 * 
 * ### Quick reference
 * 
 * ```
 * {
 *      "widget_type": "DataTable",
 *      "object_alias: "exface.Core.PAGE",
 *      "filters": [
 *          {"attribute_alias": "Name"},
 *          {"attribute_alias": "APP"}
 *      ],
 *      "columns": [
 *          {"attribute_alias": "Name"},
 *          {"attribute_alias": "APP__ALIAS", "caption": "App"},
 *          {"attribute_alias": "IN_MENU"},
 *          {"attribute_alias": "APP", "hidden": true}
 *      ]
 * }
 * 
 * ```
 * 
 * | JSONpath | Result
 * | --------- | --------
 * | `$.widget_type` | The widget type "DataTable"
 * | `$.filters[1]` | The second filter (because array numbering starts with `0`)
 * | `$.filters[1].attribute_alias` | Attribute alias of the second filter
 * | `$.columns[1,3]` | Second and fourth column 
 * | `$.filters[1:3]` | Columns from index `1` to index `3`
 * | `$.columns[:2]` | First three columns
 * | `$.columns[x:y:z]` | Columns from `x` to `y` with a step of `z`.
 * | `$.columns[?(@.attribute_alias == 'NAME')]` | Column with `attribute_alias` "NAME".
 * | `$..*[?(@.attribute_alias == 'NAME')]` | All widgets with `attribute_alias` "NAME".
 * | `$.columns[?(@.attribute_alias == 'NAME')].hidden` | Hidden property of column with `attribute_alias` "NAME".
 * | `$.columns[?(@.attribute_alias == $.filters[1])]` | Columns with `attribute_alias` "APP"
 * | `$[columns]` | All columns.
 * | `$['columns']` | All columns.
 * | `$.columns[*][attribute_alias, 'hidden', "caption"]` | `attribute_alias`, `hidden`-property and `caption` of all columns.
 * | `$..filters[?(@.attribute_alias in [$.columns[0].attribute_alias, $.columns[2].attribute_alias])]` | All filters (from all widgets) with `attribute_alias` matching that of the first or thrid column.
 * | `$.filters[?(@.attribute_alias == 'APP' and @.hidden == true 10 or @.disabled == true)]` | APP-filters, that are hidden or disabled  (`and` takes precedence to `or`)
 * 
 * ### Getting the path from the UXON editor
 * 
 * You can start by copying the JSONpath directly in the UXON editor of whatever you are changing - e.g. an
 * action. Simly press `JSON-Path` in the menu of the desired node in the editor and copy the path.
 * 
 * You will get something like
 * 
 * ```
 *  $.dialog.widgets[1].tabs[4].widgets[0].columns[4]
 * 
 * ```
 * 
 * To make it more readable and more immune to minor changes of the target UXON, replace numeric indices in
 * the array by matcher expressions:
 * 
 * ```
 *  $.dialog.widgets[1].tabs[?(@.caption=='General')].widgets[0].columns[?(@.attribute_alias=='DISABLED_FLAG')]
 * 
 * ```
 * 
 * This is much more verbose and will work even if the order of the tabs or columns changes in future.
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
            $jsonObj->removeObject($jsonPath);
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
     *              {"attribute_alias": "MY_ATTR", "hint": "New column!"}
     *          ]
     *     }
     * }
     *
     * ```
     *
     * Insert a column into a table before a specified attribute.
     *
     *  ```
     *  {
     *      "insert": {
     *           "$.columns[?(@.attribute_alias=='OTHER_ATTR')]": [
     *               {"attribute_alias": "MY_ATTR"}
     *           ]
     *      }
     *  }
     *
     *  ```
     *
     * @uxon-property insert
     * @uxon-type object
     * @uxon-template {"// JSONpath to position - e.g. $.columns[?(@.attribute_alias=='MYATTR')]": [{"":""}]}
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
     * Remove columns with certain attributes_
     *
     *  ```
     *  {
     *      "remove": [
     *           "$.columns[?(@.attribute_alias=='ATTR1')]",
     *           "$.columns[?(@.attribute_alias=='ATTR2')]"
     *      ]
     *  }
     *
     *  ```
     *
     * Remove filters over a certain attribute from all widgets:
     *
     *  ```
     *  {
     *      "remove": [
     *           "$..filters[?(@.attribute_alias=='MYATTR')]"
     *      ]
     *  }
     *
     *  ```
     *
     * @uxon-property remove
     * @uxon-type object
     * @uxon-template ["// JSONpath to remove - e.g. $.columns[?(@.attribute_alias=='MYATTR')]"]
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