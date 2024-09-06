<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// display a node in the flowchart
class FlowNode
{
    const STYLE_SQUARE = 'square';

    const STYLE_ROUND = 'round';

    const STYLE_PROCESS = 'process';

    const STYLE_DATA = 'data';

    const STYLE_ERROR = 'error';

    protected $title; // Node title
    protected $style; // Node style, instance of FlowChartNodeStyle

    public function __construct(string $title = null, $style = null)
    {
        $this->title = $title;
        $this->style = $style;
    }

    // returns title of node
    public function getTitle(): string
    {
        return $this->title;
    }

    // returns style of node
    public function getStyle(): ?FlowNodeStyle
    {
        if (is_string($this->style)) {
            $this->style = $this->getStyleFromPreset($this->style);
        }
        return $this->style;
    }

    protected function getStyleFromPreset(string $preset): ?FlowNodeStyle
    {
        switch ($preset) {
            case self::STYLE_SQUARE:
            case self::STYLE_DATA:
                $style = new FlowNodeStyle($preset, FlowNodeStyle::SHAPE_SQUARE);
                break;
            case self::STYLE_ROUND:
            case self::STYLE_PROCESS:
                $style = new FlowNodeStyle($preset, FlowNodeStyle::SHAPE_ROUND);
                break;
            case self::STYLE_ERROR:
                $style = new FlowNodeStyle($preset, FlowNodeStyle::SHAPE_SQUARE, 'red');
        }
        return $style;
    }
}
