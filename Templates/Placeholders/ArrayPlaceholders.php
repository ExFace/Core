<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Templates\AbstractPlaceholderResolver;

/**
 * Replaces placeholders with values provided as a placeholder=>value array.
 * 
 * ## Examples
 * 
 * - `new ArrayPlaceholders(['ph1' => 'val1', 'ph2' => 'val2'])` will replace `ph1` with `val1`
 * - `new ArrayPlaceholders(['ph1' => 'val1', 'ph2' => 'val2'], '~file:')` will replace `~file:ph1` with `val1`
 * 
 *
 * @author Andrej Kabachnik
 */
class ArrayPlaceholders extends AbstractPlaceholderResolver
{
    private $placeholders = [];
    
    /**
     * 
     * @param array $placeholders
     * @param string $prefix
     */
    public function __construct(array $placeholders, string $prefix = '')
    {
        $this->prefix = $prefix ?? '';
        $this->placeholders = $placeholders;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders, $this->prefix) as $placeholder) {
            $key = $this->stripPrefix($placeholder, $this->prefix);
            if (array_key_exists($key, $this->placeholders)) {
                $vals[$placeholder] = $this->placeholders[$key];
            }
        }
        return $vals;
    }
}