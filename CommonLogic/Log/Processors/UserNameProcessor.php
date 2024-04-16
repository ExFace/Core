<?php

namespace exface\Core\CommonLogic\Log\Processors;


class UserNameProcessor extends AbstractColumnPositionProcessor
{
    protected function getContentId()
    {
        return 'userName';
    }

    protected function getContent()
    {
        if ($this->getWorkbench()->isStarted()) {
            return $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername();
        } 
        return null;
    }

    protected function getIndexColumns()
    {
        return array('requestId', 'id');
    }
}
