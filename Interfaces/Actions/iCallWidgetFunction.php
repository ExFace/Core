<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

interface iCallWidgetFunction extends ActionInterface
{
    /**
     * 
     * @return string|NULL
     */
    public function getFunctionName() : ?string;
    
    /**
     * 
     * @return string[]
     */
    public function getFunctionArguments() : array;

    /**
     * 
     * @param UiPageInterface $page
     * @return WidgetInterface
     */
    public function getWidget(UiPageInterface $page) : WidgetInterface;
}