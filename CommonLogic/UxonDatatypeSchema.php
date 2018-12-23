<?php
namespace exface\Core\CommonLogic;

/**
 * UXON-schema class for data types.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonDatatypeSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\UxonSchema::getEntityClass()
     */
    public function getEntityClass(UxonObject $uxon, array $path, string $rootEntityClass = '\exface\Core\CommonLogic\DataTypes\AbstractDataType') : string
    {
        $name = $rootEntityClass;
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'alias') === 0) {
                $w = $this->getEntityClassFromWidgetType($value);
                if ($this->validateEntityClass($w) === true) {
                    $name = $w;
                }
            }
        }
        
        if (count($path) > 1) {
            return parent::getEntityClass($uxon, $path, $name);
        }
        
        return $name;
    }
}