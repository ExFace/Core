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
        return $this->getWorkbench()->context()->getScopeUser()->getUsername();
    }

    protected function getIndexColumns()
    {
        return array('requestId', 'id');
    }
}
