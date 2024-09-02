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
        $graph = new Graph(['direction' => Graph::LEFT_RIGHT]);
        
        foreach ($flowChart->getLinks() as $link) {
            // getNodeId: generates a valid Mermaid.js ID for each node
            $nodeFromId = new Node($this->getNodeId($link->getNodeFrom()), $link->getNodeFrom()->getTitle());
            $nodeToId = new Node($this->getNodeId($link->getNodeTo()), $link->getNodeTo()->getTitle());
            $graph->addNode($nodeFromId);
            $graph->addNode($nodeToId);
            $graphLink = new Link($nodeFromId, $nodeToId, $link->getTitle());
            $graph->addLink($graphLink);
        }
        return $graph->render();
    }

    protected function getNodeId(FlowChartNode $node) :string
    {
        return str_replace(' ', '_', $node->getTitle());
    }
}