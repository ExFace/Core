<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderNotFoundError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

/**
 * 
 * ```
 * $renderer->addPlaceholderResolver(
 *		new OptionalPlaceholders(
 *			function() use ($dataSheet, $rowIndex) {
 *				return $dataSheet ? new DataRowPlaceholders($dataSheet, $rowIndex) : null;
 *			},
 *			'~data:',
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
    private PlaceholderResolverInterface|null $innerResolver = null;

    protected $innerConstructor = null;

    protected string|null $errorText = null;

    /**
     *
     * @param callable $resolverConstructor
     * @param string   $prefix
     * @param string   $errorText
     */
    public function __construct(callable $resolverConstructor, string $prefix, string $errorText)
    {
        $this->innerConstructor = $resolverConstructor;
        $this->errorText = $errorText;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array
    {
        $vals = [];
        $resolver = $this->getInnerResolver();
        
        if ($resolver === null) {
            $myPhs = $this->filterPlaceholders($placeholders);
            if (! empty($myPhs)) {
                throw new PlaceholderNotFoundError(implode(', ', $myPhs), $this->errorText);
            }
        } else {
            $vals = $resolver->resolve($placeholders, $logbook);
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