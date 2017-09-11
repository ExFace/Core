<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Exceptions\DataTypeValidationError;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\AppInterface;

interface DataTypeInterface extends ExfaceClassInterface, AliasInterface, MetaModelPrototypeInterface
{

    /**
     * Constructuro
     *
     * @param NameResolverInterface $name_resolver            
     */
    public function __construct(NameResolverInterface $name_resolver);

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
     * Returns TRUE if the current data type equals is derived from the given one (e.g.
     * Integer::is(Number) = true) and FALSE otherwise.
     *
     * @param DataTypeInterface|string $data_type_or_resolvable_name
     * @return boolean
     */
    public function is($data_type_or_resolvable_name);

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
    
    /**
     * Returns the name resolver for this data type.
     * 
     * @return NameResolverInterface
     */
    public function getNameResolver();
    
    /**
     * Returns the app, to which this data type belongs to.
     * 
     * NOTE: if the model of this data type belongs to another app, than its prototype, this method
     * will return the app of the model. Use getPrototypeNameResolver->getApp() to get the app
     * of the prototype.
     * 
     * @return AppInterface
     */
    public function getApp();
}
?>