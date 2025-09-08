<?php
namespace exface\Core\Templates\Modifiers;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderModifier;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderModifierInterface;

/**
 * 
 */
class IfNullModifier extends AbstractPlaceholderModifier
{
    private $default = null;

    /**
     * {@inheritDoc}
     * @see AbstractPlaceholderModifier::parse()
     */
    protected function parse(string $filterSuffix)
    {
        if (StringDataType::startsWith($filterSuffix, '??')) {
            $default = ltrim($filterSuffix, "?");
        }
        $this->default = $default;
    }
    
    public static function stripFilter(string $expression, string $filterDelimiter = '|') : string
    {
        return StringDataType::substringBefore($expression, $filterDelimiter . '??', $expression);
    }

    /**
     * @param string $filter
     * @return string
     */
    public static function findDefaultValue(string $expression, string $filterDelimiter = '|')
    {
        $default = StringDataType::substringAfter($expression, $filterDelimiter . '??', null);
        return $default;
    }

    /**
     * {@inheritDoc}
     * @see PlaceholderModifierInterface::parse()
     */
    public function apply($value)
    {
        return $value ?? $this->default;
    }
}