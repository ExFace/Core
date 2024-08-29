<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;

// provides basic implementation of the render() method
class ExtendFlowChart extends FlowChart
{
    public function render(): string
    {
        return 'Rendered FlowChart';
    }
}