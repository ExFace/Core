<?php
namespace exface\Core\CommonLogic\Debugger\Diagrams;

use exface\Core\Interfaces\Debug\Diagrams\FlowChartInterface;
use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;

// renders the flowchart in Mermaid.js syntax
class MermaidFlowChart
{
    // takes the FlowChartInterface object and converts nodes and links into Mermaid.js
    public function render(FlowChartInterface $flowChart) : string
    {
        $graph = new Graph();
        
        foreach ($flowChart->getLinks() as $link) {
            // getNodeId: generates a valid Mermaid.js ID for each node
            $graphNode1 = new Node($graph, $this->getNodeId($link->getNodeFrom()));
            $graphNode2 = new Node($graph, $this->getNodeId($link->getNodeTo())); 
            $graph->addNode($graphNode1);
            $graph->addNode($graphNode2);
            $graphLink = new Link($graphNode1, $graphNode2, $link->getTitle());
            $graph->addLink($graphLink);
        }
        return $graph->render();
    }

    protected function getNodeId(FlowChartNode $node) :string
    {
        return str_replace(' ', '_', $node->getTitle());
    }
}