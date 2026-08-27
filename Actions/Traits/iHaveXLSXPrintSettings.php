<?php
namespace exface\Core\Actions\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Adds configurable PhpSpreadsheet print settings to an XLSX action.
 */
trait iHaveXLSXPrintSettings
{

    
    private string $xlsxOrientation = 'landscape';
    private int $xlsxPaperSize = 9; // ID for A4 paper
    private string $xlsxPageOrder = 'downThenOver';
    private int $xlsxScale = 100;
    private array $xlsxPageMargins = [
        'left' => 0.25,
        'right' => 0.25,
        'top' => 0.75,
        'bottom' => 0.75,
        'header' => 0.3,
        'footer' => 0.3,
    ];

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
    public function setOrientation(string $value)
    {
        if (! in_array($value, [
            'default',
            'landscape',
            'portrait',
        ], true)) {
            throw new ActionConfigurationError($this, 'Invalid XLSX print orientation "' . $value . '".');
        }
        $this->xlsxOrientation = $value;
        return $this;
    }

    /**
     * Returns the printed page orientation.
     */
    public function getOrientation(): string
    {
        return $this->xlsxOrientation;
    }

    /**
     * Set the printed paper size using a PhpSpreadsheet `PageSetup::PAPERSIZE_*` numeric code.
     * Code : Name
     * 
     * Supported paper sizes:
     * 1 US Letter 8 1/2 x 11 in
     * 2 US Letter Small 8 1/2 x 11 in
     * 3 US Tabloid 11 x 17 in
     * 4 US Ledger 17 x 11 in
     * 5 US Legal 8 1/2 x 14 in
     * 6 US Statement 5 1/2 x 8 1/2 in
     * 7 US Executive 7 1/4 x 10 1/2 in
     * 8 A3 297 x 420 mm
     * 9 A4 210 x 297 mm
     * 10 A4 Small 210 x 297 mm
     * 11 A5 148 x 210 mm
     * 12 B4 (JIS) 257 x 364 mm
     * 13 B5 (JIS) 182 x 257 mm
     * 14 Folio 8 1/2 x 13 in
     * 15 Quarto 215 x 275 mm
     * 16 10 x 14 in
     * 17 11 x 17 in
     * 18 US Note 8 1/2 x 11 in
     * 19 US Envelope #9 3 7/8 x 8 7/8
     * 20 US Envelope #10 4 1/8 x 9 1/2
     * 21 US Envelope #11 4 1/2 x 10 3/8
     * 22 US Envelope #12 4 3/4 x 11 in
     * 23 US Envelope #14 5 x 11 1/2
     * 24 C size sheet
     * 25 D size sheet
     * 26 E size sheet
     * 27 Envelope DL 110 x 220mm
     * 28 Envelope C5 162 x 229 mm
     * 29 Envelope C3 324 x 458 mm
     * 30 Envelope C4 229 x 324 mm
     * 31 Envelope C6 114 x 162 mm
     * 32 Envelope C65 114 x 229 mm
     * 33 Envelope B4 250 x 353 mm
     * 34 Envelope B5 176 x 250 mm
     * 35 Envelope B6 176 x 125 mm
     * 36 Envelope 110 x 230 mm
     * 37 US Envelope Monarch 3.875 x 7.5 in
     * 38 6 3/4 US Envelope 3 5/8 x 6 1/2 in
     * 39 US Std Fanfold 14 7/8 x 11 in
     * 40 German Std Fanfold 8 1/2 x 12 in
     * 41 German Legal Fanfold 8 1/2 x 13 in
     * 42 B4 (ISO) 250 x 353 mm
     * 43 Japanese Postcard 100 x 148 mm
     * 44 9 x 11 in
     * 45 10 x 11 in
     * 46 15 x 11 in
     * 47 Envelope Invite 220 x 220 mm
     * 48 RESERVED--DO NOT USE
     * 49 RESERVED--DO NOT USE
     * 50 US Letter Extra 9 1/2 x 12 in
     * 51 US Legal Extra 9 1/2 x 15 in
     * 52 US Tabloid Extra 11.69 x 18 in
     * 53 A4 Extra 9.27 x 12.69 in
     * 54 Letter Transverse 8 1/2 x 11 in
     * 55 A4 Transverse 210 x 297 mm
     * 56 Letter Extra Transverse 9 1/2 x 12 in
     * 57 SuperA/SuperA/A4 227 x 356 mm
     * 58 SuperB/SuperB/A3 305 x 487 mm
     * 59 US Letter Plus 8.5 x 12.69 in
     * 60 A4 Plus 210 x 330 mm
     * 61 A5 Transverse 148 x 210 mm
     * 62 B5 (JIS) Transverse 182 x 257 mm
     * 63 A3 Extra 322 x 445 mm
     * 64 A5 Extra 174 x 235 mm
     * 65 B5 (ISO) Extra 201 x 276 mm
     * 66 A2 420 x 594 mm
     * 67 A3 Transverse 297 x 420 mm
     * 68 A3 Extra Transverse 322 x 445 mm
     * 69 Japanese Double Postcard 200 x 148 mm
     * 70 A6 105 x 148 mm
     * 71 Japanese Envelope Kaku #2
     * 72 Japanese Envelope Kaku #3
     * 73 Japanese Envelope Chou #3
     * 74 Japanese Envelope Chou #4
     * 75 Letter Rotated 11 x 8 1/2 11 in
     * 76 A3 Rotated 420 x 297 mm
     * 77 A4 Rotated 297 x 210 mm
     * 78 A5 Rotated 210 x 148 mm
     * 79 B4 (JIS) Rotated 364 x 257 mm
     * 80 B5 (JIS) Rotated 257 x 182 mm
     * 81 Japanese Postcard Rotated 148 x 100 mm
     * 82 Double Japanese Postcard Rotated 148 x 200 mm
     * 83 A6 Rotated 148 x 105 mm
     * 84 Japanese Envelope Kaku #2 Rotated
     * 85 Japanese Envelope Kaku #3 Rotated
     * 86 Japanese Envelope Chou #3 Rotated
     * 87 Japanese Envelope Chou #4 Rotated
     * 88 B6 (JIS) 128 x 182 mm
     * 89 B6 (JIS) Rotated 182 x 128 mm
     * 90 12 x 11 in
     * 91 Japanese Envelope You #4
     * 92 Japanese Envelope You #4 Rotated
     * 93 PRC 16K 146 x 215 mm
     * 94 PRC 32K 97 x 151 mm
     * 95 PRC 32K(Big) 97 x 151 mm
     * 96 PRC Envelope #1 102 x 165 mm
     * 97 PRC Envelope #2 102 x 176 mm
     * 98 PRC Envelope #3 125 x 176 mm
     * 99 PRC Envelope #4 110 x 208 mm
     * 100 PRC Envelope #5 110 x 220 mm
     * 101 PRC Envelope #6 120 x 230 mm
     * 102 PRC Envelope #7 160 x 230 mm
     * 103 PRC Envelope #8 120 x 309 mm
     * 104 PRC Envelope #9 229 x 324 mm
     * 105 PRC Envelope #10 324 x 458 mm
     * 106 PRC 16K Rotated
     * 107 PRC 32K Rotated
     * 108 PRC 32K(Big) Rotated
     * 109 PRC Envelope #1 Rotated 165 x 102 mm
     * 110 PRC Envelope #2 Rotated 176 x 102 mm
     * 111 PRC Envelope #3 Rotated 176 x 125 mm
     * 112 PRC Envelope #4 Rotated 208 x 110 mm
     * 113 PRC Envelope #5 Rotated 220 x 110 mm
     * 114 PRC Envelope #6 Rotated 230 x 120 mm
     * 115 PRC Envelope #7 Rotated 230 x 160 mm
     * 116 PRC Envelope #8 Rotated 309 x 120 mm
     * 117 PRC Envelope #9 Rotated 324 x 229 mm
     * 118 PRC Envelope #10 Rotated 458 x 324 mm
     *
     * @uxon-property paper_size
     * @uxon-type integer
     * @uxon-default 9
     *
     * @param int $value
     * @return $this
     */
    public function setPaperSize(int $value)
    {
        if (! in_array($value, $this->getSupportedPaperSizes(), true)) {
            throw new ActionConfigurationError($this, 'Unsupported XLSX paper size code "' . $value . '".');
        }
        $this->xlsxPaperSize = $value;
        return $this;
    }

    /**
     * Returns the PhpSpreadsheet paper size code.
     */
    public function getPaperSize(): int
    {
        return $this->xlsxPaperSize;
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
    public function setPageOrder(string $value)
    {
        if (! in_array($value, [
            'downThenOver',
            'overThenDown',
        ], true)) {
            throw new ActionConfigurationError($this, 'Invalid XLSX page order "' . $value . '".');
        }
        $this->xlsxPageOrder = $value;
        return $this;
    }

    /**
     * Returns the order in which worksheet pages are printed.
     */
    public function getPageOrder(): string
    {
        return $this->xlsxPageOrder;
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
    public function setScale(int $value)
    {
        if ($value < 10 || $value > 400) {
            throw new ActionConfigurationError($this, 'XLSX print scale must be between 10 and 400.');
        }
        $this->xlsxScale = $value;
        return $this;
    }

    /**
     * Returns the print scale percentage.
     */
    public function getScale(): int
    {
        return $this->xlsxScale;
    }

    /**
     * Define page margins in inches.
     *
     * Supported keys are `left`, `right`, `top`, `bottom`, `header`, and `footer`. Omitted keys
     * retain their defaults.
     *
     * @uxon-property page_margins
     * @uxon-type object
     * @uxon-template {"left":0.25,"right":0.25,"top":0.75,"bottom":0.75,"header":0.3,"footer":0.3}
     *
     * @param UxonObject $value
     * @return $this
     */
    public function setPageMargins(UxonObject $value)
    {
        foreach ($value->toArray() as $margin => $size) {
            if (! array_key_exists($margin, $this->xlsxPageMargins)) {
                throw new ActionConfigurationError($this, 'Unsupported XLSX page margin "' . $margin . '".');
            }
            if (! is_int($size) && ! is_float($size)) {
                throw new ActionConfigurationError($this, 'XLSX page margin "' . $margin . '" must be numeric.');
            }
            if ($size < 0) {
                throw new ActionConfigurationError($this, 'XLSX page margin "' . $margin . '" cannot be negative.');
            }
            $this->xlsxPageMargins[$margin] = (float) $size;
        }
        return $this;
    }

    /**
     * Returns page margins in inches.
     *
     * @return array{left:float,right:float,top:float,bottom:float,header:float,footer:float}
     */
    public function getPageMargins(): array
    {
        return $this->xlsxPageMargins;
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
    public function getXLSXPrintSettings(): array
    {
        return [
            'orientation' => $this->getOrientation(),
            'paper_size' => $this->getPaperSize(),
            'page_order' => $this->getPageOrder(),
            'scale' => $this->getScale(),
            'page_margins' => $this->getPageMargins(),
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