<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataSpreadSheet;
use PHPUnit\Framework\TestCase;

class JExcelSortIndicatorsTest extends TestCase
{
    private function generateIndicators(array $aliases, array $sorters) : string
    {
        $columns = [];
        foreach ($aliases as $alias) {
            $column = $this->createMock(DataColumn::class);
            $column->method('getAttributeAlias')->willReturn($alias);
            $columns[] = $column;
        }
        $widget = $this->createMock(DataSpreadSheet::class);
        $widget->method('getColumns')->willReturn($columns);
        $widget->method('getSorters')->willReturn(array_map(function(array $sorter) {
            return new UxonObject($sorter);
        }, $sorters));

        $element = new class($widget) {
            use JExcelTrait;

            private DataSpreadSheet $widget;

            public function __construct(DataSpreadSheet $widget)
            {
                $this->widget = $widget;
            }

            public function getWidget() : DataSpreadSheet
            {
                return $this->widget;
            }

            public function generate() : string
            {
                return $this->buildJsJExcelSortIndicators('oSpreadsheet');
            }
        };

        return $element->generate();
    }

    public function testMatchesAttributeAliasesIncludingDuplicatesAndRelatedAttributes() : void
    {
        $js = $this->generateIndicators([null, 'NAME', 'PARENT__NAME', 'NAME'], [
            ['attribute_alias' => 'MISSING', 'direction' => 'ASC'],
            ['attribute_alias' => 'NAME', 'direction' => 'asc'],
            ['attribute_alias' => 'PARENT__NAME', 'direction' => 'desc']
        ]);

        $this->assertStringContainsString('var aSortClasses = [null,"arrow-down","arrow-up","arrow-down"];', $js);
        $this->assertStringContainsString("oHeader.classList.remove('arrow-up', 'arrow-down');", $js);
        $this->assertStringNotContainsString('orderBy(', $js);
        $this->assertStringNotContainsString('updateOrder(', $js);
    }

    public function testNoSortersClearsStaleIndicators() : void
    {
        $js = $this->generateIndicators(['NAME', 'DATE'], []);

        $this->assertStringContainsString('var aSortClasses = [null,null];', $js);
        $this->assertStringContainsString("oHeader.classList.remove('arrow-up', 'arrow-down');", $js);
    }
}
