<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

/**
 * Data type for metamodel aliases.
 * 
 * @author Andrej Kabachnik
 *
 */
class MetamodelAliasDataType extends StringDataType
{
    const VALIDATION_REGEX = '/^[^_~=\.][a-zA-Z0-9_]*$/';
    
    const VALIDATION_REGEX_WITH_NAMESPACE = '/^[^_~=\.][a-zA-Z0-9_\.]*$/';
    
    private $includesNamespace = false;
    
    /**
     * 
     * @param string|array|NULL $string
     * @param bool $includesNamespace
     * @throws DataTypeCastingError
     * @return string|NULL
     */
    public static function cast($string, bool $includesNamespace = false)
    {
        if (static::isValueEmpty($string) === true){
            return $string;
        } 
        
        $pattern = $includesNamespace ? self::VALIDATION_REGEX_WITH_NAMESPACE : self::VALIDATION_REGEX;
        if (is_array($string) === true){
            foreach ($string as $str) {
                if (preg_match($pattern, $str) !== 1) {
                    throw new DataTypeCastingError('Invalid metamodel alias "' . $str . '"!', '6XDP7LI');
                }
            }
            return implode(EXF_LIST_SEPARATOR, $string);
        } 
        
        if (preg_match($pattern, $string) !== 1) {
            throw new DataTypeCastingError('Invalid metamodel alias "' . $string . '"!', '6XDP7LI');
        }
        
        return $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getValidationErrorCode()
     */
    public function getValidationErrorCode() : ?string
    {
        return parent::getValidationErrorCode() ?? '6XDP7LI';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::getValidatorRegex()
     */
    public function getValidatorRegex() : ?string
    {
        return parent::getValidatorRegex() ?? ($this->getIncludesNamespace() ? self::VALIDATION_REGEX_WITH_NAMESPACE : self::VALIDATION_REGEX);
    }
    
    /**
     * 
     * @return bool
     */
    protected function getIncludesNamespace() : bool
    {
        return $this->includesNamespace;
    }
    
    /**
     * Set to TRUE to allow aliases with namespaces - e.g. `my.App.ALIAS` along with just `ALIAS`.
     * 
     * @uxon-property includes_namespace
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool $value
     * @return MetamodelAliasDataType
     */
    public function setIncludesNamespace(bool $value) : MetamodelAliasDataType
    {
        $this->includesNamespace = $value;
        return $this;
    }
}