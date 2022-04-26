<?php
namespace exface\Core\Interfaces;

interface iCanBeCopied extends WorkbenchDependantInterface
{

    /**
     * Copies the current instance including sub-elements (deep-copy)
     * 
     * @return self
     */
    public function copy() : self;
}