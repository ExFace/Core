<?php
namespace exface\Core\CommonLogic\Debugger\Diagrams;

// provides custom implementation for rendering a flowchart
class MermaidFlowChartCustom extends FlowChart
{
    // iterating through nodes and links, formatting them into Mermaid.js syntax
    public function render() : string
    {
        $output = "flowchart LR\n";
        foreach ($this->nodes as $node) {
            $output .= $this->renderNode($node) . "\n";
        }
        foreach ($this->links as $link) {
            $output .= $this->renderLink($link) . "\n";
        }
        return $output;
    }

    // converting a node into a Mermaid.js formatted string
    public function renderNode(FlowChartNode $node) : string
    {
        return "{$this->getNodeId($node)}[{$this->escapeTitle($node->getTitle())}]";
    }

    public function renderLink(FlowChartLink $link) : string
    {
        // TODO find a new way to display style settings such as dotted etc.
        $arrowType = ($link->style->name === "dotted") ?"-.->":"-->";
        return "{$link->getNodeFrom()->getTitle()} {$arrowType}|{$link->getTitle()}| {$link->getNodeTo()->getTitle()}";
    }

    // generates a valid ID for each node
    protected function getNodeId(FlowChartNode $node) :string
    {
        return str_replace(' ', '_', $node->getTitle());
    }

    // special characters in node titles are properly escaped
    protected function escapeTitle(string $title) : string
    {
        return addslashes($title);
    }
}