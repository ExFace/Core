<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\WidgetLinkCoordinate;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataTable;
use PHPUnit\Framework\TestCase;

/**
 * Covers UID coordinates resolved before a table initializes its lazy UID column.
 */
class WidgetLinkCoordinateUidTest extends TestCase
{
    /**
     * Resolves an implicit UID without accepting unrelated missing column selectors.
     *
     * @return void
     */
    public function testResolvesLazyUidColumn() : void
    {
        $workbench = new Workbench();
        $object = new MetaObject(new Model($workbench));
        $object->setAlias('LINK_TARGET');
        $attribute = new Attribute($object, 'UID', 'UID');
        $attribute->setDataType(DataTypeFactory::createFromPrototype($workbench, StringDataType::class));
        $attribute->setSystem(true);
        $object->addAttribute($attribute);
        $object->setUidAttributeAlias('UID');

        $page = UiPageFactory::createEmpty($workbench);
        $widget = new DataTable($page);
        $widget->setMetaObject($object);
        $displayColumn = new DataColumn($page, $widget);
        $displayColumn->setDataColumnName('DISPLAY_NAME');
        $widget->addColumn($displayColumn);

        $this->assertNull($widget->getColumnByDataColumnName('UID'));
        $uidColumn = (new WidgetLinkCoordinate('UID'))->findColumn($widget);
        $this->assertInstanceOf(DataColumn::class, $uidColumn);
        $this->assertSame('UID', $uidColumn->getDataColumnName());
        $this->assertSame($uidColumn, $widget->getUidColumn());
        $this->assertSame($uidColumn, (new WidgetLinkCoordinate('UID$~loaded:LIST'))->findColumn($widget));
        $this->assertSame($displayColumn, (new WidgetLinkCoordinate('DISPLAY_NAME'))->findColumn($widget));
        $this->assertNull((new WidgetLinkCoordinate('MISSING'))->findColumn($widget));
    }
}
