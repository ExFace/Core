<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\CommonLogic\Selectors\Traits\PrototypeSelectorTrait;
use exface\Core\Interfaces\Selectors\PrototypeSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class UxonPrototypeSelector implements PrototypeSelectorInterface
{
    use PrototypeSelectorTrait;
    
    private string $string;
    private WorkbenchInterface $workbench;

    /**
     * @inheritDoc
     */
    public function __construct(WorkbenchInterface $workbench, string $selectorString)
    {
        $this->string = $selectorString;
        $this->workbench = $workbench;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->string;
    }

    /**
     * @inheritDoc
     */
    public function getComponentType(): string
    {
        return 'UXON prototype';
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}