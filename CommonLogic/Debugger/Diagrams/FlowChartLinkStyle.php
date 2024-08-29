<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// holds style properties how a link should be visually represented
class FlowChartLinkStyle
{
    public $name;   // name of style
    public $stroke; // stroke color
    public $arrow;  // arrow type
    public $weight; // weight or thickness of the link

    public function __construct(string $name, string $stroke, string $arrow, string $weight)
    {
        $this->name = $name;
        $this->stroke = $stroke;
        $this->arrow = $arrow;
        $this->weight = $weight;
    }

    // getter methods to retrieve the style properties
    public function getName(): string
    {
        return $this->name;
    }

    public function getStroke(): string
    {
        return $this->stroke;
    }

    public function getArrow(): string
    {
        return $this->arrow;
    }

    public function getWeight(): string
    {
        return $this->weight;
    }
}
