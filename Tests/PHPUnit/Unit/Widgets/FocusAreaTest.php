<?php

namespace exface\Core\Tests\PHPUnit\Unit\Widgets;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Widgets\ContextBar;
use exface\Core\Widgets\DataTable;
use exface\Core\Widgets\Dialog;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\Parts\FocusArea;
use PHPUnit\Framework\TestCase;

/**
 * Covers parsing of tour focus areas (`widgetId~area`) and resolving their widgets within a UI screen.
 */
class FocusAreaTest extends TestCase
{
    /**
     * The widget id and the area are split at the first `~`; the rest belongs to the area.
     */
    public function testSplitsWidgetIdAndArea() : void
    {
        $table = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()->getMock();
        $page = $this->createMock(UiPageInterface::class);
        $page->expects(self::once())->method('getWidget')->with('myTable')->willReturn($table);

        $area = new FocusArea($page, ' myTable~column.COL$2 ');

        self::assertSame($table, $area->getWidget());
        self::assertSame($page, $area->getScreen());
        self::assertSame('myTable~column.COL$2', $area->getIdRelativeToScreen());
        self::assertSame('column.COL$2', $area->getIdRelativeToWidget());
        self::assertFalse($area->isEntireWidget());
    }

    /**
     * Without an area (or with an empty one) the focus area is the entire widget.
     */
    public function testMissingOrEmptyAreaMeansEntireWidget() : void
    {
        $table = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()->getMock();
        $page = $this->createMock(UiPageInterface::class);
        $page->method('getWidget')->with('myTable')->willReturn($table);

        foreach (['myTable', 'myTable~'] as $string) {
            $area = new FocusArea($page, $string);
            self::assertSame($table, $area->getWidget());
            self::assertNull($area->getIdRelativeToWidget());
            self::assertTrue($area->isEntireWidget());
        }
    }

    /**
     * `ContextBar` always resolves to the context bar of the page.
     */
    public function testContextBarIdResolvesToContextBar() : void
    {
        $contextBar = $this->getMockBuilder(ContextBar::class)->disableOriginalConstructor()->getMock();
        $page = $this->createMock(UiPageInterface::class);
        $page->method('getContextBar')->willReturn($contextBar);
        $page->expects(self::never())->method('getWidget');

        $area = new FocusArea($page, 'ContextBar~user');

        self::assertSame($contextBar, $area->getWidget());
        self::assertSame('user', $area->getIdRelativeToWidget());
    }

    /**
     * Filters are replaced by their input widgets because they are rendered by them.
     */
    public function testFilterIsReplacedByItsInput() : void
    {
        $input = $this->getMockBuilder(Input::class)->disableOriginalConstructor()->getMock();
        $filter = $this->getMockBuilder(Filter::class)->disableOriginalConstructor()
            ->onlyMethods(['getInputWidget'])->getMock();
        $filter->method('getInputWidget')->willReturn($input);
        $page = $this->createMock(UiPageInterface::class);
        $page->method('getWidget')->willReturn($filter);

        self::assertSame($input, (new FocusArea($page, 'myFilter'))->getWidget());
    }

    /**
     * Inside a dialog, the id is resolved within the id space of the dialog unless it already includes it.
     */
    public function testIdIsResolvedWithinDialogIdSpace() : void
    {
        $table = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()->getMock();
        $page = $this->createMock(UiPageInterface::class);
        $page->method('getWidgetIdSpaceSeparator')->willReturn('.');
        $dialog = $this->getMockBuilder(Dialog::class)->disableOriginalConstructor()
            ->onlyMethods(['getIdSpace', 'getPage'])->getMock();
        $dialog->method('getIdSpace')->willReturn('myDialog');
        $dialog->method('getPage')->willReturn($page);
        $page->expects(self::exactly(2))->method('getWidget')->with('myDialog.myTable', $dialog)->willReturn($table);

        self::assertSame($table, (new FocusArea($dialog, 'myTable~header'))->getWidget());
        self::assertSame($table, (new FocusArea($dialog, 'myDialog.myTable~header'))->getWidget());
    }
}
