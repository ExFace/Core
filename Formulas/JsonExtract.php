<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\CommonLogic\Model\Formula;

/**
 * Extracts a value from a JSON using the provided JSONpath expression
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
 * - `=JsonExtract(MY_JSON_ATTR, '$.Category')` -> `OLDE Monitors`
 * - `=JsonExtract(MY_JSON_ATTR, '$.Prices.Retail.Value')` -> `199.9`
 * - `=JsonExtract(MY_JSON_ATTR, '$.Category.*.Value')` -> `164, 199.9`
 * - `=JsonExtract(MY_JSON_ATTR, '$.Prices')` => `{"Wholesale": {"Value": "164","Currency": "EUR"},"Retail": {"Value": "199.9","Currency": "EUR"}}`
 *
 * @author Andrej Kabachnik
 *        
 */
class JsonExtract extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    function run(string $json = null, string $jsonPath = null, string $delimiter = ', ')
    {
        if ($json === null || $json === '') {
            return '';
        }
        if ($jsonPath === null || $jsonPath === '') {
            return '';
        }
        $array = JsonDataType::decodeJson($json);
        $extracts = ArrayDataType::filterJsonPath($array, $jsonPath);
        $result = '';
        foreach ($extracts as $extract) {
            if (is_object($extract) || is_array($extract)) {
                $result = JsonDataType::encodeJson($extracts);
                break;
            }
            if ($extract === null || $extract === '') {
                continue;
            }
            $result = ($result !== '' ? $delimiter : '') . $extract;
        }
        return $result;
    }
}