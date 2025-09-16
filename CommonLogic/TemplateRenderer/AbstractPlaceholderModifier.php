<?php

namespace exface\Core\CommonLogic\TemplateRenderer;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderModifierInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * 
 */
abstract class AbstractPlaceholderModifier implements PlaceholderModifierInterface
{
    private $workbench;

    /**
     * @param WorkbenchInterface $workbench
     * @param string $filterSuffix
     */
    public function __construct(WorkbenchInterface $workbench, string $filterSuffix)
    {
        $this->workbench = $workbench;
        $this->parse($filterSuffix);
    }

    /**
     * @param string $filterSuffix
     * @return mixed
     */
    protected abstract function parse(string $filterSuffix);
}