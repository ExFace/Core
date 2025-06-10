<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

/**
 * Applies multiple placeholder resolvers in sequence
 *
 * @author Andrej Kabachnik
 */
class PlaceholderGroup implements PlaceholderResolverInterface
{    
    /**
     * 
     * @var PlaceholderResolverInterface[]
     */
    private $resolvers = [];
    
    /**
     * 
     * @param PlaceholderResolverInterface[] $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array
    {     
        $vals = [];
        foreach ($this->resolvers as $resolver) {
            $vals = array_merge($vals, $resolver->resolve($placeholders, $logbook));
        }
        return $vals;
    }
    
    /**
     * 
     * @param PlaceholderResolverInterface $resolver
     * @return PlaceholderGroup
     */
    public function addPlaceholderResolver(PlaceholderResolverInterface $resolver) : PlaceholderGroup
    {
        $this->resolvers[] = $resolver;
        return $this;
    }
}