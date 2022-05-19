<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

interface iCallWidgetFunction extends ActionInterface
{
    public function getFunctionName() : ?string;

    public function getWidget(UiPageInterface $page) : WidgetInterface;
}