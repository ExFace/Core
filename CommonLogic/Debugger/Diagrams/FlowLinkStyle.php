<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// This class is not in use / applied - starting point for implementing e.g. dotted lines
// holds style properties how a link should be visually represented
class FlowLinkStyle
{
    private $name;   // name of style
    private $color; // get color
    private $arrow;  // arrow type
    private $weight; // weight or thickness of the link

    public function __construct(string $name, string $stroke, string $arrow = null, string $weight)
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
        return $this->color;
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
