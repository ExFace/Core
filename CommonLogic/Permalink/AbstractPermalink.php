<?php

namespace exface\Core\CommonLogic\Permalink;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Permalinks\DialogPermalink;

abstract class AbstractPermalink implements PermalinkInterface
{
    use ImportUxonObjectTrait;

    private $uxon = null;
    private $workbench;
    private $name = null;
    private $aliasWithNamespace = null;

    public function __construct(WorkbenchInterface $workbench, ?UxonObject $configUxon = null)
    {
        $this->workbench = $workbench;
        $this->uxon = $configUxon;
        if ($configUxon !== null) {
            $this->importUxonObject($configUxon);
        }
    }

    public function withUrl(string $urlPath) : PermalinkInterface
    {
        $clone = clone $this;
        return $clone->parse($urlPath);
    }

    protected abstract function parse(string $urlPath) : PermalinkInterface;

    protected function setName(string $name) : DialogPermalink
    {
        $this->name = $name;
        return $this;
    }

    protected function setAlias(string $aliasWithNamespace) : PermalinkInterface
    {
        $this->aliasWithNamespace = $aliasWithNamespace;
        return $this;
    }

    public function getAliasWithNamespace() : string
    {
        return $this;
    }

    public function getWorkbench()
    {
        $this->workbench;
    }

    /**
     * @inheritDoc
     */
    public function exportUxonObject()
    {
        return $this->uxon ?? new UxonObject();
    }
}