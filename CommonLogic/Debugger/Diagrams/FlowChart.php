<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;
use exface\Core\Interfaces\Diagrams\FlowChartInterface;

//core structure and functionality for any type of flowchart; abstract -> common functionality that can be reused by any specific type of flowchart AND allows subclasses to implement their own specific rendering logic while still reusing the core flowchart
abstract class FlowChart implements FlowChartInterface
{
    // holds all the nodes in the flowchart
    protected $nodes = [];
    // holds all the links between the nodes
    protected $links = [];
    // reference to the last node added
    protected $lastNode = null;
    
    public function startNode(string $title): FlowChartNode
    {
        $node = new FlowChartNode($title);
        $this->nodes[] = $node;
        $this->lastNode = $node;
        return $node; // ultimately returns a newly created 'Node' object
    }
    
    public function continue(string $toTitle, string $linkTitle): self
    {
        $toNode = new FlowChartNode($toTitle);
        $this->nodes[] = $toNode;
        
        if ($this->lastNode !== null) {
            $this->addLink($this->lastNode, $toNode, $linkTitle);
        }
        $this->lastNode = $toNode;
        return $this;
    }

    protected function addLink(FlowChartNode $from, FlowChartNode $to, string $title): self
    {
        $link = new FlowChartLink($from, $to, $title);
        $this->links[] = $link;
        return $this;
    }

    public function endNode(string $toTitle, string $linkTitle): void
    {
        $toNode = new FlowChartNode($toTitle);
        $this->nodes[] = $toNode;
        if ($this->lastNode !== null) {
            $this->addLink($this->lastNode, $toNode, $linkTitle);
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