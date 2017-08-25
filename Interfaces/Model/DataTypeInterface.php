<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Constants\SortingDirections;

interface DataTypeInterface extends \exface\Core\Interfaces\ExfaceClassInterface
{

    /**
     * Constructuro
     *
     * @param Workbench $exface            
     */
    public function __construct(Workbench $exface);

    /**
     *
     * @return Model
     */
    public function getModel();

    /**
     * Returns the string name of the data type (e.g.
     * Number, String, etc.)
     *
     * @return string
     */
    public function getName();

    /**
     * Returns TRUE if the current data type is derived from the given one (e.g.
     * Integer::is(Number) = true) and FALSE otherwise.
     *
     * @param
     *            AbstractDataType | string $data_type_or_string
     * @return boolean
     */
    public function is($data_type_or_string);

    /**
     * Returns a normalized representation of the given string, that can be interpreted by the ExFace core correctly.
     * E.g. Date::parse('21.9.1984') = 1984-09-21
     *
     * @param string $string            
     * @throws DataTypeValidationError
     * @return string
     */
    public static function parse($string);

    /**
     * Returns TRUE if the given value matches the data type (and thus can be parsed) or FALSE otherwise.
     *
     * @param string $string            
     * @return boolean
     */
    public static function validate($string);
    
    /**
     * 
     * @return SortingDirections
     */
    public function getDefaultSortingDirection();
}
?>