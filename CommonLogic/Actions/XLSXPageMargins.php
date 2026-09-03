<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * Configure the page margins of a printable XLSX workbook in inches.
 *
 * Omitted margins retain their defaults.
 * 
 * ```
 * 
 * {
 *     "left": 0.25,
 *     "right": 0.25,
 *     "top": 0.75,
 *     "bottom": 0.75,
 *     "header": 0.3,
 *     "footer": 0.3
 * }
 * 
 * ```
 * 
 *
 * @author Sergej Riel
 */
class XLSXPageMargins implements iCanBeConvertedToUxon
{
    use ICanBeConvertedToUxonTrait;

    private ActionInterface $action;
    private float $left = 0.25;
    private float $right = 0.25;
    private float $top = 0.75;
    private float $bottom = 0.75;
    private float $header = 0.3;
    private float $footer = 0.3;

    /**
     * Creates page margins for an action and imports their optional UXON configuration.
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
     * Set the left page margin in inches.
     *
     * @uxon-property left
     * @uxon-type number
     * @uxon-default 0.25
     *
     * @param int|float $value
     * @return $this
     */
    public function setLeft($value): XLSXPageMargins
    {
        $this->left = $this->validateMargin($value, 'left');
        return $this;
    }

    /**
     * Returns the left page margin in inches.
     *
     * @return float
     */
    public function getLeft(): float
    {
        return $this->left;
    }

    /**
     * Set the right page margin in inches.
     *
     * @uxon-property right
     * @uxon-type number
     * @uxon-default 0.25
     *
     * @param int|float $value
     * @return $this
     */
    public function setRight($value): XLSXPageMargins
    {
        $this->right = $this->validateMargin($value, 'right');
        return $this;
    }

    /**
     * Returns the right page margin in inches.
     *
     * @return float
     */
    public function getRight(): float
    {
        return $this->right;
    }

    /**
     * Set the top page margin in inches.
     *
     * @uxon-property top
     * @uxon-type number
     * @uxon-default 0.75
     *
     * @param int|float $value
     * @return $this
     */
    public function setTop($value): XLSXPageMargins
    {
        $this->top = $this->validateMargin($value, 'top');
        return $this;
    }

    /**
     * Returns the top page margin in inches.
     *
     * @return float
     */
    public function getTop(): float
    {
        return $this->top;
    }

    /**
     * Set the bottom page margin in inches.
     *
     * @uxon-property bottom
     * @uxon-type number
     * @uxon-default 0.75
     *
     * @param int|float $value
     * @return $this
     */
    public function setBottom($value): XLSXPageMargins
    {
        $this->bottom = $this->validateMargin($value, 'bottom');
        return $this;
    }

    /**
     * Returns the bottom page margin in inches.
     *
     * @return float
     */
    public function getBottom(): float
    {
        return $this->bottom;
    }

    /**
     * Set the header page margin in inches.
     *
     * @uxon-property header
     * @uxon-type number
     * @uxon-default 0.3
     *
     * @param int|float $value
     * @return $this
     */
    public function setHeader($value): XLSXPageMargins
    {
        $this->header = $this->validateMargin($value, 'header');
        return $this;
    }

    /**
     * Returns the header page margin in inches.
     *
     * @return float
     */
    public function getHeader(): float
    {
        return $this->header;
    }

    /**
     * Set the footer page margin in inches.
     *
     * @uxon-property footer
     * @uxon-type number
     * @uxon-default 0.3
     *
     * @param int|float $value
     * @return $this
     */
    public function setFooter($value): XLSXPageMargins
    {
        $this->footer = $this->validateMargin($value, 'footer');
        return $this;
    }

    /**
     * Returns the footer page margin in inches.
     *
     * @return float
     */
    public function getFooter(): float
    {
        return $this->footer;
    }

    /**
     * Returns all margins in the format expected by XLSX builders.
     *
     * @return array{left:float,right:float,top:float,bottom:float,header:float,footer:float}
     */
    public function toArray(): array
    {
        return [
            'left' => $this->getLeft(),
            'right' => $this->getRight(),
            'top' => $this->getTop(),
            'bottom' => $this->getBottom(),
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
        ];
    }

    /**
     * Validates and normalizes a page margin to a non-negative float.
     *
     * @param mixed $value
     * @param string $name
     * @return float
     */
    private function validateMargin($value, string $name): float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new ActionConfigurationError($this->action, 'XLSX page margin "' . $name . '" must be numeric.');
        }
        if ($value < 0) {
            throw new ActionConfigurationError($this->action, 'XLSX page margin "' . $name . '" cannot be negative.');
        }
        return (float) $value;
    }
}
