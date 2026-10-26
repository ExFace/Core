<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelDisplayTrait;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;
use exface\Core\Widgets\ColorIndicator;
use exface\Core\Widgets\DataColumn;
use PHPUnit\Framework\TestCase;

/**
 * Checks binding edge cases locally, without database or Workbench dependencies.
 */
class JExcelDisplayBindingsTest extends TestCase
{
    /**
     * Only static expressions may be evaluated during generation without row data.
     *
     * @return void
     */
    public function testPreservesLiteralColorsWithoutEvaluatingNonStaticExpressions() : void
    {
        foreach (['red' => false, '#aabbcc' => true, '~OK' => true] as $color => $static) {
            $expression = $this->createMock(ExpressionInterface::class);
            $expression->method('isStatic')->willReturn($static);
            if ($static) {
                $expression->expects($this->once())->method('evaluate')->willReturn($color);
            } else {
                $expression->expects($this->never())->method('evaluate');
            }
            $binding = $this->createMock(WidgetPropertyBindingInterface::class);
            $binding->method('hasValue')->willReturn(true);
            $binding->method('isBoundToDataColumn')->willReturn(false);
            $binding->method('getValueExpression')->willReturn($expression);
            $binding->method('getValue')->willReturn($color);
            $cell = $this->createMock(ColorIndicator::class);
            $cell->method('getColorBinding')->willReturn($binding);
            $cell->method('getDisableFormatting')->willReturn(true);
            $cell->method('getHintScale')->willReturn($this->createMock(WidgetPropertyScaleInterface::class));
            $column = $this->createMock(DataColumn::class);
            $column->method('getCellWidget')->willReturn($cell);
            $generator = new class {
                use JExcelDisplayTrait { buildJsJExcelDisplayOptions as public options; }
            };
            $this->assertStringContainsString('"color":' . json_encode($color), $generator->options($column));
        }
    }

    /**
     * Repeated generation must reuse auxiliary columns instead of appending duplicates.
     *
     * @return void
     */
    public function testAddsMissingDependencyOnceWithUnformattedDisplayValues() : void
    {
        $binding = $this->createMock(WidgetPropertyBindingInterface::class);
        $binding->method('getDataColumnName')->willReturn('STATUS__COLOR');
        $cell = $this->createMock(ColorIndicator::class);
        $cell->method('getColorBinding')->willReturn($binding);
        $column = $this->createMock(DataColumn::class);
        $column->method('getCellWidget')->willReturn($cell);
        $column->method('getDataColumnName')->willReturn('STATUS');
        $hidden = $this->createMock(DataColumn::class);
        $hidden->method('getDataColumnName')->willReturn('STATUS__COLOR');
        $owner = new class($column, $hidden) {
            private array $columns;
            private DataColumn $hidden;
            private ?UxonObject $configuration = null;

            /** @param DataColumn $column @param DataColumn $hidden */
            public function __construct(DataColumn $column, DataColumn $hidden)
            {
                $this->columns = [$column];
                $this->hidden = $hidden;
            }

            /** @return DataColumn[] */
            public function getColumns() : array { return $this->columns; }

            /** @param string $name @return DataColumn|null */
            public function getColumnByDataColumnName(string $name) : ?DataColumn
            {
                foreach ($this->columns as $column) {
                    if ($column->getDataColumnName() === $name) return $column;
                }
                return null;
            }

            /** @param UxonObject $configuration @return DataColumn */
            public function createColumnFromUxon(UxonObject $configuration) : DataColumn
            {
                $this->configuration = $configuration;
                return $this->hidden;
            }

            /** @param DataColumn $column @return void */
            public function addColumn(DataColumn $column) : void { $this->columns[] = $column; }

            /** @return UxonObject|null */
            public function getConfiguration() : ?UxonObject { return $this->configuration; }
        };
        $generator = new class($owner) {
            use JExcelDisplayTrait { prepareJExcelDisplayColumns as public prepare; }
            private object $owner;

            /** @param object $owner */
            public function __construct(object $owner) { $this->owner = $owner; }

            /** @return object */
            public function getWidget() : object { return $this->owner; }
        };
        $generator->prepare();
        $generator->prepare();
        $this->assertCount(2, $owner->getColumns());
        $configuration = $owner->getConfiguration();
        $this->assertSame('STATUS__COLOR', $configuration->getProperty('data_column_name'));
        $this->assertTrue($configuration->getProperty('hidden'));
        $this->assertFalse($configuration->getProperty('editable'));
        $this->assertSame(['widget_type' => 'Display', 'disable_formatting' => true], $configuration->getProperty('cell_widget')->toArray());
    }
}