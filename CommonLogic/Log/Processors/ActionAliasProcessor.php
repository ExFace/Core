<?php

namespace exface\Core\CommonLogic\Log\Processors;


class ActionAliasProcessor extends AbstractColumnPositionProcessor
{
    protected function getContentId()
    {
        return 'actionAlias';
    }

    protected function getContent()
    {
        $currentAction = $this->getCurrentAction();
        if ($currentAction) {
            return $currentAction->getAliasWithNamespace();
        }

        return "";
    }

    protected function getIndexColumns()
    {
        return array('actionAlias', 'requestId', 'id');
    }

    protected function getCurrentAction()
    {
        $wb = $this->getWorkbench();
        if (!$wb)
            return false;

        $ctx = $wb->context();
        if (!$ctx)
            return false;

        $sw = $ctx->getScopeWindow();
        if (!$sw)
            return false;

        $aCtx = $sw->getActionContext();
        if (!$aCtx)
            return false;

        return $aCtx->getCurrentAction();
    }
}
