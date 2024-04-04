<?php
namespace exface\Core\Formulas;

/**
 * Returns the position (number starting with 1) of one text string inside another. 
 * 
 * When the text is not found, FIND returns the value specified in the fourth parameter (`null` by default).
 * 
 * Syntax: `Find(find_text, find_within, start_pos, if_not_fond)`
 *
 * @author Andrej Kabachnik
 *        
 */
class Find extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     * 
     * @param string|NULL $findText
     * @param string|NULL $findWithin
     * @param int $startPos
     * @param mixed $ifNotFound
     * @return int|NULL
     */
    function run($findText = null, $findWithin = null, int $startPos = null, $ifNotFound = null)
    {
        if ($findText === null || $findText === '' || $findWithin === null || $findWithin === '') {
            return $ifNotFound;
        }
        
        $pos = stripos($findWithin, $findText, $startPos);
        
        if ($pos === false) {
            return $ifNotFound;
        }
        
        return ($pos + 1);
    }
}