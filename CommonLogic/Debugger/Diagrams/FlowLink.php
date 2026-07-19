<?php

namespace Exface\Core\CommonLogic\Debugger\Diagrams;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

// represents link between two nodes, references to the start and end nodes
class FlowLink
{
    protected $from;
    protected $to;
    protected $title;

    public function __construct(FlowNode $from, FlowNode $to, string $title)
    {
        $this->from = $from;
        $this->to = $to;
        $this->title = $title;
    }

    // returns starting node
    public function getNodeFrom(): FlowNode
    {
        return $this->from;
    }

    // returns ending node
    public function getNodeTo(): FlowNode
    {
        return $this->to;
    }

    // returns title of the link
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * 
     * @param string|object $something
     * @return string
     */
    public static function getTitleForAnything($something) : string
    {
        $str = '';
        switch (true) {
            case $something instanceof DataSheetInterface:
                $dataSheet = $something;
                $obj = $dataSheet->getMetaObject()->getAliasWithNamespace();
                $rows = $dataSheet->countRows();
                $cols = $dataSheet->getColumns()->count();
                $filters = $dataSheet->getFilters()->countConditions() + $dataSheet->getFilters()->countNestedGroups();
                if (empty($rows) && empty($cols) && empty($filters)) {
                    return "\"{$obj}\nblank\"";
                }
                $str = "\"{$obj}\n{$rows} row(s), {$cols} col(s), {$filters} filter(s)\"";
                break;
            case is_string($something):
                $str = $something;
                break;
            case $something instanceof \Stringable:
                $str = $something->__toString();
                break;
            default:
            $str =  get_class($something);
                break;
        }
        return $str;
    }
}
