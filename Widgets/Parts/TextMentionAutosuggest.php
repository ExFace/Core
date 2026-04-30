<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * Autosuggest configuration for text mentions.
 *
 * @author Andrej Kabachnik
 */
class TextMentionAutosuggest implements WidgetPartInterface
{
    use ImportUxonObjectTrait;

    private TextMention $mention;

    private ?UxonObject $uxon = null;

    private ?string $objectAlias = null;

    private ?string $filterAttributeAlias = null;

    private ?int $maxNumberOfRows = null;

    public function __construct(TextMention $mention, UxonObject $uxon = null)
    {
        $this->mention = $mention;
        if ($uxon !== null) {
            $this->uxon = $uxon;
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
     * This is the alias of the field inside the `object_alias` used for filtering and dropdown labels.
     *
     * @uxon-property filter_attribute_alias
     * @uxon-type string
     *
     * @param string $alias
     * @return $this
     */
    protected function setFilterAttributeAlias(string $alias) : TextMentionAutosuggest
    {
        $this->filterAttributeAlias = $alias;
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

    public function getFilterAttributeAlias() : ?string
    {
        return $this->filterAttributeAlias;
    }

    public function getMaxNumberOfRows() : ?int
    {
        return $this->maxNumberOfRows;
    }

    public function getObject() : MetaObjectInterface
    {
        return MetaObjectFactory::createFromString($this->getWorkbench(), $this->objectAlias);
    }

    public function exportUxonObject()
    {
        if ($this->uxon !== null) {
            return $this->uxon;
        }

        return new UxonObject([
            'object_alias' => $this->objectAlias,
            'filter_attribute_alias' => $this->filterAttributeAlias,
            'max_number_of_rows' => $this->maxNumberOfRows
        ]);
    }

    public function getWidget() : WidgetInterface
    {
        return $this->mention->getWidget();
    }

    public function getWorkbench()
    {
        return $this->mention->getWorkbench();
    }
}
