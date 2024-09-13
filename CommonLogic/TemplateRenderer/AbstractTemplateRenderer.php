<?php
namespace exface\Core\CommonLogic\TemplateRenderer;

use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\iCanBeCopied;

/**
 * Base implementation of the TemplateRendererInterface.
 * 
 * @author andrej.kabachnik
 *
 */
abstract class AbstractTemplateRenderer implements TemplateRendererInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $resolvers = [];
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface::addPlaceholder()
     */
    public function addPlaceholder(PlaceholderResolverInterface $resolver) : TemplateRendererInterface
    {
        $this->resolvers[] = $resolver;
        return $this;
    }

    /**
     * A convenience method that adds an array of placeholders, ignoring any items that are not an instance of `PlaceholderResolverInterface`.
     *
     * @param array $resolverArray
     * @return TemplateRendererInterface
     */
    public function addPlaceholdersViaArray(array $resolverArray) : TemplateRendererInterface {
        foreach ($resolverArray as $resolver) {
            if($resolver instanceof PlaceholderResolverInterface) {
                $this->resolvers[] = $resolver;
            }
        }

        return $this;
    }

    /**
     * Returns a new array with all registered resolvers.
     *
     * @return array
     */
    public function getResolvers() : array
    {
        return $this->resolvers;
    }

    /**
     * 
     * @return PlaceholderResolverInterface[]
     */
    protected function getPlaceholderResolvers() : array
    {
        return $this->resolvers;
    }

    /**
     * 
     * @see iCanBeCopied::copy()
     * @return TemplateRendererInterface
     */
    public function copy() : self
    {
        return clone $this;
    }
}