<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

Interface TemplateRendererExceptionInterface
{
    /**
     * 
     * @return TemplateRendererInterface
     */
    public function getResolver() : TemplateRendererInterface;
}