<?php
namespace exface\Core\Templates\AbstractProgressiveAppTemplate;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate;

class ServiceWorkerBuilder implements WorkbenchDependantInterface
{
    private $template = null;
    
    private $workbench = null;
    
    /**
     * 
     * @param AbstractAjaxTemplate $template
     */
    public function __construct(AbstractAjaxTemplate $template)
    {
        $this->template = $template;
        $this->workbench = $template->getWorkbench();
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
}