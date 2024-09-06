<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Diagrams\FlowInterface;
use JBZoo\MermaidPHP\Node;

//core structure and functionality for any type of flowchart
//abstract -> common functionality that can be reused by any specific type of flowchart AND allows subclasses to implement their own specific rendering logic while still reusing the core flowchart
abstract class Flow implements FlowInterface
{
    // holds all the nodes in the flowchart
    protected $nodes = [];
    // holds all the links between the nodes
    protected $links = [];
    // reference to the last node added
    protected $lastNode = null;
    
    public function addNodeStart($nodeOrTitle, $stringOrStyle = null): FlowNode
    {
        $node = $this->addNode($nodeOrTitle, $stringOrStyle);
        $this->setNodeLast($node);
        return $node;
    }

    /**
     * 
     * @param string|FlowNode $nodeOrTitle
     * @param string|object $linkTitleOrObject
     * @throws \exface\Core\Exceptions\InvalidArgumentException
     * @return \Exface\Core\CommonLogic\Debugger\Diagrams\Flow
     */
    public function continue($nodeOrTitle, $linkTitleOrObject, $stringOrStyle = null): self
    {
        $toNode = $this->addNode($nodeOrTitle, $stringOrStyle);
        
        if (null !== $fromNode = $this->getNodeLast()) {
            $this->addLink($fromNode, $toNode, FlowLink::getTitleForAnything($linkTitleOrObject));
        }
        $this->setNodeLast($toNode);
        return $this;
    }
    
    /**
     * Summary of addNodeEnd
     * @param mixed $nodeOrTitle
     * @param mixed $linkTitleOrObject
     * @param mixed $stringOrStyle
     * @return \Exface\Core\CommonLogic\Debugger\Diagrams\Flow
     */
    public function addNodeEnd($nodeOrTitle, $linkTitleOrObject, $stringOrStyle = null): self
    {
        $toNode = $this->addNode($nodeOrTitle, $stringOrStyle);
        
        if (null !== $fromNode = $this->getNodeLast()) {
            $this->addLink($fromNode, $toNode, FlowLink::getTitleForAnything($linkTitleOrObject));
    }
    $this->setNodeLast($toNode);
    return $this;
}

    protected function getNodeLast() : ?FlowNode
    {
        return $this->lastNode ?? ($this->nodes[array_key_first($this->nodes)] ?? null);
    }

    protected function setNodeLast(FlowNode $node): Flow
    {
        $this->lastNode = $node;
        return $this;
    }

    /**
     * 
     * @param \Exface\Core\CommonLogic\Debugger\Diagrams\FlowNode $from
     * @param \Exface\Core\CommonLogic\Debugger\Diagrams\FlowNode $to
     * @param string $title
     * @return \Exface\Core\CommonLogic\Debugger\Diagrams\Flow
     */
    public function addLink($from, $to, $titleOrObject): self
    {
        switch (true) {
            case $from instanceof FlowNode:
                $fromNode = $from;
                break;
            case is_string($from):
                $fromNode = $this->findNode($from);
                if ($fromNode === null) {
                    throw new UnexpectedValueException('TODO');
                }
                break;
            default: throw new InvalidArgumentException('TODO');
        }

        switch (true) {
            case $to instanceof FlowNode:
                $toNode = $to;
                break;
            case is_string($to):
                $toNode = $this->findNode($to);
                if ($toNode === null) {
                    throw new UnexpectedValueException('TODO');
                }
                break;
            default: throw new InvalidArgumentException('TODO');
        }
        $link = new FlowLink($fromNode, $toNode, FlowLink::getTitleForAnything($titleOrObject));
        $this->links[] = $link;
        return $this;
    }

    public function addNode($nodeOrTitle, $stringOrStyle = null) : FlowNode
    {
        switch (true) {
            case $nodeOrTitle instanceof FlowNode:
                $toNode = $nodeOrTitle;
                break;
            case is_string($nodeOrTitle):
                $toNode = new FlowNode($nodeOrTitle, $stringOrStyle);
                break;
            default:
                throw new InvalidArgumentException('Cannot continue flowchart: expecting string or node instance, received ' . get_class($nodeOrTitle));
        }
        $this->nodes[] = $toNode;
        return $toNode;
    }

    /**
     * 
     * @param mixed $title
     * @return FlowNode|null
     */
    protected function findNode($title) : ?FlowNode
    {
        foreach ($this->nodes as $node) {
            if ($node->getTitle() === $title) {
                return $node;
            }
        }
        return null;
    }

    // rendering of the complete diagram is done by other subclasses such as MermaidFlowChartCustom
    abstract public function render() : string;

    /**
     * Summary of getNodes
     * @return FlowNode[]
     */
    public function getNodes() : array
    {
        return $this->nodes;
    }

    /**
     *  
     * @return FlowLink[]
     */
    public function getLinks() : array
    {
        return $this->links;
    }
}