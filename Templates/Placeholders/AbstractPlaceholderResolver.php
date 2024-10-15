<?php

namespace exface\Core\Templates;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\PrefixedPlaceholderResolverInterface;

abstract class AbstractPlaceholderResolver 
    implements PlaceholderResolverInterface, PrefixedPlaceholderResolverInterface
{
    protected string $prefix = '';

    /**
     * @return string
     */
    public function GetPrefix() : string
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     * @return void
     */
    public function SetPrefix(string $prefix) : void
    {
        $this->prefix = $prefix;
    }

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