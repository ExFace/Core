<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Widgets\InputCombo;

/**
 * Configuration for a mention tag or hash tag in a text editor.
 *
 * Use `tag_prefix` and `tag_text_regex` to recognize tags in the document.
 * Configure lookup fields inside `autosuggest`, relative to its `object_alias`:
 * `filter_attribute` selects matching rows, `suggestion_attribute` supplies menu
 * labels, and `tag_attribute` supplies the value inserted after the prefix.
 * Omitted tag/menu attributes use the effective filter attribute.
 * 
 * Example 1: Record mention
 * 
 * ```
 * 
 * {
 *      "caption": "Record",
 *      "tag_prefix": "#",
 *      "tag_color": "green",
 *      "tag_text_regex": "\\d*",
 *      "autosuggest": {
 *          "object_alias": "example.App.RECORD",
 *          "filter_attribute": "NUMBER",
 *          "tag_attribute": "NUMBER",
 *          "suggestion_attribute": "LABEL",
 *          "max_number_of_rows": 10
 *      }
 * }
 * 
 * ```
 * 
 * Example 2: User mention
 * 
 * ```
 * 
 * {
 *      "caption": "user mentions",
 *      "tag_prefix": "@",
 *      "tag_color": "#001580",
 *      "tag_text_regex": ".*",
 *      "autosuggest": {
 *          "object_alias": "exface.Core.USER",
 *          "filter_attribute": "USERNAME",
 *          "tag_attribute": "USERNAME",
 *          "suggestion_attribute": "FULL_NAME",
 *          "max_number_of_rows": 10
 *      }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 */
class TextMention extends TextStencil
{
    private ?TextMentionAutosuggest $autosuggest = null;

    private ?iTriggerAction $autosuggestButton = null;

    private $tagPrefix = null;

    private $tagTextRegex = null;

    private $tagColor = null;

    /**
     * Autosuggest configuration.
     *
     * @uxon-property autosuggest
     * @uxon-type \exface\Core\Widgets\Parts\TextMentionAutosuggest
    * @uxon-template {"object_alias": "", "filter_attribute": "", "tag_attribute": "", "suggestion_attribute": "", "max_number_of_rows": 10}
     *
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setAutosuggest(UxonObject $uxon) : TextMention
    {
        if ($this->autosuggest !== null) {
            $this->autosuggest->importUxonObject($uxon);
        } else {
            $this->autosuggest = new TextMentionAutosuggest($this, $uxon);
        }
        return $this;
    }

    public function getAutosuggest() : TextMentionAutosuggest
    {
        if ($this->autosuggest === null) {
            $this->autosuggest = new TextMentionAutosuggest($this);
        }
        return $this->autosuggest;
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
     * This regular expression is used to identify the searching string of mentions.
     *
     * Example:
     * - with the tag_prefix = "@" and tag_text_regex = ".*": "@Max Mustermann"
     * - with the tag_prefix = "#" and tag_text_regex = "\\d*": "#12345"
     *
     * @uxon-property tag_text_regex
     * @uxon-type string
     * @uxon-template .*
        * @uxon-default .*
     *
     * @param string $tagTextRegex
     * @return $this
     */
    protected function setTagTextRegex(string $tagTextRegex) : TextMention
    {
        $this->tagTextRegex = $tagTextRegex;
        return $this;
    }

    /**
     * Supplies the unrestricted default when existing models omit a body pattern.
     * 
     * @return string
     */
    public function getTagTextRegex() : string
    {
        return $this->tagTextRegex ?? '.*';
    }

    /**
     * This sets the color of the mention tag.
     *
     * @uxon-property tag_color
     * @uxon-type string
     * @uxon-default '#001580'
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

    /**
     * Requests every attribute needed for labels and inserted values, including relation paths.
     * 
     * @return UxonObject
     */
    public function getAutosuggestActionUxon() : UxonObject
    {
        $autosuggestObj = $this->getAutosuggest()->getObject();
        $uxon = new UxonObject([
            'alias' => 'exface.Core.ReadData',
            'object_alias' => $this->getAutosuggest()->getObjectAlias(),
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
        foreach (array_unique(array_filter([
            $this->getAutosuggest()->getFilterAttribute(),
            $this->getAutosuggest()->getTagAttribute(),
            $this->getAutosuggest()->getSuggestionAttribute()
        ])) as $alias) {
            $uxon->appendToProperty('columns', new UxonObject([
                'attribute_alias' => $autosuggestObj->getAttribute($alias)->getAliasWithRelationPath()
            ]));
        }
        return $uxon;
    }

    /**
     * Keeps the AJAX action's widget ID stable during rendering and later server-side lookup.
     * 
     * @return iTriggerAction
     */
    public function getAutosuggestButton() : iTriggerAction
    {
        if ($this->autosuggestButton !== null) {
            return $this->autosuggestButton;
        }
        $this->autosuggestButton = WidgetFactory::createFromUxonInParent($this->getWidget(), new UxonObject([
            'widget_type' => 'Button',
            'action' => $this->getAutosuggestActionUxon()->toArray()
        ]));
        return $this->autosuggestButton;
    }

    // TODO SR: Check if this is still needed:
    public function getAutosuggestWidget() : InputCombo
    {
        $autosuggestObj = $this->getAutosuggestObject();
        $uxon = new UxonObject([
            'widget_type' => 'InputComboTable',
            'table_object_alias' => $this->getAutosuggest()->getObjectAlias()
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