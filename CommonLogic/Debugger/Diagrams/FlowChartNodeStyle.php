<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// holds style properties how a node should be visually represented
class FlowChartNodeStyle
{
    public $name;   // name of style

    // TODO what exactly defines rect round etc.?
    public $shape;  // shape of node (e.g., rect, round)
    public $color;  // color of node

    public function __construct(string $name, string $shape, string $color)
    {
        $this->name = $name;
        $this->shape = $shape;
        $this->color = $color;
    }

    // getter methods to retrieve the style properties
    public function getName(): string
    {
        return $this->name;
    }

    public function getShape(): string
    {
        return $this->shape;
    }

    public function getColor(): string
    {
        return $this->color;
    }
}
