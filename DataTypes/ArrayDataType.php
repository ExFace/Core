<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class ArrayDataType extends AbstractDataType
{
    /**
     * 
     * @param mixed $val
     * @throws DataTypeCastingError
     * @return array
     */
    public static function cast($val)
    {
        if (is_array($val) === false) {
            throw new DataTypeCastingError('Cannot cast ' . gettype($val) . ' to array!');
        }
        return $val;
    }
    
    /**
     * 
     * @param array $array
     * @return bool
     */
    public static function isAssociative(array $array) : bool
    {
        if (array() === $array) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     *
     * @param array $array
     * @return bool
     */
    public static function isSequential(array $array) : bool
    {
        return static::isAssociative($array) === false;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataTypes\DataTypeInterface::isValueEmpty()
     */
    public static function isValueEmpty($val) : bool
    {
        return empty($val) === true;
    }
    
    /**
     * Extracts parts of a JSON based on a XPath like query.
     * 
     * Examples for a book object (see structure below):
     * 
     * - `title` will give you the title of the book
     * - `pulisher` will get the entire pulisher object as an associative array
     * - `publisher/address/country_code` get the country code from the address of the publisher
     * - `authors[0]/name` will get the names of the first author
     * - `scancodes[type=ean8]/code` will the scancode with type "ean8".
     * 
     * ```
     *  {
     *      "title": "...",
     *      "publisher": {
     *          "name": "..."
     *          "address": {
     *             "country": "...",
     *             "country_code": "..."
     *          }
     *      },
     *      "authors": [{
     *             "name": "..."
     *         }, {
     *             "name": "..."
     *          }
     *      ],
     *      "scancodes": [{
     *              "type": "ean13",
     *              "value": "..."
     *         }, {
     *            "type": "ean8",
     *             "value": "..."
     *         }
     *      ]
     *  }
     * ```
     * 
     * @param array $array
     * @param string $path
     * 
     * @throws RuntimeException
     * 
     * @return mixed
     */
    public static function filterXPath(array $array, string $path)
    {
        $val = $array;
        if ($path === '/') {
            return $val;
        }
        foreach (explode('/', $path) as $step) {
            if ($cond_start = strpos($step, '[')) {
                if (substr($step, - 1) != ']')
                    throw new InvalidArgumentException('Invalid conditional selector in array XPath expression "' . $path . '": "' . $step . '"!');
                    $cond = explode('=', substr($step, $cond_start + 1, - 1));
                    if ($val = $val[substr($step, 0, $cond_start)]) {
                        foreach ($val as $v) {
                            if ($v[$cond[0]] == $cond[1]) {
                                $val = $v;
                                break;
                            }
                        }
                    }
            } else {
                $val = $val[$step];
            }
        }
        return $val;
    }
    
    /**
     * Reduces the given array of values to a single value by applying the given aggregator.
     * If no aggregator is specified, returns the first value.
     *
     * @param array $values
     * @param AggregatorInterface $aggregator
     * @return array
     */
    public static function aggregateValues(array $values, AggregatorInterface $aggregator = null)
    {
        if ($aggregator === null) {
            $func = AggregatorFunctionsDataType::LIST_DISTINCT;
            $args = [];
        } else {
            $func = $aggregator->getFunction()->getValue();
            $args = $aggregator->getArguments();
        }
        
        $output = '';
        switch ($func) {
            case AggregatorFunctionsDataType::LIST_ALL:
                $output = implode(($args[0] ? $args[0] : EXF_LIST_SEPARATOR), $values);
                break;
            case AggregatorFunctionsDataType::LIST_DISTINCT:
                $output = implode(($args[0] ? $args[0] : EXF_LIST_SEPARATOR), array_unique($values));
                break;
            case AggregatorFunctionsDataType::MIN:
                $output = count($values) > 0 ? min($values) : 0;
                break;
            case AggregatorFunctionsDataType::MAX:
                $output = count($values) > 0 ? max($values) : 0;
                break;
            case AggregatorFunctionsDataType::COUNT:
                $output = count($values);
                break;
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                $output = count(array_unique($values));
                break;
            case AggregatorFunctionsDataType::SUM:
                $output = array_sum($values);
                break;
            case AggregatorFunctionsDataType::AVG:
                $output = count($values) > 0 ? array_sum($values) / count($values) : 0;
                break;
            default:
                throw new UnexpectedValueException('Unsupported aggregator function "' . $func . '"!');
        }
        return $output;
    }
}