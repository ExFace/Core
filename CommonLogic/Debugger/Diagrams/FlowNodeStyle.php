<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// holds style properties how a node should be visually represented
class FlowNodeStyle
{
    const SHAPE_SQUARE = 'square';
    const SHAPE_ROUND = 'round';

    public $name;   // name of style
    public $shape;  // shape of node (e.g. square, round)
    public $color;  // color of node

    public function __construct(string $name, string $shape = null, string $color = null)
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

    public function getShape(): ?string
    {
        return $this->shape;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }
}
