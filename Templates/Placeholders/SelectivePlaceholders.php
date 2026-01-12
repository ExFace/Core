<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

/**
 * Applies the given resolvers and leaves all other placeholders in place
 *
 * @author Andrej Kabachnik
 */
class SelectivePlaceholders extends PlaceholderGroup
{
    private string $before;
    private string $after;
    
    /**
     * @param PlaceholderResolverInterface[] $resolvers
     * @param string $delimiterBefore
     * @param string $delimiterAfter
     */
    public function __construct(array $resolvers = [], string $delimiterBefore = '[#', string $delimiterAfter = '#]')
    {
        parent::__construct($resolvers);
        $this->before = $delimiterBefore;
        $this->after = $delimiterAfter;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $vals = parent::resolve($placeholders);
        $otherPhs = array_diff($placeholders, array_keys($vals));
        foreach ($otherPhs as $otherPh) {
            $vals[$otherPh] = $this->before . $otherPh . $this->after;
        }
        return $vals;
    }
}