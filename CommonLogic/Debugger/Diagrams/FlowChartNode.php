<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// display a node in the flowchart
class FlowChartNode
{
    protected $title; // Node title
    protected $style; // Node style, instance of FlowChartNodeStyle

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    // returns title of node
    public function getTitle(): string
    {
        return $this->title;
    }

    // returns style of node
    public function getStyle(): FlowChartNodeStyle
    {
        return $this->style;
    }
}
