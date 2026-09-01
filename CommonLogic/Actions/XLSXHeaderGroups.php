<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * Configure one consecutive group of column headers in an XLSX workbook.
 *
 * Define the group title, number of columns, common column width, header text orientation, and
 * display of empty cells.
 * 
 * ```
 * 
 * {
 *     "name": "Relevance and status",
 *     "column_count": 8,
 *     "column_width": 5.5,
 *     "orientation": "vertical",
 *     "empty_cell_filler": "n/a",
 *     "empty_cell_color": "#eeeeee"
 * }
 * 
 * ```
 * 
 *
 * @author Sergej Riel
 */
class XLSXHeaderGroups implements iCanBeConvertedToUxon
{
    use ICanBeConvertedToUxonTrait;

    private ActionInterface $action;
    private string $name = '';
    private int $columnCount = 0;
    private float $columnWidth = 13.0;
    private string $orientation = 'horizontal';
    private ?string $emptyCellFiller = null;
    private ?string $emptyCellColor = null;

    /**
     * Creates a header group for an action and imports its UXON configuration.
     *
     * @param ActionInterface $action
     * @param UxonObject|null $uxon
     */
    public function __construct(ActionInterface $action, ?UxonObject $uxon = null)
    {
        $this->action = $action;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }

    /**
     * Define the title displayed above the columns in this group.
     *
     * @uxon-property name
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $value
     * @return $this
     */
    public function setName(string $value): XLSXHeaderGroups
    {
        $this->name = $value;
        return $this;
    }

    /**
     * Returns the title displayed above this group.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Define how many consecutive exported columns belong to this group.
     *
     * @uxon-property column_count
     * @uxon-type integer
     *
     * @param int $value
     * @return $this
     */
    public function setColumnCount(int $value): XLSXHeaderGroups
    {
        if ($value < 1) {
            throw new ActionConfigurationError($this->action, 'XLSX header group `column_count` must be greater than zero.');
        }
        $this->columnCount = $value;
        return $this;
    }

    /**
     * Returns the number of consecutive columns in this group.
     */
    public function getColumnCount(): int
    {
        if ($this->columnCount < 1) {
            throw new ActionConfigurationError($this->action, 'XLSX header group `column_count` is required.');
        }
        return $this->columnCount;
    }

    /**
     * Define the width applied to every column in this group.
     *
     * @uxon-property column_width
     * @uxon-type number
     * @uxon-default 13
     *
     * @param int|float $value
     * @return $this
     */
    public function setColumnWidth($value): XLSXHeaderGroups
    {
        if ((! is_int($value) && ! is_float($value)) || $value <= 0) {
            throw new ActionConfigurationError($this->action, 'XLSX header group `column_width` must be a number greater than zero.');
        }
        $this->columnWidth = (float) $value;
        return $this;
    }

    /**
     * Returns the width applied to every column in this group.
     */
    public function getColumnWidth(): float
    {
        return $this->columnWidth;
    }

    /**
     * Define whether column header text is displayed horizontally or vertically.
     *
     * @uxon-property orientation
     * @uxon-type [horizontal,vertical]
     * @uxon-default horizontal
     *
     * @param string $value
     * @return $this
     */
    public function setOrientation(string $value): XLSXHeaderGroups
    {
        if (! in_array($value, ['horizontal', 'vertical'], true)) {
            throw new ActionConfigurationError($this->action, 'Invalid XLSX header group orientation "' . $value . '".');
        }
        $this->orientation = $value;
        return $this;
    }

    /**
     * Returns the orientation of column header text in this group.
     */
    public function getOrientation(): string
    {
        return $this->orientation;
    }

    /**
     * Define the text displayed in empty data cells of this group.
     *
     * @uxon-property empty_cell_filler
     * @uxon-type string
     * @param string $value
     * @return $this
     */
    public function setEmptyCellFiller(string $value): XLSXHeaderGroups
    {
        $this->emptyCellFiller = $value;
        return $this;
    }

    /**
     * Returns the text displayed in empty data cells of this group.
     */
    public function getEmptyCellFiller(): ?string
    {
        return $this->emptyCellFiller;
    }

    /**
     * Define the background color applied to empty data cells of this group.
     *
     * Leave this property unset to keep empty cells uncolored.
     *
     * @uxon-property empty_cell_color
     * @uxon-type color
     *
     * @param string $value
     * @return $this
     */
    public function setEmptyCellColor(string $value): XLSXHeaderGroups
    {
        $this->emptyCellColor = $value;
        return $this;
    }

    /**
     * Returns the optional background color for empty data cells.
     */
    public function getEmptyCellColor(): ?string
    {
        return $this->emptyCellColor;
    }

    /**
     * Returns this group in the format expected by XLSX builders.
     *
     * @return array{
     *     name:string,
     *     column_count:int,
     *     column_width:float,
     *     orientation:string,
     *     empty_cell_filler:string|null,
     *     empty_cell_color:string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'column_count' => $this->getColumnCount(),
            'column_width' => $this->getColumnWidth(),
            'orientation' => $this->getOrientation(),
            'empty_cell_filler' => $this->getEmptyCellFiller(),
            'empty_cell_color' => $this->getEmptyCellColor(),
        ];
    }
}
