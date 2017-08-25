<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\CommonLogic\Constants\SortingDirections;

class NumberDataType extends AbstractDataType
{

    public static function parse($string)
    {
        if (is_numeric($string)) {
            // Decimal numbers
            return $string;
        } elseif ($string === '' || is_null($string)) {
            return null;
        } elseif (strpos($string, '0x') === 0) {
            // Hexadecimal numbers in '0x....'-Notation
            return $string;
        } elseif (strcasecmp($string, 'true') === 0) {
            return 1;
        } elseif (strcasecmp($string, 'false') === 0) {
            return 0;
        } elseif (strcasecmp($string, 'null') === 0) {
            return null;
        } else {
            $string = trim($string);
            $matches = array();
            preg_match('!-?\d+[,\.]?\d*+!', str_replace(' ', '', $string), $matches);
            $match = str_replace(',', '.', $matches[0]);
            if (is_numeric($match)) {
                return $match;
            }
            throw new DataTypeValidationError('Cannot convert "' . $string . '" to a number!');
            return '';
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\AbstractDataType::getDefaultSortingDirection()
     */
    public function getDefaultSortingDirection()
    {
        return SortingDirections::DESC();
    }
}
?>