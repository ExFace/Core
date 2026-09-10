<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Configure the page layout used when an action creates a printable XLSX workbook.
 *
 * Use these settings as a nested `xlsx_print_settings` object on the action.
 * 
 * ```
 * 
 * {
 *     "orientation": "landscape",
 *     "paper_size": 9,
 *     "page_order": "downThenOver",
 *     "scale": 100,
 *     "page_margins": {
 *         "left": 0.25,
 *         "right": 0.25,
 *         "top": 0.75,
 *         "bottom": 0.75,
 *         "header": 0.3,
 *         "footer": 0.3
 *     }
 * }
 * 
 * ```
 * 
 *
 * @author Sergej Riel
 */
class XLSXPrintSettings implements iCanBeConvertedToUxon
{
    use ICanBeConvertedToUxonTrait;

    private ActionInterface $action;
    private string $orientation = 'landscape';
    private int $paperSize = 9;
    private string $pageOrder = 'downThenOver';
    private int $scale = 100;
    private ?XLSXPageMargins $pageMargins = null;

    /**
     * Creates print settings for an action and imports their optional UXON configuration.
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
     * Set the printed page orientation.
     *
     * @uxon-property orientation
     * @uxon-type [default,landscape,portrait]
     * @uxon-default landscape
     *
     * @param string $value
     * @return $this
     */
    public function setOrientation(string $value): XLSXPrintSettings
    {
        if (! in_array($value, [
            'default',
            'landscape',
            'portrait',
        ], true)) {
            throw new ActionConfigurationError($this->action, 'Invalid XLSX print orientation "' . $value . '".');
        }
        $this->orientation = $value;
        return $this;
    }

    /**
     * Returns the printed page orientation.
     *
     * @return string
     */
    public function getOrientation(): string
    {
        return $this->orientation;
    }

    /**
     * Set the printed paper size using a PhpSpreadsheet `PageSetup::PAPERSIZE_*` numeric code.
     *
     * @uxon-property paper_size
     * @uxon-type integer
     * @uxon-default 9
     *
     * @param int $value
     * @return $this
     */
    public function setPaperSize(int $value): XLSXPrintSettings
    {
        if (! in_array($value, $this->getSupportedPaperSizes(), true)) {
            throw new ActionConfigurationError($this->action, 'Unsupported XLSX paper size code "' . $value . '".');
        }
        $this->paperSize = $value;
        return $this;
    }

    /**
     * Returns the PhpSpreadsheet paper size code.
     *
     * @return int
     */
    public function getPaperSize(): int
    {
        return $this->paperSize;
    }

    /**
     * Set whether pages are printed down before across or across before down.
     *
     * @uxon-property page_order
     * @uxon-type [downThenOver,overThenDown]
     * @uxon-default downThenOver
     *
     * @param string $value
     * @return $this
     */
    public function setPageOrder(string $value): XLSXPrintSettings
    {
        if (! in_array($value, [
            'downThenOver',
            'overThenDown',
        ], true)) {
            throw new ActionConfigurationError($this->action, 'Invalid XLSX page order "' . $value . '".');
        }
        $this->pageOrder = $value;
        return $this;
    }

    /**
     * Returns the order in which worksheet pages are printed.
     *
     * @return string
     */
    public function getPageOrder(): string
    {
        return $this->pageOrder;
    }

    /**
     * Set the print scale as a percentage from 10 through 400.
     *
     * @uxon-property scale
     * @uxon-type integer
     * @uxon-default 100
     *
     * @param int $value
     * @return $this
     */
    public function setScale(int $value): XLSXPrintSettings
    {
        if ($value < 10 || $value > 400) {
            throw new ActionConfigurationError($this->action, 'XLSX print scale must be between 10 and 400.');
        }
        $this->scale = $value;
        return $this;
    }

    /**
     * Returns the print scale percentage.
     *
     * @return int
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * Define page margins in inches.
     *
     * @uxon-property page_margins
     * @uxon-type \exface\Core\CommonLogic\Actions\XLSXPageMargins
     * @uxon-template {"left":0.25,"right":0.25,"top":0.75,"bottom":0.75,"header":0.3,"footer":0.3}
     *
     * @param UxonObject $value
     * @return $this
     */
    public function setPageMargins(UxonObject $value): XLSXPrintSettings
    {
        $this->pageMargins = new XLSXPageMargins($this->action, $value);
        return $this;
    }

    /**
     * Returns the XLSX page margins, creating their defaults when not configured.
     *
     * @return XLSXPageMargins
     */
    public function getPageMargins(): XLSXPageMargins
    {
        if ($this->pageMargins === null) {
            $this->pageMargins = new XLSXPageMargins($this->action);
        }
        return $this->pageMargins;
    }

    /**
     * Returns all print settings in the format expected by XLSX builders.
     *
     * @return array{
     *     orientation:string,
     *     paper_size:int,
     *     page_order:string,
     *     scale:int,
     *     page_margins:array{left:float,right:float,top:float,bottom:float,header:float,footer:float}
     * }
     */
    public function toArray(): array
    {
        return [
            'orientation' => $this->getOrientation(),
            'paper_size' => $this->getPaperSize(),
            'page_order' => $this->getPageOrder(),
            'scale' => $this->getScale(),
            'page_margins' => $this->getPageMargins()->toArray(),
        ];
    }

    /**
     * Returns all paper size codes exposed by the installed PhpSpreadsheet version.
     *
     * @return list<int>
     */
    private function getSupportedPaperSizes(): array
    {
        $constants = (new \ReflectionClass(PageSetup::class))->getConstants();
        $paperSizes = array_filter(
            $constants,
            static fn($value, string $name): bool => str_starts_with($name, 'PAPERSIZE_') && is_int($value),
            ARRAY_FILTER_USE_BOTH
        );
        return array_values(array_unique($paperSizes));
    }
}