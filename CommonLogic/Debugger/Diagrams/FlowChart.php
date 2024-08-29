<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;
use exface\Core\Interfaces\Debug\Diagrams\FlowChartInterface;

//core structure and functionality for any type of flowchart; abstract -> common functionality that can be reused by any specific type of flowchart AND allows subclasses to implement their own specific rendering logic while still reusing the core flowchart
abstract class FlowChart implements FlowChartInterface
{
    // holds all the nodes in the flowchart
    protected $nodes = [];
    // holds all the links between the nodes
    protected $links = [];
    // reference to the last node added
    protected $lastNode = null;
    
    public function startNode(string $title, FlowChartNodeStyle $nodeStyle): FlowChartNode
    {
        $node = new FlowChartNode($title, $nodeStyle);
        $this->nodes[] = $node;
        $this->lastNode = $node;
        return $node; // ultimately returns a newly created 'Node' object
    }
    
    public function continue(string $toTitle, string $linkTitle, FlowChartNodeStyle $nodeStyle): self
    {
        $toNode = new FlowChartNode($toTitle, $nodeStyle);
        $this->nodes[] = $toNode;
        
        if ($this->lastNode !== null) {
            $this->addLink($this->lastNode, $toNode, $linkTitle, $nodeStyle);
        }
        $this->lastNode = $toNode;
        return $this;
    }

    protected function addLink(FlowChartNode $from, FlowChartNode $to, string $title, FlowChartNodeStyle $nodeStyle): self
    {
        $link = new FlowChartLink($from, $to, $title, $nodeStyle);
        $this->links[] = $link;
        return $this;
    }

    public function endNode(string $toTitle, string $linkTitle, FlowChartNodeStyle $nodeStyle): void
    {
        $toNode = new FlowChartNode($toTitle, $nodeStyle);
        $this->nodes[] = $toNode;
        if ($this->lastNode !== null) {
            $this->addLink($this->lastNode, $toNode, $linkTitle, $nodeStyle);
        }
        $this->lastNode = $toNode;
    }

    // rendering of the complete diagram is done by other subclasses such as MermaidFlowChartCustom
    abstract public function render() : string;

    /**
     * Summary of getNodes
     * @return FlowChartNode[]
     */
    public function getNodes() : array
    {
        return $this->nodes;
    }

    /**
     *  
     * @return FlowChartLink[]
     */
    public function getLinks() : array
    {
        return $this->links;
    }
}