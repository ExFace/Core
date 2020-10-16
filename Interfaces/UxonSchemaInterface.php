<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;

interface UxonSchemaInterface extends WorkbenchDependantInterface
{   
    /**
     * Returns the name of the schema: widget, action, datatype, etc.
     * @return string
     */
    public static function getSchemaName() : string;
    
    /**
     * Returns the prototype class for a given path.
     *
     * @param UxonObject $uxon
     * @param array $path
     * @param string|NULL $rootPrototypeClass
     * @return string
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : string;
    
    /**
     * Returns the value of an inheritable property from the point of view of the end of the given path.
     *
     * This is usefull for common properties like `object_alias`, that get inherited from the parent
     * prototype automatically, but can be specified explicitly by the user.
     *
     * @param UxonObject $uxon
     * @param array $path
     * @param string $propertyName
     * @param string $rootValue
     * @return mixed
     */
    public function getPropertyValueRecursive(UxonObject $uxon, array $path, string $propertyName, string $rootValue = '');
    
    /**
     * Returns an array with names of all properties of a given prototype class.
     *
     * @param string $prototypeClass
     * @return string[]
     */
    public function getProperties(string $prototypeClass) : array;
    
    /**
     * 
     * @param string $prototypeClass
     * @return string[]
     */
    public function getPropertiesTemplates(string $prototypeClass) : array;
    
    /**
     * 
     * @param string $annotation
     * @param mixed $value
     * @param string|NULL $prototypeClass
     * @return string[]
     */
    public function getPropertiesByAnnotation(string $annotation, $value, string $prototypeClass = null) : array;
    
    /**
     * Returns an array of UXON types valid for the given prototype class property.
     *
     * The result is an array, because a property may accept multiple types
     * (separated by a pipe (|) in the UXON annotations). The array elements
     * have the same order, as the types in the annotation.
     *
     * @param string $prototypeClass
     * @param string $property
     * @return string[]
     */
    public function getPropertyTypes(string $prototypeClass, string $property) : array;
    
    /**
     * Returns an array of valid values for properties with fixed values (or an empty array for non-enum properties).
     *
     * @param UxonObject $uxon
     * @param array $path
     * @param string $search
     * @return string[]
     */
    public function getValidValues(UxonObject $uxon, array $path, string $search = null, string $rootPrototypeClass = null, MetaObjectInterface $rootObject = null) : array;
    
    /**
     * Returns the meta object for the prototype at the end of the path.
     *
     * @param UxonObject $uxon
     * @param array $path
     * @param MetaObjectInterface $rootObject
     * @return MetaObjectInterface
     */
    public function getMetaObject(UxonObject $uxon, array $path, MetaObjectInterface $rootObject = null) : MetaObjectInterface;
    
    /**
     * Returns TRUE if this schema was instantiated as part of another one (it's parent schema)
     *
     * @return bool
     */
    public function hasParentSchema();
    
    /**
     * Returns the parent schema (if exists).
     *
     * @return UxonSchemaInterface
     */
    public function getParentSchema() : UxonSchemaInterface;
    
    /**
     * Returns an array with preset data for this schema.
     * 
     * Each element of the array must have the following structure:
     * [
     *  DESCRIPTION: ""
     *  MODIFIED_ON: ""
     *  NAME: "" // Required!!!
     *  PROTOTYPE: ""
     *  UID: "" // Required!
     *  UXON: "{}"
     *  WRAP_FLAG: "0"
     *  WRAP_PATH: ""
     * ]
     * 
     * @param UxonObject $uxon
     * @param array $path
     * @param string $rootPrototypeClass
     * @return array
     */
    public function getPresets(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array;
}