<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderNotFoundError;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

/**
 * 
 * ```
 * $renderer->addPlaceholderResolver(
 *		new OptionalPlaceholders(
 *			'~data:',
 *			function() use ($dataSheet, $rowIndex) {
 *				return $dataSheet ? new DataRowPlaceholders($dataSheet, $rowIndex) : null;
 *			},
 *			'The `~data:` placeholder cannot be used with event ' . $event->getName() . ' - this event does not have any data';
 *		)	
 *	);
 * 
 * ```
 *
 * @author Andrej Kabachnik
 */
class OptionalPlaceholders extends AbstractPlaceholderResolver
{    
    private $innerResolver = null;

    private $innerConstructor = null;

    private $errorText = null;
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(callable $resolverContructor, string $prefix, string $errorText)
    {
        $this->innerConstructor = $resolverContructor;
        $this->errorText = $errorText;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $resolver = $this->getInnerResolver();
        if ($resolver === null) {
            $myPhs = $this->filterPlaceholders($placeholders);
            if (! empty($myPhs)) {
                throw new PlaceholderNotFoundError(implode(', ', $myPhs), $this->errorText);
            }
        } else {
            $vals = $resolver->resolve($placeholders);
        }

        return $vals;
    }

    protected function getInnerResolver() : ?PlaceholderResolverInterface
    {
        if ($this->innerResolver === null && $this->innerConstructor !== null) {
            $constructor = $this->innerConstructor;
            $this->innerResolver = $constructor();
            if ($this->innerResolver instanceof AbstractPlaceholderResolver) {
                $this->innerResolver->setPrefix($this->getPrefix());
            }
        }
        return $this->innerResolver;
    }
}