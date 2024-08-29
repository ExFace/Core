<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// represents link between two nodes, references to the start and end nodes
class FlowChartLink
{
    protected $from;
    protected $to;
    protected $title;
    // public provides flexibility, allowing the style to be adjusted dynamically
    public $style;    // Link style, instance of FlowChartLinkStyle

    // TODO continue: public function __construct(FlowChartNode $from, FlowChartNode $to, string $title, FlowChartLinkStyle $style)
    public function __construct(FlowChartNode $from, FlowChartNode $to, string $title, FlowChartLinkStyle $style)
    {
        $this->from = $from;
        $this->to = $to;
        $this->title = $title;
        $this->style = $style;
    }

    // returns starting node
    public function getNodeFrom(): FlowChartNode
    {
        return $this->from;
    }

    // returns ending node
    public function getNodeTo(): FlowChartNode
    {
        return $this->to;
    }

    // returns title of the link
    public function getTitle(): string
    {
        return $this->title;
    }

    // returns style associated with the link
    public function getStyle(): FlowChartLinkStyle
    {
        return $this->style;
    }
}
