<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;

/**
 * Resolves placeholders to app properties values: `~app:alias`.
 * 
 * @author Andrej Kabachnik
 */
class AppPlaceholders extends AbstractPlaceholderResolver
{
    use SanitizedPlaceholderTrait;
    
    private $app = null;
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(AppInterface $app, string $prefix = '~app')
    {
        $this->setPrefix($prefix);
        $this->app = $app;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array
    {     
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $prop = $this->stripPrefix($placeholder);
            $getter = 'get' . ucfirst($prop);
            switch (true) {
                case method_exists($this->app, $getter):
                    $val = call_user_func([$this->app, $getter]);
                    break;
                default:
                    // Do nothing?
            }
            $vals[$placeholder] = $this->sanitizeValue($val);
        }
        return $vals;
    }
}