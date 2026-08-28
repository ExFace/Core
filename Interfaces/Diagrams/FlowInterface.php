<?php

namespace Exface\Core\Interfaces\Diagrams;

//
interface FlowInterface
{
    public function getLinks(): array;

    public function getNodes(): array;
}
