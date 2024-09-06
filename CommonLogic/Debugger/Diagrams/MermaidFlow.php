<?php
namespace exface\Core\CommonLogic\Debugger\Diagrams;

use exface\Core\Interfaces\Diagrams\FlowInterface;
use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;

// renders the flowchart in Mermaid.js syntax
class MermaidFlow
{
    // takes the FlowChartInterface object and converts nodes and links into Mermaid.js
    public function render(FlowInterface $flowChart) : string
    {
        $graph = new Graph(['direction' => Graph::LEFT_RIGHT]);
        
        foreach ($flowChart->getLinks() as $link) {
            $from = $this->addNode($link->getNodeFrom(), $graph);
            $to = $this->addNode($link->getNodeTo(), $graph);
            $graphLink = new Link($from, $to, $link->getTitle());
            $graph->addLink($graphLink);
        }
        return $graph->render();
    }

    protected function addNode(FlowNode $node, Graph $graph) : Node
    {
        $graphNode = new Node($this->getNodeId($node), $node->getTitle(), $this->getNodeForm($node->getStyle()));
        $graph->addNode($graphNode); 
        if ($style = $this->getNodeStyle($node->getStyle())) {
            $graph->addStyle('style ' . $this->getNodeId($node) . ' ' . $style);
        }
        return $graphNode; 
    }

    protected function getNodeStyle(FlowNodeStyle $nodeStyle = null) : string
    {
        if ($nodeStyle === null) {
            return '';
        }
        switch (true) {
            case $nodeStyle->getColor() === 'red':
                return 'fill:#ef4444,stroke:#ef4444';
        }
        return '';
    }

    protected function getNodeForm(FlowNodeStyle $style = null) : string
    {
        if ($style === null) {
            return Node::ROUND;
        }
        switch ($style->getShape()) {
            case FlowNodeStyle::SHAPE_SQUARE: 
                $form = Node::SQUARE;
                break;
            case FlowNodeStyle::SHAPE_ROUND: 
                $form = Node::ROUND;
                break;
            default:
                $form = Node::ROUND;
        }
        return $form;
    }

    protected function getNodeId(FlowNode $node) :string
    {
        return str_replace(' ', '_', $node->getTitle());
    }
}