<?php
namespace exface\Core;

class utils
{

    /**
     * Returns an array of ExFace-placeholders found in a string.
     * E.g. will return ["name", "id"] for string "Object [#name#] has the id [#id#]"
     *
     * @param string $string            
     * @return array
     */
    public function findPlaceholdersInString($string)
    {
        $placeholders = array();
        preg_match_all("/\[#([^\]\[#]+)#\]/", $string, $placeholders);
        return is_array($placeholders[1]) ? $placeholders[1] : array();
    }
}
?>