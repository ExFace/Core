<?php

namespace exface\Core\CommonLogic\TemplateRenderer;

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
    public function getPrefix() : string
    {
        return $this->prefix;
    }

    protected function setPrefix(string $prefix) : PlaceholderResolverInterface
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     *
     * @param string[] $placeholders
     * @param string $prefix
     * @return string[]
     */
    protected function filterPlaceholders(array $placeholders) : array
    {
        return array_filter($placeholders, function($ph) {
            return StringDataType::startsWith($ph, $this->getPrefix());
        });
    }

    /**
     *
     * @param string $placeholder
     * @param string $prefix
     * @return string
     */
    protected function stripPrefix(string $placeholder) : string
    {
        $prefix = $this->getPrefix();
        if ($prefix === '') {
            return $placeholder;
        }
        return StringDataType::substringAfter($placeholder, $prefix);
    }
}