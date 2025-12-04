<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\IntegerDataType;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;

/**
 * Counts the number of matches of a JSONpath expression in a JSON
 * 
 * Here is a good tool find the right JSONpath expression and to play around: https://jsonpath.com.
 * 
 * ## Examples
 * 
 * Assuming the following JSON is stored in the attribute `MY_JSON_ATTR`
 * 
 * ```
 * {
 *      "Category": "OLED Monitors",
 *      "Color": "Silver",
 *      "Prices": {
 *          "Wholesale": {
 *              "Value": "164",
 *              "Currency": "EUR"
 *          },
 *          "Retail": {
 *              "Value": "199.9",
 *              "Currency": "EUR"
 *          }
 *      }
 * }
 * 
 * ```
 * 
 * - `=JsonCount(MY_JSON_ATTR, '$.Prices')` -> 2
 *
 * @author Andrej Kabachnik
 *        
 */
class JsonCount extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    function run(string $json = null, string $jsonPath = null)
    {
        if ($json === null || $json === '') {
            return '';
        }
        
        if ($jsonPath === null || $jsonPath === '') {
            return '';
        }
        
        $array = JsonDataType::decodeJson($json);
        $extracts = ArrayDataType::filterJsonPath($array, $jsonPath);
        return count($extracts);
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), IntegerDataType::class);
    }
}