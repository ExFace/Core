<?php

namespace exface\Core\Mutations;

use exface\Core\Interfaces\Mutations\MutationTargetInterface;

class MetaObjectUidMutationTarget implements MutationTargetInterface
{
    private $selector = null;
    private $uid = null;
    public function __construct(string $objectAlias, string $instanceUid)
    {
        $this->selector = $objectAlias;
        $this->uid = $instanceUid;
    }

    public function getUid() : string
    {
        return $this->uid;
    }

    public function getObjectAliasWithNamespace() : string
    {
        return $this->selector;
    }

    public function getTargetKey() : string
    {
        return $this->selector;
    }

    public function getTargetValue() : string
    {
        return $this->uid;
    }

    public function __toString() : string
    {
        return $this->selector . ':' . $this->uid;
    }
}