<?php
namespace exface\Core\CommonLogic\AI;

use exface\Core\CommonLogic\AI\AbstractConcept;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Uxon\UxonSchema;

/**
 * UXON-schema class for AI prompt concepts
 *
 * @see UxonSchema for general information.
 *
 * @author Andrej Kabachnik
 *
 */
class AiConceptUxonSchema extends UxonSchema
{
    public static function getSchemaName() : string
    {
        return 'AI concept';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : string
    {
        $name = $rootPrototypeClass ?? $this->getDefaultPrototypeClass();
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'class') === 0) {
                $name = $value;
                break;
            }
        }
        
        if (count($path) > 1) {
            return parent::getPrototypeClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Uxon\UxonSchema::getDefaultPrototypeClass()
     */
    protected function getDefaultPrototypeClass() : string
    {
        return '\\' . AbstractConcept::class;
    }
}