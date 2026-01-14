<?php

namespace exface\Core\CommonLogic\Utils\FiniteStateMachine\SimpleParser;

use exface\Core\CommonLogic\Utils\FiniteStateMachine\AbstractStateMachine;

class SimpleParser extends AbstractStateMachine
{
    public function process(string $data = null) : ?array
    {
        if($data === null || $data === '') {
            return [];
        }

        $this->dataRaw = $data;
        $this->data = new SimpleParserData($data);
        return parent::process()->getOutputAll();
    }

    protected function getDataForProcessing(): SimpleParserData
    {
        return $this->data;
    }

    protected function getInput($data): int
    {
        return $data->getCursor();
    }
    
    public function getOutput() : array
    {
        return $this->data->getOutputAll();
    }
    
    public function getDebugInfo() : array
    {
        return array_merge(parent::getDebugInfo(), [
            'Token' => $this->data->getToken($this->data->getCursor()),
            'Stack' => $this->data->getStackInfo(),
            'Buffer' => $this->data->getOutputAll()
        ]);
    }
}