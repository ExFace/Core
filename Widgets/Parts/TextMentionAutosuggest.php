<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * Autosuggest configuration for text mentions.
 *
 * Define `object_alias` and independent `filter_attribute`, `tag_attribute`
 * and `suggestion_attribute` aliases relative to that object. These control
 * searching, inserted text and menu labels respectively.
 * 
 * @author Andrej Kabachnik
 */
class TextMentionAutosuggest implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;

    private TextMention $mention;

    private ?string $objectAlias = null;
    private ?string $filterAttribute = null;
    private ?string $tagAttribute = null;
    private ?string $suggestionAttribute = null;
    private ?int $maxNumberOfRows = null;
    private ?UxonObject $dataUxon = null;

    public function __construct(TextMention $mention, UxonObject $uxon = null)
    {
        $this->mention = $mention;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }

    /**
     * It specifies the object where the mention autosuggest should get the data from.
     *
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     *
     * @param string $alias
     * @return $this
     */
    protected function setObjectAlias(string $alias) : TextMentionAutosuggest
    {
        $this->objectAlias = $alias;
        return $this;
    }

    /**
    * Choose the attribute of the suggestion object to filter by the typed query.
     *
    * @uxon-property filter_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $alias
     * @return $this
     */
    protected function setFilterAttribute(string $alias) : TextMentionAutosuggest
    {
        $this->filterAttribute = $alias;
        return $this;
    }

    /**
     * Choose the attribute of the suggestion object to insert after the tag prefix.
     * 
     * If omitted, the effective filter attribute supplies the inserted value.
     * 
     * @uxon-property tag_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    protected function setTagAttribute(string $alias) : TextMentionAutosuggest
    {
        $this->tagAttribute = $alias;
        return $this;
    }

    /**
     * Choose the attribute of the suggestion object to display in the suggestion menu.
     * 
     * If omitted, the effective filter attribute supplies the menu label.
     * 
     * @uxon-property suggestion_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return $this
     */
    protected function setSuggestionAttribute(string $alias) : TextMentionAutosuggest
    {
        $this->suggestionAttribute = $alias;
        return $this;
    }

    /**
     * Limit of rows that autosuggest should return.
     *
     * @uxon-property max_number_of_rows
     * @uxon-type integer
     *
     * @param int $rows
     * @return $this
     */
    protected function setMaxNumberOfRows(int $rows) : TextMentionAutosuggest
    {
        $this->maxNumberOfRows = $rows;
        return $this;
    }

    public function getObjectAlias() : ?string
    {
        return $this->objectAlias;
    }

    /**
     * Exposes the query field independently of the menu label and inserted tag.
     *
     * @return string|null
     */
    public function getFilterAttribute() : ?string
    {
        return $this->filterAttribute;
    }

    /**
     * Exposes the insertion field for facade-specific suggestion handling.
     *
     * @return string|null
     */
    public function getTagAttribute() : ?string
    {
        return $this->tagAttribute;
    }

    /**
     * Exposes the menu label without coupling it to query filtering.
     *
     * @return string|null
     */
    public function getSuggestionAttribute() : ?string
    {
        return $this->suggestionAttribute;
    }

    public function getMaxNumberOfRows() : ?int
    {
        return $this->maxNumberOfRows;
    }

    public function getObject() : MetaObjectInterface
    {
        return MetaObjectFactory::createFromString($this->getWorkbench(), $this->objectAlias);
    }

    public function getWidget() : WidgetInterface
    {
        return $this->mention->getWidget();
    }

    public function getWorkbench()
    {
        return $this->mention->getWorkbench();
    }
    
    protected function setData(UxonObject $uxon) : TextMentionAutosuggest
    {
        $this->dataUxon = $uxon;
        return $this;
    }
}