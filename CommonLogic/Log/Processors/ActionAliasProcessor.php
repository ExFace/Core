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
        $currentAction = $this->getWorkbench()->context()->getScopeWindow()->getActionContext()->getCurrentAction();
        if ($currentAction) {
            return $currentAction->getAliasWithNamespace();
        }

        return "";
    }

    protected function getIndexColumns()
    {
        return array('actionAlias', 'requestId', 'id');
    }
}
