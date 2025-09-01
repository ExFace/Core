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
 *     "autosuggest_filter_attribute": "id",
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
    
    protected function setAutosuggestObjectAlias(string $alias) : TextMention
    {
        $this->autosuggestObjectAlias = $alias;
        return $this;
    }
    
    public function getAutosuggestObject() : MetaObjectInterface
    {
        return MetaObjectFactory::createFromString($this->getWorkbench(), $this->autosuggestObjectAlias);
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
            'action' => $this->getAutosuggestActionUxon()->toArray()
        ]));
        return $btn;
    }
    
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
    
    protected function setAutosuggestFilterAttributeAlias(string $alias) : TextMention
    {
        $this->autosuggestFilterAttributeAlias = $alias;
        return $this;
    }
    
    public function getAutosuggestFilterAttributeAlias() : string
    {
        return $this->autosuggestFilterAttributeAlias;
    }
}