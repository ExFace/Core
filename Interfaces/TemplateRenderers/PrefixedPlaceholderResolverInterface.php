<?php

namespace exface\Core\Interfaces\TemplateRenderers;

interface PrefixedPlaceholderResolverInterface
{
    /**
     * @return string
     */
    public function GetPrefix() : string;
}