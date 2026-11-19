<?php

namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\Utils;

use exface\Core\CommonLogic\Utils\GanttXlsxBuilder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Borders;
use PHPUnit\Framework\TestCase;

/**
 * Verifies task interval handling in generated and reopened Gantt workbooks.
 */
class GanttXlsxBuilderTest extends TestCase
{
    /**
     * Reversed tasks neither draw bars, create lanes, nor extend an otherwise valid timeline.
     */
    public function testReversedTaskLeavesValidTaskUnchanged(): void
    {
        $valid = ['start' => '2026-01-05', 'end' => '2026-01-12', 'title' => 'Valid'];
        $expected = $this->buildWorkbook([
            ['columns' => ['Location' => 'A'], 'tasks' => [$valid]],
        ]);
        try {
            $actual = $this->buildWorkbook([
                ['columns' => ['Location' => 'A'], 'tasks' => [
                    $valid,
                    ['start' => '2030-01-01', 'end' => '1990-01-01', 'title' => 'Reversed'],
                ]],
            ]);
            try {
                self::assertSame($expected->getActiveSheet()->toArray(), $actual->getActiveSheet()->toArray());
                self::assertSame(6, $actual->getActiveSheet()->getHighestRow());
                self::assertSame('D6', $actual->getActiveSheet()->getFreezePane());
                self::assertSame('Valid', $actual->getActiveSheet()->getCell('E6')->getValue());
                self::assertSame(
                    $expected->getActiveSheet()->getStyle('E6')->getFill()->getStartColor()->getARGB(),
                    $actual->getActiveSheet()->getStyle('E6')->getFill()->getStartColor()->getARGB()
                );
            } finally {
                $actual->disconnectWorksheets();
            }
        } finally {
            $expected->disconnectWorksheets();
        }
    }

    /**
     * An entirely reversed task list retains business values without weekly columns or bars.
     */
    public function testOnlyReversedTasksKeepBusinessRowWithoutTimeline(): void
    {
        $workbook = $this->buildWorkbook([
            ['columns' => ['Location' => 'A'], 'tasks' => [
                ['start' => '2026-02-02', 'end' => '2026-01-05', 'title' => 'Reversed'],
            ]],
        ]);
        try {
            $sheet = $workbook->getActiveSheet();
            self::assertSame('C', $sheet->getHighestColumn());
            self::assertSame(6, $sheet->getHighestRow());
            self::assertSame('A', $sheet->getCell('A6')->getValue());
            self::assertSame('A', $sheet->getCell('B6')->getValue());
            self::assertNull($sheet->getCell('C6')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    /**
     * Equal dates, inferred dates, and overlapping valid tasks retain their rendering behavior.
     */
    public function testEqualAndMissingDatesStillProduceValidBars(): void
    {
        $workbook = $this->buildWorkbook([
            ['columns' => ['Location' => 'A'], 'tasks' => [
                ['start' => '2026-01-05', 'end' => '2026-01-05', 'title' => 'Equal'],
                ['start' => '2026-01-05', 'title' => 'Start only'],
                ['end' => '2026-01-07', 'title' => 'End only'],
            ]],
        ]);
        try {
            $sheet = $workbook->getActiveSheet();
            self::assertSame(8, $sheet->getHighestRow());
            self::assertSame('End only', $sheet->getCell('E6')->getValue());
            self::assertSame('Equal', $sheet->getCell('E7')->getValue());
            self::assertSame('Start only', $sheet->getCell('E8')->getValue());
            self::assertSame(Borders::DIAGONAL_UP, $sheet->getStyle('E6')->getBorders()->getDiagonalDirection());
            self::assertSame(Borders::DIAGONAL_NONE, $sheet->getStyle('E7')->getBorders()->getDiagonalDirection());
            self::assertSame(Borders::DIAGONAL_UP, $sheet->getStyle('E8')->getBorders()->getDiagonalDirection());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    /**
     * Exercises the public export contract and reloads the XLSX while cleaning up the temporary file.
     *
     * @param list<array<string, mixed>> $items
     */
    private function buildWorkbook(array $items): Spreadsheet
    {
        $keys = ['SHEET_TITLE', 'GANTT', 'YEAR', 'QUARTER', 'MONTH', 'CALENDAR_WEEK', 'EXECUTION_YEAR'];
        for ($month = 1; $month <= 12; $month++) {
            $keys[] = 'MONTH_' . str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        }
        $path = tempnam(sys_get_temp_dir(), 'gantt-test-');
        if ($path === false) {
            self::fail('Cannot create a temporary Gantt workbook.');
        }
        try {
            $builder = new GanttXlsxBuilder(
                freezeColumns: 3,
                translations: array_combine($keys, $keys)
            );
            $builder->build($items, $path);
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
