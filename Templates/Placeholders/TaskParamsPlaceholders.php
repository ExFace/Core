<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;

/**
 * Resolves placeholders parameters of tasks: `~task:param_name`.
 * 
 * @author Andrej Kabachnik
 */
class TaskParamsPlaceholders extends AbstractPlaceholderResolver
{
    use SanitizedPlaceholderTrait;
    
    private TaskInterface $task;
    
    /**
     * 
     * @param FacadeInterface $task
     * @param string $prefix
     */
    public function __construct(TaskInterface $task, string $prefix = '~task:')
    {
        $this->setPrefix($prefix);
        $this->task = $task;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $phStripped = $this->stripPrefix($placeholder);
            $val = $this->task->getParameter($phStripped);
            $vals[$placeholder] = $this->sanitizeValue($val);
        }
        return $vals;
    }
}