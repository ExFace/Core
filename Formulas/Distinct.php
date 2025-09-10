<?php
namespace exface\Core\Formulas;

use exface\Core\Exceptions\InvalidArgumentException;

/**
 * Returns a distinct string from a given sequence as string.
 * 
 * If only one value is within the string, the value will be returned without any changes.
 * If multiple values are within the string, the string will be concatenated to contain only distinct values.
 * 
 * ### Examples:
 *
 * - `=Distinct('12')` -> returns '12'
 * - `=Distinct('12,12,12')` -> returns '12'
 * - `=Distinct('12, 12, 12')` -> returns '12'
 * - `=Distinct('12; 12; 12')` -> returns '12'
 * - `=Distinct('12, 12, 3, 3')` -> returns '12, 3'
 *
 * @author Miriam Seitz
 *        
 */
class Distinct extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     * 
     * @param string|null $list - The list that should be reduced to only distinct values
     * @return string
     */
    function run(string $list = null) : string
    {
        if ($list === null) {
            throw new InvalidArgumentException('No string to process provided.');
        }

        // remove all white spaces for a homogen sequence
        $list = preg_replace('/\s+/','',$list);

        switch (true) {
         case str_contains($list, ','):
             $separator = ',';
             $list_as_array = explode($separator, $list);
             break;
         case str_contains($list, ';'):
             $separator = ';';
             $list_as_array = explode($separator, $list);
             break;
             default:
                 throw new InvalidArgumentException('Provided string \'' . $list . '\' has no recognizable separator.');
        }

        $distinct_array = array_unique($list_as_array);

        return implode($separator, $distinct_array);
    }
}