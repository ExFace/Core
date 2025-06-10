<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

Interface TemplatePlaceholderExceptionInterface
{
    /**
     * 
     * @return PlaceholderResolverInterface
     */
    public function getResolver() : PlaceholderResolverInterface;
}