<?php

namespace exface\Core\Interfaces\TemplateRenderers;

interface PrefixedPlaceholderResolverInterface
{
    /**
     * @return string
     */
    public function GetPrefix() : string;

    /**
     * @param string $prefix
     * @return void
     */
    public function SetPrefix(string $prefix) : void;
}