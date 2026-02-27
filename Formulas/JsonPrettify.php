<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\CommonLogic\Model\Formula;

/**
 * Pretty-prints the given JSON
 * 
 * Turns `{"no": "21452314", "positions": [{"product": "M021"}]}` into
 * 
 * ```
 * {
 *  "no": "21452314", 
 *  "positions": 
 *      [
 *          {
 *              "product": "M021"
 *          }
 *      ]
 * }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class JsonPrettify extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    function run(string $json = null)
    {
        if ($json === null || $json === '') {
            return '';
        }
        
        return JsonDataType::prettify(JsonDataType::decodeJson($json, false));
    }
}