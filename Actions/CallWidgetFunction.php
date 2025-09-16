<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Actions\iCallWidgetFunction;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Button;
use exface\Core\Factories\WidgetLinkFactory;

/**
 * Activates a function of a select widget (see available functions in widget docs).
 *  
 * @author andrej.kabachnik
 *
 */
class CallWidgetFunction extends AbstractAction implements iCallWidgetFunction
{
    private ?string $widgetId = null;
    private ?string $widgetIdSpace = null;
    private ?string $funcName = null;
    private array $funcArgs = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::MOUSE_POINTER);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        return ResultFactory::createEmptyResult($task);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallWidgetFunction::getFunctionName()
     */
    public function getFunctionName(): ?string
    {
        return $this->funcName;
    }
    
    /**
     * The name of the widget function to call (leave empty to call default function)
     * 
     * @uxon-property function
     * @uxon-type string
     * 
     * @param string $name
     * @return CallWidgetFunction
     */
    public function setFunction(string $name) : CallWidgetFunction
    {
        $name = trim($name);
        if ($name === null || $name === '') {
            $this->funcName = null;
            return $this;
        }
        $this->funcName = StringDataType::substringBefore($name, '(', $name);
        if ($this->funcName !== $name) {
            $argStr = StringDataType::substringAfter($name, '(');
            $argStr = mb_substr($argStr, 0, -1);
            $this->funcArgs = explode(',', $argStr);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallWidgetFunction::getFunctionArguments()
     */
    public function getFunctionArguments() : array
    {
        return $this->funcArgs;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCallWidgetFunction::getWidget()
     */
    public function getWidget(UiPageInterface $page): WidgetInterface
    {
        $id = $this->getWidgetId();
        if (mb_substr($this->widgetId, 0, 1) === '~') {
            if ($this->isDefinedInWidget()){
                $link = WidgetLinkFactory::createFromWidget($this->getWidgetDefinedIn(), $this->widgetId);
                return $link->getTargetWidget();
            }
        }

        // If the widget id in the link does not have an id space and the action is called from a button, assume,
        // that we are in the id space of the button.
        $idSpaceOfLink = StringDataType::substringBefore($id, UiPage::WIDGET_ID_SPACE_SEPARATOR, null, false, true);
        if ($idSpaceOfLink === null && ($this->getWidgetIdSpace() !== null || $this->isDefinedInWidget())) {
            $idSpaceOfAction = $this->getWidgetIdSpace() ?? $this->getWidgetDefinedIn()->getIdSpace();
            // Don't add the id space if the widget id in the action is a path already and it starts with the id space
            // TODO we really need a way to tell, if an id string is a path or a manual id. We are only guessing
            // all the time!
            switch (true) {
                // No id space to set - forget it!
                case $idSpaceOfAction === null:
                    break;
                // Root id space - add it explicitly to speed up searching
                case $idSpaceOfAction === '' || $idSpaceOfAction === $page->getWidgetIdSpaceSeparator():
                // Otherwise add the id space unless the id actually starts with it
                case false === StringDataType::startsWith($id, $idSpaceOfAction):
                    $id = $idSpaceOfAction . $page->getWidgetIdSpaceSeparator() . $id;
                    break;
            }
        }
        return $page->getWidget($id); 
    }

    /**
     * 
     * @return string
     */
    protected function getWidgetId() : string
    {
        return $this->widgetId;
    }
    
    /**
     * The ID of the target widget.
     * 
     * NOTE:the `widget_id` should either be in the same id space, as the trigger button, or an id space must be included!
     * 
     * @uxon-property widget_id
     * @uxon-type uxon:$..id
     * 
     * @param string $value
     * @return CallWidgetFunction
     */
    public function setWidgetId(string $value) : CallWidgetFunction
    {
        $this->widgetId = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getName()
     */
    public function getName()
    {
        $name = parent::getName();
        if ($name == $this->translate('NAME') && $this->isButtonPress()) {
            $name = $this->getWidget($this->getWidgetDefinedIn()->getPage())->getCaption();
        }
        return $name;
    }
    
    /**
     * Returns if the call widget funtion is a button press
     * 
     * @return bool
     */
    protected function isButtonPress() : bool
    {
        return  $this->getFunctionName() == Button::FUNCTION_PRESS
                && $this->isDefinedInWidget()
                && $this->getWidget($this->getWidgetDefinedIn()->getPage()) instanceof Button;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getHint()
     */
    public function getHint() : ?string
    {
        $hint = parent::getHint();
        if ($hint === null && $this->isButtonPress()) {
            $hint = $this->getWidget($this->getWidgetDefinedIn()->getPage())->getHint();
        }
        return $hint;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getIcon()
     */
    public function getIcon() : ?string
    {
        $icon = parent::getIcon();
        if ($icon === Icons::MOUSE_POINTER && $this->isButtonPress()) {
            $icon = $this->getWidget($this->getWidgetDefinedIn()->getPage())->getIcon();
        }
        return $icon;
    }
    
    protected function getWidgetIdSpace() : ?string
    {
        return $this->widgetIdSpace;
    }

    /**
     * The id space to search for the widget_id if it does not have a space explicitly defined
     * 
     * If not set, the id space of the button triggering the action will be used automatically.
     * 
     * To use the root id space of the page, set this property to an empty string or an underscore
     * (`_`).
     * 
     * @param string $value
     * @return $this
     */
    protected function setWidgetIdSpace(string $value) : CallWidgetFunction
    {
        $this->widgetIdSpace = $value;
        return $this;
    }
}