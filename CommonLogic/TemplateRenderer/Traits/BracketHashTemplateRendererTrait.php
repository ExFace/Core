<?php
namespace exface\Core\CommonLogic\TemplateRenderer\Traits;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;

/**
 * Trait for string templates using the standard `[##]` placeholder syntax.
 * 
 * @author andrej.kabachnik
 *
 */
trait BracketHashTemplateRendererTrait
{    
    /**
     * 
     * @param string $tpl
     * @return string[]
     */
    protected function getPlaceholders(string $tpl) : array
    {
        return array_unique(StringDataType::findPlaceholders($tpl));
    }
    
    /**
     * 
     * @param string[] $placeholders
     * @return array
     */
    protected function getPlaceholderValues(array $placeholders) : array
    {
        $phVals = [];
        foreach ($this->getPlaceholderResolvers() as $resolver) {
            $phVals = array_merge($phVals, $resolver->resolve($placeholders));
        }
        if (count($phVals) < count($placeholders)) {
            $missingPhs = array_diff($placeholders, array_keys($phVals));
            throw new RuntimeException('Unknown placehodler(s) "[#' . implode('#]", "[#', $missingPhs) . '#]" found in template "' . $this->getTemplateFilePath() . '"!');
        }
        return $phVals;
    }
    
    /**
     * 
     * @param string $tplString
     * @param array $placeholderValues
     * @return string
     */
    protected function resolvePlaceholders(string $tplString, array $placeholderValues) : string
    {
        return StringDataType::replacePlaceholders($tplString, $placeholderValues, false);
    }
}