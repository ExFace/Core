<?php

namespace exface\Core\CommonLogic\Log\Processors;


class RequestIdProcessor extends AbstractColumnPositionProcessor
{
    protected function getContentId()
    {
        return 'requestid';
    }

    protected function getContent()
    {
        return $this->getWorkbench()->getContext()->getScopeRequest()->getScopeId();
    }

    protected function getIndexColumns()
    {
        return array('id');
    }
}
