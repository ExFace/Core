<?php
namespace exface\Core\CommonLogic\TemplateRenderer\Traits;

use exface\Core\DataTypes\StringDataType;

/**
 * Trait to simplify development of prefixed placeholders like `facade:property`
 * 
 * @author andrej.kabachnik
 *
 */
trait PrefixedPlaceholderTrait
{
    /**
     * 
     * @param string[] $placeholders
     * @param string $prefix
     * @return string[]
     */
    protected function filterPlaceholders(array $placeholders, string $prefix) : array
    {
        return array_filter($placeholders, function($ph) use ($prefix) {
            return StringDataType::startsWith($ph, $prefix);
        }); 
    }
    
    /**
     * 
     * @param string $placeholder
     * @param string $prefix
     * @return string
     */
    protected function stripPrefix(string $placeholder, string $prefix) : string
    {
        if ($prefix === '') {
            return $placeholder;
        }
        return StringDataType::substringAfter($placeholder, $prefix);
    }
}
