<?php
namespace exface\Core\CommonLogic\Debugger\Diagrams;

use exface\Core\Interfaces\Diagrams\FlowChartInterface;
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
        
       // Ensure we add all nodes to the graph first
       $nodeMap = [];
       foreach ($flowChart->getNodes() as $node) {
           $nodeId = $this->getNodeId($node);
           $graphNode = new Node($graph, $nodeId);
           $graphNode->setText($node->getTitle());
           $nodeMap[$nodeId] = $graphNode;
           $graph->addNode($graphNode);
       }

       // Then add all links between nodes
       foreach ($flowChart->getLinks() as $link) {
           $fromNode = $nodeMap[$this->getNodeId($link->getNodeFrom())];
           $toNode = $nodeMap[$this->getNodeId($link->getNodeTo())];
           $graphLink = new Link($fromNode, $toNode, $link->getTitle());
           $graph->addLink($graphLink);
       }
    }

    protected function getNodeId(FlowChartNode $node) :string
    {
        return str_replace(' ', '_', $node->getTitle());
    }
}