<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\WidgetLinkCoordinate;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataTable;
use exface\Core\Widgets\Input;
use PHPUnit\Framework\TestCase;

/**
 * Covers coordinate parsing, column resolution and widget-link round trips without a model DB.
 */
class WidgetLinkCoordinateTest extends TestCase
{
    /**
     * Separates a row selector's aggregator from a column's own aggregation expression.
     *
     * @return void
     */
    public function testAggregatedCoordinatesRetainColumnExpressions() : void
    {
        $coordinate = new WidgetLinkCoordinate('AMOUNT:COUNT$~loaded:sum');
        $this->assertSame('AMOUNT:COUNT', $coordinate->getColumnCoordinate());
        $this->assertSame('~loaded', $coordinate->getRowCoordinate());
        $this->assertSame('SUM', $coordinate->getAggregator());
        $this->assertTrue($coordinate->isTargetingLoadedRows());
        $this->assertFalse($coordinate->isTargetingSelection());
        $this->assertSame('AMOUNT:COUNT$~loaded:SUM', (string) $coordinate);

        $selected = new WidgetLinkCoordinate('NAME$~selected:LIST');
        $this->assertTrue($selected->isTargetingSelection());
        $this->assertFalse($selected->isTargetingLoadedRows());
        $this->assertSame('LIST', $selected->getAggregator());
    }

    /**
     * Leaves implicit and custom row contexts undecided while identifying explicit numeric rows.
     *
     * @return void
     */
    public function testImplicitAndCustomCoordinatesDoNotGuessSelection() : void
    {
        foreach (['', 'NAME', '~start_date', '~position$latitude'] as $expression) {
            $coordinate = new WidgetLinkCoordinate($expression);
            $this->assertNull($coordinate->isTargetingSelection());
            $this->assertNull($coordinate->isTargetingLoadedRows());
            $this->assertSame($expression, (string) $coordinate);
        }
        $custom = new WidgetLinkCoordinate('~position$latitude');
        $this->assertTrue($custom->isTargetingCustomCoordinate());
        $this->assertFalse($custom->isTargetingColumn());
        $this->assertSame('latitude', $custom->getRowCoordinate());

        $numeric = new WidgetLinkCoordinate('NAME$0');
        $this->assertSame(0, $numeric->getRowCoordinate());
        $this->assertTrue($numeric->isTargetingRowNumber());
        $this->assertFalse($numeric->isTargetingSelection());
        $this->assertFalse($numeric->isTargetingLoadedRows());
    }

    /**
     * Rejects incomplete selectors and aggregators that cannot be evaluated by the shared JS helper.
     *
     * @return void
     */
    public function testMalformedCoordinatesFailExplicitly() : void
    {
        foreach (['NAME$', 'NAME$:SUM', 'NAME$~loaded:', 'NAME$~loaded:UNKNOWN', 'NAME$999999999999999999999999'] as $expression) {
            try {
                new WidgetLinkCoordinate($expression);
                $this->fail('Expected invalid coordinate: ' . $expression);
            } catch (InvalidArgumentException $error) {
                $this->assertNotSame('', $error->getMessage());
            }
        }
    }

    /**
     * Prefers an exact data-column name, with attribute-alias fallback and UID defaults.
     *
     * @return void
     */
    public function testFindColumnUsesNamesBeforeAliases() : void
    {
        $workbench = new Workbench();
        $page = UiPageFactory::createEmpty($workbench);
        $widget = new DataTable($page);
        $widget->setMetaObject(new MetaObject(new Model($workbench)));
        $namedColumn = new DataColumn($page, $widget);
        $namedColumn->setId('name');
        $namedColumn->setDataColumnName('NAME');
        $aliasColumn = new DataColumn($page, $widget);
        $aliasColumn->setDataColumnName('DISPLAY_NAME');
        $aliasColumn->setAttributeAlias('NAME');
        $widget->addColumn($namedColumn);
        $widget->addColumn($aliasColumn);
        $widget->setUidColumnId('name');

        $this->assertSame($namedColumn, (new WidgetLinkCoordinate('NAME'))->findColumn($widget));
        $aliasColumn->setAttributeAlias('TITLE');
        $this->assertSame($aliasColumn, (new WidgetLinkCoordinate('TITLE'))->findColumn($widget));
        $this->assertSame($namedColumn, (new WidgetLinkCoordinate())->findColumn($widget));
        $this->assertNull((new WidgetLinkCoordinate('~rowcount'))->findColumn($widget));
        $this->assertNull((new WidgetLinkCoordinate('MISSING'))->findColumn($widget));
    }

    /**
     * Preserves aggregated and legacy coordinates when links are exported and imported.
     *
     * @return void
     */
    public function testWidgetLinksRoundTripCoordinates() : void
    {
        $page = UiPageFactory::createEmpty(new Workbench());
        $source = new Input($page);
        foreach (['NAME$~loaded:LIST', 'AMOUNT$~selected:SUM', 'NAME$2', '~start_date'] as $expression) {
            $link = WidgetLinkFactory::createFromWidget($source, '~self!' . $expression . '?');
            $this->assertTrue($link->hasTargetCoordinate());
            $this->assertSame($expression, (string) $link->getTargetCoordinate());
            $copy = WidgetLinkFactory::createFromWidget($source, $link->exportUxonObject());
            $this->assertSame($expression, (string) $copy->getTargetCoordinate());
            $this->assertTrue($copy->isOnlyIfNotEmpty());
        }
        $plain = WidgetLinkFactory::createFromWidget($source, '~self');
        $this->assertFalse($plain->hasTargetCoordinate());
        $this->assertNull($plain->getTargetCoordinate());
        $legacy = WidgetLinkFactory::createFromWidget($source, new UxonObject([
            'widget_id' => '~self', 'column_id' => 'NAME', 'row_number' => 2
        ]));
        $this->assertSame('NAME$2', (string) $legacy->getTargetCoordinate());
        $this->assertSame(2, $legacy->getTargetRowNumber());
    }
}
