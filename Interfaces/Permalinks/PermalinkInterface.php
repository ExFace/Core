<?php
namespace exface\Core\Interfaces\Permalinks;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;

interface PermalinkInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function getRedirect() : string;

    public function getLink() : string;

    public function withUrl(string $urlPath) : PermalinkInterface;
}