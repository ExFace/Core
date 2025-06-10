<?php
namespace exface\Core\Exceptions\TemplateRenderer;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\TemplatePlaceholderExceptionInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

/**
 * Exception thrown on errors in placeholder resolvers
 *
 * @author Andrej Kabachnik
 */
class PlaceholderResolverRuntimeError extends RuntimeException implements TemplatePlaceholderExceptionInterface
{
    private $resolver = null;
    
    /**
     * 
     * @param PlaceholderResolverInterface $renderer
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(PlaceholderResolverInterface $resolver, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->resolver = $resolver;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\TemplatePlaceholderExceptionInterface::getResolver()
     */
    public function getResolver(): PlaceholderResolverInterface
    {
        return $this->resolver;
    }
}