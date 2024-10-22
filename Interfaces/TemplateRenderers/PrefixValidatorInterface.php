<?php

namespace exface\Core\Interfaces\TemplateRenderers;

interface PrefixValidatorInterface
{
    public function isValidPrefixForContext(string $prefix, $context) : bool;
}