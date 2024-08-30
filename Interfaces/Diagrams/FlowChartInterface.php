<?php

namespace Exface\Core\Interfaces\Diagrams;

//
interface FlowChartInterface
{
    public function getLinks(): array;

    public function getNodes(): array;
}
