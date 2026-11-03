<?php

namespace exface\Core\Tests\PHPUnit\Unit\QueryBuilders;

use DateTimeImmutable;
use DateTimeZone;
use exface\Core\CommonLogic\Utils\GanttXlsxBuilder;
use exface\Core\Interfaces\Filesystem\FileInfoInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\QueryBuilders\ExcelBuilder;
use exface\Core\Templates\BracketHashXlsxTemplateRenderer;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Pins the spreadsheet behavior used by Core before changing PhpSpreadsheet majors.
 */
class PhpSpreadsheetCompatibilityTest extends TestCase
{
    private array $temporaryFiles = [];

    /**
     * Loads Core's legacy global constants required by the importer under test.
     */
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 4) . '/CommonLogic/Constants.php';
        parent::setUp();
    }

    /**
     * Removes temporary workbook fixtures after each characterization.
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    /**
     * The ExcelBuilder import resolves column headers, evaluates ranges, and retains empty cells as null.
     */
    public function testExcelBuilderImportsHeadersRangesAndEmptyCells(): void
    {
        $fixture = new Spreadsheet();
        $sheet = $fixture->getActiveSheet();
        $sheet->fromArray([
            ['Person', 'Identifier'],
            ['Ada', '0012345'],
            [null, '12345678901234567890'],
            [null, null],
            ['Grace', '1234567890123456'],
        ], null, 'A1', true);
        $path = $this->writeWorkbook($fixture, 'Xlsx');

        $object = $this->createStub(MetaObjectInterface::class);
        $object->method('getDataAddress')->willReturn($path);
        $object->method('getDataAddressProperty')->willReturn(null);

        $builder = $this->newExcelBuilder($path, $object, [
            $this->newAttribute('Person', '[Person]'),
            $this->newAttribute('Identifier', '[Identifier]'),
            $this->newAttribute('EmptyAwareRange', 'A2:A5'),
        ]);
        $rows = $builder->importFile($this->createStub(FileInfoInterface::class));

        // PhpSpreadsheet 1.30.x imported this 16-digit numeric-looking value as an integer; 5.x preserves it as a string to avoid identifier precision loss.
        self::assertSame([
            ['Person' => 'Ada', 'Identifier' => '0012345', 'EmptyAwareRange' => 'Ada'],
            ['Person' => null, 'Identifier' => '12345678901234567890', 'EmptyAwareRange' => null],
            ['Person' => null, 'Identifier' => null, 'EmptyAwareRange' => null],
            ['Person' => 'Grace', 'Identifier' => '1234567890123456', 'EmptyAwareRange' => 'Grace'],
        ], $rows);

        $worksheet = IOFactory::load($path)->getActiveSheet();
        // PhpSpreadsheet 1.30.x range reads returned the 16-digit identifier as an integer; 5.x keeps the string value intact.
        self::assertSame([
            2 => ['A' => 'Ada', 'B' => '0012345'],
            3 => ['A' => null, 'B' => '12345678901234567890'],
            4 => ['A' => null, 'B' => null],
            5 => ['A' => 'Grace', 'B' => '1234567890123456'],
        ], $builder->readRange($worksheet, 'A2:B5'));
        self::assertSame('B2:B5', $builder->resolveHeader($worksheet, '[Identifier]'));
        self::assertSame(DataType::TYPE_STRING, $worksheet->getCell('B2')->getDataType());
        self::assertSame(DataType::TYPE_STRING, $worksheet->getCell('B3')->getDataType());
        self::assertSame(DataType::TYPE_NULL, $worksheet->getCell('B4')->getDataType());
        // PhpSpreadsheet 1.30.x bound this 16-digit numeric string as numeric; 5.x binds it as text to preserve all digits.
        self::assertSame(DataType::TYPE_STRING, $worksheet->getCell('B5')->getDataType());
    }

    /**
     * Merged-cell fill mode returns the top-left value while preserving missing cells as null.
     */
    public function testExcelBuilderFillsMergedCellRanges(): void
    {
        $worksheet = (new Spreadsheet())->getActiveSheet();
        $worksheet->mergeCells('A1:B2');
        $worksheet->setCellValue('A1', 'Merged label');
        $worksheet->getCell('B1');
        $worksheet->getCell('A2');
        $worksheet->getCell('B2');
        $builder = $this->newExcelBuilder('', $this->createStub(MetaObjectInterface::class), []);

        self::assertSame([
            1 => ['A' => 'Merged label', 'B' => 'Merged label'],
            2 => ['A' => 'Merged label', 'B' => 'Merged label'],
        ], $builder->readMergedRange($worksheet, 'A1:B2'));
    }

    /**
     * Pins existing merged-range import behavior independently of PhpSpreadsheet: missing merged children stay null, and trailing merged rows beyond the highest data row are omitted.
     */
    public function testExcelBuilderImportFillsVerticalMergedRangeByDefault(): void
    {
        $fixture = new Spreadsheet();
        $sheet = $fixture->getActiveSheet();
        $sheet->mergeCells('A2:A4');
        $sheet->setCellValue('A2', 'North');
        $sheet->mergeCells('A5:A6');
        $sheet->setCellValue('A5', 'South');
        $path = $this->writeWorkbook($fixture, 'Xlsx');
        $builder = $this->newExcelBuilderForRangeImport($path, 'A2:A6');

        self::assertSame([
            ['Values' => 'North'],
            ['Values' => null],
            ['Values' => null],
            ['Values' => 'South'],
        ], $builder->importFile($this->createStub(FileInfoInterface::class)));
    }

    /**
     * Pins existing fill-disabled behavior independently of PhpSpreadsheet: missing merged children stay null, and trailing merged rows beyond the highest data row are omitted.
     */
    public function testExcelBuilderImportLeavesVerticalMergedCellsEmptyWhenFillIsDisabled(): void
    {
        $fixture = new Spreadsheet();
        $sheet = $fixture->getActiveSheet();
        $sheet->mergeCells('A2:A4');
        $sheet->setCellValue('A2', 'North');
        $sheet->mergeCells('A5:A6');
        $sheet->setCellValue('A5', 'South');
        $path = $this->writeWorkbook($fixture, 'Xlsx');
        $builder = $this->newExcelBuilderForRangeImport($path, 'A2:A6', false);

        self::assertSame([
            ['Values' => 'North'],
            ['Values' => null],
            ['Values' => null],
            ['Values' => 'South'],
        ], $builder->importFile($this->createStub(FileInfoInterface::class)));
    }

    /**
     * Pins existing horizontal merge behavior independently of PhpSpreadsheet: merged child coordinates absent from the cell collection remain null.
     */
    public function testExcelBuilderImportFillsHorizontallyMergedRangeAddress(): void
    {
        $fixture = new Spreadsheet();
        $sheet = $fixture->getActiveSheet();
        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue('A2', 'Across');
        $path = $this->writeWorkbook($fixture, 'Xlsx');
        $builder = $this->newExcelBuilderForRangeImport($path, 'A2:C2');

        self::assertSame([
            ['Values' => 'Across'],
            ['Values' => null],
            ['Values' => null],
        ], $builder->importFile($this->createStub(FileInfoInterface::class)));
    }

    /**
     * Exercises the replaced getCell([...]) call for styled merged child cells, pinning fill-on anchor values and fill-off nulls.
     */
    public function testExcelBuilderMergedCellFillUsesExistingStyledChildCells(): void
    {
        $fixture = new Spreadsheet();
        $sheet = $fixture->getActiveSheet();
        $sheet->getStyle('A2:A4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->mergeCells('A2:A4');
        $sheet->setCellValue('A2', 'North');
        $sheet->mergeCells('A5:A6');
        $sheet->setCellValue('A5', 'South');
        $path = $this->writeWorkbook($fixture, 'Xlsx');

        $loadedSheet = IOFactory::load($path)->getActiveSheet();
        self::assertTrue($loadedSheet->getCellCollection()->has('A3'));
        self::assertTrue($loadedSheet->getCellCollection()->has('A4'));

        $fillEnabledBuilder = $this->newExcelBuilderForRangeImport($path, 'A2:A6', true);
        self::assertSame([
            ['Values' => 'North'],
            ['Values' => 'North'],
            ['Values' => 'North'],
            ['Values' => 'South'],
        ], $fillEnabledBuilder->importFile($this->createStub(FileInfoInterface::class)));

        $fillDisabledBuilder = $this->newExcelBuilderForRangeImport($path, 'A2:A6', false);
        self::assertSame([
            ['Values' => 'North'],
            ['Values' => null],
            ['Values' => null],
            ['Values' => 'South'],
        ], $fillDisabledBuilder->importFile($this->createStub(FileInfoInterface::class)));
    }

    /**
     * Formula cells expose their cached result until calculation is requested again.
     */
    public function testFormulaCalculationSeparatesCachedAndRecalculatedValues(): void
    {
        $spreadsheet = new Spreadsheet();
        $cell = $spreadsheet->getActiveSheet()->getCell('A1');
        $cell->setValue('=1+2');
        $cell->setCalculatedValue(17);

        // PhpSpreadsheet 1.30.x returned the cached whole number as 17.0; 5.x returns the equivalent integer 17.
        self::assertSame(17, $cell->getOldCalculatedValue());
        self::assertSame(3, $cell->getCalculatedValue());
        self::assertSame('=1+2', $cell->getValue());
        $booleanFormulaCell = $spreadsheet->getActiveSheet()->getCell('A2');
        $booleanFormulaCell->setValue('=1=1');
        self::assertSame(true, $spreadsheet->getActiveSheet()->getCell('A2')->getCalculatedValue());
    }

    /**
     * Merges remain present and retain their anchor values after an XLSX round trip.
     */
    public function testMergedCellsPersistAcrossXlsxRoundTrip(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Merged label');
        $sheet->mergeCells('A1:C2');
        $loaded = $this->roundTrip($spreadsheet, 'Xlsx');
        $loadedSheet = $loaded->getActiveSheet();

        self::assertSame(['A1:C2'], array_values($loadedSheet->getMergeCells()));
        self::assertSame('Merged label', $loadedSheet->getCell('A1')->getValue());
        self::assertNull($loadedSheet->getCell('B2')->getValue());
    }

    /**
     * Each supported binary/text writer can be read back and checked without comparing file bytes.
     */
    public function testXlsxXlsCsvAndOdsRoundTripCellValuesAndTypes(): void
    {
        foreach ([
            'Xlsx' => ['Boolean' => 'b', 'Number' => 'n', 'Text' => 's'],
            'Xls' => ['Boolean' => 'b', 'Number' => 'n', 'Text' => 's'],
            'Ods' => ['Boolean' => 'b', 'Number' => 'n', 'Text' => 's'],
            'Csv' => ['Boolean' => 'b', 'Number' => 'n', 'Text' => 's'],
        ] as $writerType => $expectedTypes) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', true);
            $sheet->setCellValue('A2', false);
            $sheet->setCellValue('A3', 42);
            $sheet->setCellValueExplicit('A4', 'literal text', DataType::TYPE_STRING);
            $loaded = $this->roundTrip($spreadsheet, $writerType);
            $loadedSheet = $loaded->getActiveSheet();

            // PhpSpreadsheet 1.30.x read an ODS true value as false; 5.x correctly preserves true.
            self::assertSame(true, $loadedSheet->getCell('A1')->getValue(), $writerType . ' boolean value');
            self::assertSame($expectedTypes['Boolean'], $loadedSheet->getCell('A1')->getDataType(), $writerType . ' boolean type');
            self::assertSame(false, $loadedSheet->getCell('A2')->getValue(), $writerType . ' false boolean value');
            self::assertSame($expectedTypes['Boolean'], $loadedSheet->getCell('A2')->getDataType(), $writerType . ' false boolean type');
            self::assertSame(42, $loadedSheet->getCell('A3')->getValue(), $writerType . ' numeric value');
            self::assertSame($expectedTypes['Number'], $loadedSheet->getCell('A3')->getDataType(), $writerType . ' numeric type');
            self::assertSame('literal text', $loadedSheet->getCell('A4')->getValue(), $writerType . ' string value');
            self::assertSame($expectedTypes['Text'], $loadedSheet->getCell('A4')->getDataType(), $writerType . ' string type');
        }
    }

    /**
     * HTML previews render both boolean values and are compared after removing volatile markup.
     */
    public function testHtmlPreviewHasStableNormalizedBooleanOutput(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([[true, false]], null, 'A1', true);
        $path = $this->temporaryPath();
        IOFactory::createWriter($spreadsheet, 'Html')->save($path);
        $html = file_get_contents($path);
        $normalized = $this->normalizeHtml($html);

        self::assertSame(['TRUE', 'FALSE'], $this->extractHtmlCellValues($normalized));
    }

    /**
     * Template substitution changes placeholder text while retaining cell formatting.
     */
    public function testTemplateProcessingRetainsNumberFontFillAndBorderFormatting(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Hello [#NAME#]');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setName('Arial');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
        $sheet->getStyle('A1')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('B1')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->setCellValue('B1', 12.5);

        $renderer = (new ReflectionClass(PhpSpreadsheetTemplateRendererProbe::class))->newInstanceWithoutConstructor();
        $renderer->setValues(['NAME' => 'Ada']);
        $renderer->render($spreadsheet);

        self::assertSame('Hello Ada', $sheet->getCell('A1')->getValue());
        self::assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        self::assertSame('Arial', $sheet->getStyle('A1')->getFont()->getName());
        self::assertSame('FFFF0000', $sheet->getStyle('A1')->getFill()->getStartColor()->getARGB());
        self::assertSame(Border::BORDER_THIN, $sheet->getStyle('A1')->getBorders()->getBottom()->getBorderStyle());
        self::assertSame(NumberFormat::FORMAT_NUMBER_00, $sheet->getStyle('B1')->getNumberFormat()->getFormatCode());
    }

    /**
     * Renders typed placeholders and pins their XLSX, CSV, and HTML preview output under the 5.x binder.
     */
    public function testTemplatePlaceholderValuesRenderAcrossXlsxCsvAndHtml(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([[
            '[#LONG16#]',
            '[#LONG20#]',
            '[#LEADING_ZEROES#]',
            '[#DATE#]',
            '[#TRUE_VALUE#]',
            '[#FALSE_VALUE#]',
            '[#DECIMAL#]',
            '[#TEXT#]',
        ]], null, 'A1', true);

        $renderer = (new ReflectionClass(PhpSpreadsheetTemplateRendererProbe::class))->newInstanceWithoutConstructor();
        $renderer->setValues([
            'LONG16' => '1234567890123456',
            'LONG20' => '12345678901234567890',
            'LEADING_ZEROES' => '0012345',
            'DATE' => '2026-04-03',
            'TRUE_VALUE' => true,
            'FALSE_VALUE' => false,
            'DECIMAL' => '12.5',
            'TEXT' => 'plain text',
        ]);
        $renderer->render($spreadsheet);

        $loaded = $this->roundTrip($spreadsheet, 'Xlsx')->getActiveSheet();
        self::assertSame('1234567890123456', $loaded->getCell('A1')->getValue());
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('A1')->getDataType());
        self::assertSame('12345678901234567890', $loaded->getCell('B1')->getValue());
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('B1')->getDataType());
        self::assertSame('0012345', $loaded->getCell('C1')->getValue());
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('C1')->getDataType());
        self::assertSame('2026-04-03', $loaded->getCell('D1')->getValue());
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('D1')->getDataType());
        self::assertSame(1, $loaded->getCell('E1')->getValue());
        self::assertSame(DataType::TYPE_NUMERIC, $loaded->getCell('E1')->getDataType());
        self::assertNull($loaded->getCell('F1')->getValue());
        self::assertSame(DataType::TYPE_NULL, $loaded->getCell('F1')->getDataType());
        self::assertSame(12.5, $loaded->getCell('G1')->getValue());
        self::assertSame(DataType::TYPE_NUMERIC, $loaded->getCell('G1')->getDataType());
        self::assertSame('plain text', $loaded->getCell('H1')->getValue());
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('H1')->getDataType());

        $csvPath = $this->writeWorkbook($spreadsheet, 'Csv');
        $csv = file_get_contents($csvPath);
        self::assertSame([
            '1234567890123456',
            '12345678901234567890',
            '0012345',
            '2026-04-03',
            '1',
            '',
            '12.5',
            'plain text',
        ], str_getcsv(trim($csv)));

        $htmlPath = $this->temporaryPath();
        $htmlWriter = IOFactory::createWriter($spreadsheet, 'Html');
        $htmlWriter->writeAllSheets();
        $htmlWriter->save($htmlPath);
        $html = $this->normalizeHtml(file_get_contents($htmlPath));
        self::assertSame([
            '1234567890123456',
            '12345678901234567890',
            '0012345',
            '2026-04-03',
            '1',
            "\u{00A0}",
            '12.5',
            'plain text',
        ], $this->extractHtmlCellValues($html));
    }

    /**
     * List validation rules survive XLSX serialization on each cell in their applied range.
     */
    public function testDataValidationListPersistsOnEveryAppliedCell(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $validation = $sheet->getCell('B2')->getDataValidation();
        $validation->setType('list');
        $validation->setFormula1('"Open,Closed"');
        $validation->setAllowBlank(true);
        $sheet->setDataValidation('B2:B4', $validation);
        $loaded = $this->roundTrip($spreadsheet, 'Xlsx')->getActiveSheet();

        foreach (['B2', 'B3', 'B4'] as $coordinate) {
            $validation = $loaded->getCell($coordinate)->getDataValidation();
            // PhpSpreadsheet 1.30.x stored the rule only at B2; 5.x stores the validation for the whole B2:B4 range.
            $expectedType = 'list';
            self::assertSame($expectedType, $validation->getType(), $coordinate . ' validation type');
            self::assertSame('"Open,Closed"', $validation->getFormula1(), $coordinate . ' validation formula');
            self::assertTrue($validation->getAllowBlank(), $coordinate . ' blank setting');
        }
    }

    /**
     * Date serial conversion preserves the instant when a timezone is supplied and the date-only serial.
     */
    public function testDateSerialsPreserveDateTimeAndDateOnlyValues(): void
    {
        $timezone = new DateTimeZone('UTC');
        $dateTime = new DateTimeImmutable('2024-01-02 03:04:05', $timezone);
        $serial = Date::dateTimeToExcel($dateTime);
        $restored = Date::excelToDateTimeObject($serial, $timezone);

        self::assertSame('2024-01-02 03:04:05', $restored->format('Y-m-d H:i:s'));
        self::assertSame(45293.0, (float) Date::dateTimeToExcel(new DateTimeImmutable('2024-01-02', $timezone)));
        self::assertSame(1.5, (float) Date::dateTimeToExcel(new DateTimeImmutable('1899-12-31 12:00:00', $timezone)));
    }

    /**
     * Numeric-looking identifiers pin default-binder precision and leading-zero behavior across XLSX export.
     */
    public function testLongNumericIdentifiersPinDefaultBinderValueAndType(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '12345678901234567890');
        $sheet->setCellValue('A2', '0012345');
        $sheet->setCellValue('A3', '1234567890123456');
        $loaded = $this->roundTrip($spreadsheet, 'Xlsx')->getActiveSheet();

        self::assertSame('12345678901234567890', $loaded->getCell('A1')->getValue());
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('A1')->getDataType());
        self::assertSame('0012345', $loaded->getCell('A2')->getValue());
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('A2')->getDataType());
        // PhpSpreadsheet 1.30.x bound this value as an integer; 5.x preserves the original 16-digit text identifier.
        self::assertSame('1234567890123456', $loaded->getCell('A3')->getValue());
        // PhpSpreadsheet 1.30.x classified the identifier as numeric; 5.x classifies it as a string to avoid precision loss.
        self::assertSame(DataType::TYPE_STRING, $loaded->getCell('A3')->getDataType());
    }

    /**
     * Gantt workbooks retain values, semantic styles, merged headers, and configured widths after export.
     */
    public function testGanttExportPreservesValuesStylesMergesAndColumnWidths(): void
    {
        $path = $this->temporaryPath();
        $translations = [
            'SHEET_TITLE' => 'Timeline',
            'GANTT' => 'Gantt',
            'YEAR' => 'Year',
            'QUARTER' => 'Quarter',
            'MONTH' => 'Month',
            'CALENDAR_WEEK' => 'Week',
            'EXECUTION_YEAR' => 'Execution year',
        ];
        for ($month = 1; $month <= 12; $month++) {
            $translations[sprintf('MONTH_%02d', $month)] = sprintf('Month %02d', $month);
        }

        (new GanttXlsxBuilder(
            [],
            true,
            0.5,
            1,
            2,
            [],
            $translations,
            ['#112233', '#445566']
        ))->build([[
            'columns' => ['UID' => '1234567890123456', 'Name' => 'Deployment'],
            'tasks' => [[
                'start' => '2024-01-03',
                'end' => '2024-01-05',
                'title' => 'Implementation',
                'color' => '#00AA00',
            ]],
        ]], $path);
        $sheet = IOFactory::load($path)->getActiveSheet();

        self::assertSame('UID', $sheet->getCell('C1')->getValue());
        self::assertSame('UID', $sheet->getCell('A2')->getValue());
        self::assertSame('Name', $sheet->getCell('B2')->getValue());
        // PhpSpreadsheet 1.30.x exported this UID as an integer; 5.x preserves its 16-digit text value.
        self::assertSame('1234567890123456', $sheet->getCell('A6')->getValue());
        // PhpSpreadsheet 1.30.x marked this UID numeric; 5.x stores it as text to avoid identifier precision loss.
        self::assertSame(DataType::TYPE_STRING, $sheet->getCell('A6')->getDataType());
        // PhpSpreadsheet 1.30.x wrote this corresponding Gantt cell as an integer; 5.x keeps the identifier as text.
        self::assertSame('1234567890123456', $sheet->getCell('C6')->getValue());
        self::assertSame('Deployment', $sheet->getCell('B6')->getValue());
        self::assertSame('Implementation', $sheet->getCell('E6')->getValue());
        self::assertContains('A1:B1', array_values($sheet->getMergeCells()));
        self::assertContains('C1:C5', array_values($sheet->getMergeCells()));
        self::assertSame('FF112233', $sheet->getCell('A2')->getStyle()->getFill()->getStartColor()->getARGB());
        self::assertSame(13.0, $sheet->getColumnDimension('A')->getWidth());
        self::assertSame(16.2, $sheet->getColumnDimension('C')->getWidth());
        self::assertSame(2.0, $sheet->getColumnDimension('E')->getWidth());
    }

    /**
     * Creates an ExcelBuilder probe that exercises Core's actual workbook import path.
     *
     * @param list<object> $attributes
     */
    private function newExcelBuilder(string $path, MetaObjectInterface $object, array $attributes): ExcelBuilderCompatibilityProbe
    {
        $builder = (new ReflectionClass(ExcelBuilderCompatibilityProbe::class))->newInstanceWithoutConstructor();
        $builder->configure($path, $object, $attributes);
        return $builder;
    }

    /**
     * Creates an ExcelBuilder import fixture with an optional merged-cell fill override.
     *
     * @param list<object> $attributes
     */
    private function newExcelBuilderForRangeImport(string $path, string $range, ?bool $fillMergedCells = null): ExcelBuilderCompatibilityProbe
    {
        $object = $this->createStub(MetaObjectInterface::class);
        $object->method('getDataAddress')->willReturn($path);
        $object->method('getDataAddressProperty')->willReturnCallback(
            static function (string $property) use ($fillMergedCells): ?bool {
                if ($property === ExcelBuilder::DAP_EXCEL_FILL_MERGED_CELLS) {
                    return $fillMergedCells;
                }
                return null;
            }
        );

        return $this->newExcelBuilder($path, $object, [$this->newAttribute('Values', $range)]);
    }

    /**
     * Creates a minimal query-part stub for one imported Excel column.
     */
    private function newAttribute(string $key, string $address): object
    {
        $attribute = $this->createStub(\exface\Core\CommonLogic\QueryBuilder\QueryPartSelect::class);
        $attribute->method('getColumnKey')->willReturn($key);
        $attribute->method('getDataAddress')->willReturn($address);
        $attribute->method('getDataType')->willReturn($this->createStub(\exface\Core\Interfaces\DataTypes\DataTypeInterface::class));
        $attribute->method('getAlias')->willReturn($key);
        return $attribute;
    }

    /**
     * Creates and registers a temporary file path for a workbook fixture.
     */
    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpspreadsheet_');
        if ($path === false) {
            self::fail('Unable to allocate a temporary spreadsheet fixture.');
        }
        $this->temporaryFiles[] = $path;
        return $path;
    }

    /**
     * Writes a workbook with an explicit writer and returns its temporary path.
     */
    private function writeWorkbook(Spreadsheet $spreadsheet, string $writerType): string
    {
        $path = $this->temporaryPath();
        IOFactory::createWriter($spreadsheet, $writerType)->save($path);
        return $path;
    }

    /**
     * Reads back a generated workbook so assertions compare document semantics, not binary bytes.
     */
    private function roundTrip(Spreadsheet $spreadsheet, string $writerType): Spreadsheet
    {
        $path = $this->writeWorkbook($spreadsheet, $writerType);
        return IOFactory::createReader($writerType)->load($path);
    }

    /**
     * Removes unstable identifiers, timestamps, and whitespace before HTML assertions.
     */
    private function normalizeHtml(string $html): string
    {
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/\s+id="[^"]*"/i', '', $html);
        $html = preg_replace('/<meta\b[^>]*>/i', '', $html);
        return trim($html);
    }

    /**
     * Extracts visible cell text from normalized HTML output for stable writer assertions.
     *
     * @return list<string>
     */
    private function extractHtmlCellValues(string $html): array
    {
        $document = new \DOMDocument();
        $document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $values = [];
        foreach ($xpath->query('//table//td') as $cell) {
            $values[] = trim($cell->textContent);
        }
        return $values;
    }
}

/**
 * Exposes protected ExcelBuilder operations to characterize their existing behavior.
 */
final class ExcelBuilderCompatibilityProbe extends ExcelBuilder
{
    private string $fixturePath;
    private MetaObjectInterface $fixtureObject;
    private array $fixtureAttributes;

    /**
     * Supplies fixture state without initializing the query-builder runtime.
     *
     * @param list<object> $attributes
     */
    public function configure(string $path, MetaObjectInterface $object, array $attributes): void
    {
        $this->fixturePath = $path;
        $this->fixtureObject = $object;
        $this->fixtureAttributes = $attributes;
        (new ReflectionProperty(ExcelBuilder::class, 'ranges'))->setValue($this, [
            'PERSON' => null,
            'IDENTIFIER' => null,
        ]);
    }

    /**
     * Uses the fixture's real workbook path for the inherited reader.
     */
    protected function getStreamablePath(FileInfoInterface $fileInfo): string
    {
        return $this->fixturePath;
    }

    /**
     * Supplies the fixture's query columns to ExcelBuilder's import loop.
     */
    protected function getAttributes()
    {
        return $this->fixtureAttributes;
    }

    /**
     * Supplies the fixture object required by ExcelBuilder's data-address options.
     */
    public function getMainObject(): MetaObjectInterface
    {
        return $this->fixtureObject;
    }

    /**
     * Invokes Core's spreadsheet reader for the configured fixture.
     *
     * @return array<array<string,mixed>>
     */
    public function importFile(FileInfoInterface $fileInfo): array
    {
        return $this->buildResultRows($fileInfo);
    }

    /**
     * Exposes range evaluation while retaining the importer's indexed array shape.
     */
    public function readRange(Worksheet $sheet, string $range): array
    {
        return $this->rangeToArray($sheet, $range, null, true, false, true);
    }

    /**
     * Exposes merged-cell-aware range evaluation.
     */
    public function readMergedRange(Worksheet $sheet, string $range): array
    {
        return $this->rangeToArrayUnmerged($sheet, $range, null, true, false, true);
    }

    /**
     * Exposes header-based column address resolution.
     */
    public function resolveHeader(Worksheet $sheet, string $address): ?string
    {
        $property = new ReflectionProperty(ExcelBuilder::class, 'ranges');
        $property->setValue($this, ['IDENTIFIER' => null]);
        return $this->addressToRange($address, $sheet);
    }
}

/**
 * Supplies fixed placeholder values without requiring a booted Core workbench.
 */
final class PhpSpreadsheetTemplateRendererProbe extends BracketHashXlsxTemplateRenderer
{
    private array $fixtureValues = [];

    /**
     * Supplies values resolved from the test template.
     *
     * @param array<string,mixed> $values
     */
    public function setValues(array $values): void
    {
        $this->fixtureValues = $values;
    }

    /**
     * Replaces workbench-backed resolution with fixed characterization values.
     *
     * @param list<string> $placeholders
     * @return array<string,mixed>
     */
    protected function getPlaceholderValues(array $placeholders): array
    {
        return $this->fixtureValues;
    }
}
