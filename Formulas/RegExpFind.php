<?php
namespace exface\Core\Formulas;

/**
 * Extract a value from a string via regular expression
 * 
 * Parameters:
 * 
 * 1. The string to search in (expression)
 * 2. The regular expression in Pearl syntax including delimiters: e.g. `/.+/i`
 * 3. Which match to return: e.g. `1` for the first match (default), `2` for the 
 * second or `-1` for the last one.
 * 
 * **NOTE:** backslashes in the pattern MUST be escaped as the pattern is a
 * quoted string!
 * 
 * Examples:
 * 
 * - `=RegExpFind('Hello World', '/W.*$/mi')` will yield `World`
 * - `=RegExpFind('1.2.3', '/\\.\\d/mi', -1)` will yield `.3`
 * 
 * @author Andrej Kabachnik
 *        
 */
class RegExpFind extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     * 
     * @param string $search
     * @param string $pattern
     * @param int $matchToReturn
     * @return NULL|mixed
     */
    function run($search = null, string $pattern = null, int $matchToReturn = 1)
    {
        if ($search === null || $search === '') {
            return $search;
        }
        
        if ($pattern === null || $pattern === '') {
            return $search;
        }
        
        $matches = [];
        preg_match_all($pattern, $search, $matches);
        $matches0 = $matches[0];
        
        if (empty($matches0)) {
            return null;
        }
        
        switch (true) {
            case $matchToReturn === 0:
                return $matches0[count($matches0) - 1];
            case $matchToReturn < 0:
                return $matches0[count($matches0) + $matchToReturn];
            default:
                return $matches0[$matchToReturn - 1];
        }
    }
}