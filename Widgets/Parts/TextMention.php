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
 * Mention Widget Dokumentation:
 * For example, DevMan Tickets (autosuggest_object_alias) mention fetch can be activated by typing 
 * the "#" character (tag_prefix) into the editor (see the example uxon below).
 * 
 * If the input after the tag prefix matches the given regex (tag_text_regex),
 * it will fetch 10 (autosuggest_max_number_of_rows) filtered rows with the columns "LABEL" (autosuggest_filter_attribute_alias) and "id" (tag_text_attribute) 
 * and present a dropdown with the values of the "LABEL" field.
 * 
 * If the user selects one of the options or presses the space bar when only one row is shown, 
 * a tag containing the tag prefix and the 'id' field will appear in the editor, e.g. '#id', in the defined tag colour.
 * 
 * If the input after the tag prefix matches one of the mentions, but the table fetch does not succeed, 
 * the input will still be transformed into a mention tag but without any press actions included.
 * 
 * If the "tag_text_attribute" is not given, the results of the "autosuggest_filter_attribute_alias" will be taken and pasted to the text editor instead.
 * 
 * ```
 *  {
 *      "caption": "devman tickets mention",
 *      "hint": "this devman mention widget filters the DevMan.ticket table with LABEL field and writes the ID of it as an \"#id\" tag. ",
 *      "tag_prefix": "#",
 *      "autosuggest_max_number_of_rows": 10,
 *      "tag_color": "green",
 *      "tag_text_regex": "\\d*",
 *      "autosuggest_object_alias": "axenox.DevMan.ticket",
 *      "autosuggest_filter_attribute_alias": "LABEL",
 *      "tag_text_attribute": "id"
 *  }
 * 
 * ```
 * 
 * Example 2: User mention:
 * ```
 * {
 *      "caption": "user mentions",
 *      "hint": "user mentions hint",
 *      "tag_prefix": "@",
 *      "autosuggest_max_number_of_rows": 10,
 *      "tag_color": "#001580",
 *      "tag_text_regex": ".*",
 *      "autosuggest_object_alias": "exface.Core.USER",
 *      "autosuggest_filter_attribute_alias": "FULL_NAME",
 * }
 * 
 * ```
 * 
 * 
 * TODO in the ToastUIEditorTrait:
 * 
 * ```
 *       "click_action": {
 *           "alias": "exface.Core.GoToPage",
 *           "page_alias": "axenox.DevMan.tickets"
 *      }
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
    private $tagTextAttribute = null;
    private $tagPrefix = null;
    private $autosuggestMaxNumberOfRows = null;
    private $tagTextRegex = null;
    private $tagColor = null;


    /**
     * It specifies the object where the mention widgets should get the data from.
     *
     * @uxon-property autosuggest_object_alias
     * @uxon-type metamodel:object
     *
     * @param string $alias
     * @return $this
     */
    protected function setAutosuggestObjectAlias(string $alias) : TextMention
    {
        $this->autosuggestObjectAlias = $alias;
        return $this;
    }

    /**
     * @return MetaObjectInterface
     */
    public function getAutosuggestObject() : MetaObjectInterface
    {
        return MetaObjectFactory::createFromString($this->getWorkbench(), $this->autosuggestObjectAlias);
    }

    /**
     * This is the alias of the field inside the "autosuggest_object" that is used for filtering and will appear in the dropdown.
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

    /**
     * @return string
     */
    public function getAutosuggestFilterAttributeAlias() : string
    {
        return $this->autosuggestFilterAttributeAlias;
    }
    
    /**
     * This is the attribute alias that will be inserted into the tag text when the autosuggest value is selected.
     * If no 'autosuggest_filter_attribute_alias' is given, the 'tag_text_attribute' property is used automatically.
     *
     * @uxon-property tag_text_attribute
     * @uxon-type string
     *
     * @param string $alias
     * @return $this
     */
    public function setTagTextAttribute(string $alias) : TextMention
    {
        $this->tagTextAttribute = $alias;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTagTextAttribute() : ?string
    {
        return $this->tagTextAttribute;
    }

    /**
     * This character activates the mention widget, if typed into the editor.
     *
     * @uxon-property tag_prefix
     * @uxon-type string
     * @uxon-template @
     *
     * @param string $tagPrefix
     * @return TextMention
     */
    protected function setTagPrefix(string $tagPrefix) : TextMention
    {
        $this->tagPrefix = $tagPrefix;
        return $this;
    }

    /**
     * @return string
     */
    public function getTagPrefix() : string
    {
        return $this->tagPrefix;
    }

    /**
     * This sets the limit number of rows that the autosuggest will display.
     *
     * @uxon-property autosuggest_max_number_of_rows
     *
     * @param int $numberOfRows
     * @return $this
     */
    public function setAutosuggestMaxNumberOfRows(int $numberOfRows) : TextMention
    {
        $this->autosuggestMaxNumberOfRows = $numberOfRows;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getAutosuggestMaxNumberOfRows() : ?int
    {
        return $this->autosuggestMaxNumberOfRows;
    }

    /**
     * This regular expression is used to identify the searching string of mentions
     * Example:
     * - with the tag_prefix = "@" and tag_text_regex = ".*": "@Max Mustermann"
     * - with the tag_prefix = "#" and tag_text_regex = "\\d*": "#12345"
     *
     * @uxon-property tag_text_regex
     * @uxon-type string
     * @uxon-template .*
     *
     * @param string $tagTextRegex
     * @return $this
     */
    protected function setTagTextRegex(string $tagTextRegex) : TextMention
    {
        $this->tagTextRegex = $tagTextRegex;
        return $this;
    }

    public function getTagTextRegex() : string
    {
        return $this->tagTextRegex;
    }
    

    /**
     * This sets the color of the mention tag.
     * 
     * @uxon-property tag_color
     * @uxon-type string
     * @uxon-defaul '#001580'
     * 
     * @param string $color
     * @return $this
     */
    public function setTagColor(string $color) : TextMention
    {
        $this->tagColor = $color;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTagColor() : ?string
    {
        return $this->tagColor;
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