<?php
namespace exface\Core\Formulas;

use exface\Core\Exceptions\InvalidArgumentException;

/**
 * Returns a distinct string from a given sequence as string.
 * The string will also be stripped of whitespaces to ensure a homogene result.
 * 
 * If only one value is within the string, the value will be returned without any changes.
 * If multiple values are within the string, the string will be concatenated to contain only distinct values.
 * 
 * ### Examples:
 *
 * - `=Distinct('12', ',')` -> returns '12'
 * - `=Distinct('12,12,12', ',')` -> returns '12'
 * - `=Distinct('12, 12, 12', ',')` -> returns '12'
 * - `=Distinct('12; 12; 12', ';')` -> returns '12'
 * - `=Distinct('12, 12, 3, 3', ',')` -> returns '12,3'
 *
 * @author Miriam Seitz
 *        
 */
class Distinct extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     *
     * @param string|null $list - The list that should be reduced to only distinct values
     * @param string|null $separator - Separator used within the sequence.
     * @return string
     */
    function run(string $list = null, string $separator = null) : string
    {
        if ($list === null) {
            throw new InvalidArgumentException('No string to process provided.');
        }

        // remove all white spaces for a homogen sequence
        $list = preg_replace('/\s+/','',$list);

        // create new string with unique sequence
        $list_as_array = explode($separator, $list);
        $distinct_array = array_unique($list_as_array);

        return implode($separator, $distinct_array);
    }
}