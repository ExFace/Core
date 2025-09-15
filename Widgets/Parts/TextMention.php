<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\InputCombo;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;
use exface\Core\Widgets\Traits\iHaveIconTrait;

/**
 * Configuration for a mention tag or hash tag in a text editor
 * 
 * ```
 *  {
 *     "name": "Ticket",
 *     "regex": "/#\d+/"
 *     "show_toolbar_button": false,
 *     "autosuggest_object_alias": "axenox.DevMan.ticket",
 *     "autosuggest_filter_attribute_alias": "id",
 *     "click_action": {
 *          "alias": "exface.Core.GoToPage",
 *          "page_alias": "axenox.DevMan.tickets"
 *     }
 *  }
 * 
 * ```
 * 
 * TODO in the ToastUIEditorTrait:
 * 
 * ```
 *  $tuiRegex = '/\[(' . RegularExpressionDataType::stripDelimiters($this->getWidget()->getMentions()[0]->getRegex()) . ')\]\((.*?)\)/';
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class TextMention extends TextStencil
{    
    private $autosuggestObjectAlias = null;
    private $autosuggestFilterAttributeAlias = null;
    private $autosuggestActivationCharacter = null;
    private $regex = null;


    /**
     * TODO SR: Description
     * TODO SR: Find the right uxon-type
     *
     * @uxon-property autosuggest_object_alias
     * @uxon-type string
     *
     * @param string $alias
     * @return $this
     */
    protected function setAutosuggestObjectAlias(string $alias) : TextMention
    {
        $this->autosuggestObjectAlias = $alias;
        return $this;
    }
    
    public function getAutosuggestObject() : MetaObjectInterface
    {
        return MetaObjectFactory::createFromString($this->getWorkbench(), $this->autosuggestObjectAlias);
    }

    /**
     * TODO SR: Write a description
     *
     * @uxon-property autosuggest_filter_attribute_alias
     * @uxon-type string
     *
     * @param string $alias
     * @return $this
     */
    protected function setAutosuggestFilterAttributeAlias(string $alias) : TextMention
    {
        $this->autosuggestFilterAttributeAlias = $alias;
        return $this;
    }

    public function getAutosuggestFilterAttributeAlias() : string
    {
        return $this->autosuggestFilterAttributeAlias;
    }

    /**
     * This character activates the mention widget, if typed into the editor.
     *
     * @uxon-property autosuggest_activation_character
     * @uxon-type string
     * @uxon-template @
     *
     * @param string $autosuggestActivationCharacter
     * @return TextMention
     */
    protected function setAutosuggestActivationCharacter(string $autosuggestActivationCharacter) : TextMention
    {
        $this->autosuggestActivationCharacter = $autosuggestActivationCharacter;
        return $this;
    }

    /**
     * @return string
     */
    public function getAutosuggestActivationCharacter() : string
    {
        return $this->autosuggestActivationCharacter;
    }

    /**
     * This regular expression is used to identify the searching string of mentions
     * Example:
     * - with the activation_character = "@" and regex = ".+": "@Max Mustermann"
     * - with the activation_character = "#" and regex = "\d+": "#12345"
     *
     * @uxon-property regex
     * @uxon-type string
     * @uxon-template .+
     *
     * @param string $regex
     * @return $this
     */
    protected function setRegex(string $regex) : TextMention
    {
        $this->regex = $regex;
        return $this;
    }

    public function getRegex() : string
    {
        return $this->regex;
    }

    public function getAutosuggestActionUxon() : UxonObject
    {
        $autosuggestObj = $this->getAutosuggestObject();
        $uxon = new UxonObject([
            'alias' => 'exface.Core.ReadData',
            'object_alias' => $this->autosuggestObjectAlias,
            'columns' => []
        ]);
        if ($autosuggestObj->hasUidAttribute()) {
            $uxon->appendToProperty('columns', new UxonObject(['attribute_alias' => $autosuggestObj->getUidAttributeAlias()]));
        } else {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid mention configuroation TODO!');
        }
        if ($autosuggestObj->hasLabelAttribute()) {
            $uxon->appendToProperty('columns', new UxonObject(['attribute_alias' => $autosuggestObj->getLabelAttributeAlias()]));
        } else {
            $uxon->appendToProperty('columns', new UxonObject(['attribute_alias' => $autosuggestObj->getUidAttributeAlias()]));
        }
        return $uxon;
    }
    
    public function getAutosuggestButton() : iTriggerAction
    {
        $btn = WidgetFactory::createFromUxonInParent($this->getWidget(), new UxonObject([
            'widget_type' => 'Button',
            'action' => $this->getAutosuggestActionUxon()->toArray()
        ]));
        return $btn;
    }

    //TODO SR: Check if this is still needed:
    public function getAutosuggestWidget() : InputCombo
    {
        $autosuggestObj = $this->getAutosuggestObject();
        $uxon = new UxonObject([
            'widget_type' => 'InputComboTable',
            'table_object_alias' => $this->autosuggestObjectAlias
        ]);
        if ($autosuggestObj->hasUidAttribute()) {
            $uxon->setProperty('value_attribute_alias', $autosuggestObj->getUidAttributeAlias());
        } else {
            throw new WidgetConfigurationError($this->getWidget(), 'Invalid mention configuroation TODO!');
        }
        if ($autosuggestObj->hasLabelAttribute()) {
            $uxon->setProperty('text_attribute_alias', $autosuggestObj->getLabelAttributeAlias());
        } else {
            $uxon->setProperty('text_attribute_alias', $autosuggestObj->getUidAttributeAlias());
        }
        
        $widget = WidgetFactory::createFromUxonInParent($this->getWidget(), $uxon);
        return $widget;
    }
}