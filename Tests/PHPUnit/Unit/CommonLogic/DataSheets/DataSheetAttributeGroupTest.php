<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\DataSheets;

use exface\Core\CommonLogic\DataSheets\DataSheet;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\AttributeGroup;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\Exceptions\Model\MetaAttributeGroupNotFoundError;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Factories\DataSheetFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers sheet-local attribute group expansion using an in-memory model and real Workbench.
 */
class DataSheetAttributeGroupTest extends TestCase
{
    /**
     * Group templates mix with ordinary columns without overriding earlier definitions.
     */
    public function testEditableGroupPreservesTemplatesDuplicatesAndSourceUxon() : void
    {
        $sheet = $this->createSheet();
        $uxon = new UxonObject([
            'columns' => [
                ['attribute_alias' => 'NAME'],
                ['attribute_group_alias' => '~EDITABLE', 'hidden' => true],
                'ID'
            ],
            'rows' => [['ID' => '1', 'NAME' => 'Example', 'DESCRIPTION' => 'Details']]
        ]);
        $source = $uxon->toArray();
        $sheet->importUxonObject($uxon);

        $this->assertSame(['NAME', 'DESCRIPTION', 'ID'], array_keys($sheet->getColumns()->getAll()));
        $this->assertFalse($sheet->getColumns()->get('NAME')->getHidden());
        $this->assertTrue($sheet->getColumns()->get('DESCRIPTION')->getHidden());
        $this->assertSame('Details', $sheet->getCellValue('DESCRIPTION', 0));
        $this->assertSame($source, $uxon->toArray());

        $export = $sheet->exportUxonObject();
        foreach ($export->getProperty('columns') as $column) {
            $this->assertFalse($column->hasProperty('attribute_group_alias'));
        }
        $copy = DataSheetFactory::createFromObject($sheet->getMetaObject());
        $copy->importUxonObject($export);
        $this->assertSame($sheet->getColumns()->getColumnsExpressions(), $copy->getColumns()->getColumnsExpressions());
        $this->assertSame($sheet->getRows(), $copy->getRows());
    }

    /**
     * Named groups retain their own order, and suffixes apply to each attribute.
     */
    public function testNamedGroupPreservesOrderAndAppliesAggregator() : void
    {
        $sheet = $this->createSheet();
        $object = $sheet->getMetaObject();
        $group = new AttributeGroup($sheet->getWorkbench(), $object);
        $group->setAlias('my.App.DETAILS');
        $group->add($object->getAttribute('DESCRIPTION'));
        $group->add($object->getAttribute('NAME'));
        $object->addAttributeGroup($group);

        $sheet->importUxonObject(new UxonObject([
            'columns' => [['attribute_group_alias' => 'my.App.DETAILS:LIST_DISTINCT']]
        ]));

        $this->assertSame(
            ['DESCRIPTION:LIST_DISTINCT', 'NAME:LIST_DISTINCT'],
            array_values($sheet->getColumns()->getColumnsExpressions())
        );
        $this->assertSame(['DESCRIPTION', 'NAME'], array_keys($group->getAttributes()));
    }

    /**
     * Modifier colons must stay in the group selector, even with an aggregator suffix.
     */
    public function testSortedCombinedGroupWithAndWithoutAggregator() : void
    {
        foreach (['', ':LIST_DISTINCT'] as $suffix) {
            $sheet = $this->createSheet();
            $sheet->importUxonObject(new UxonObject([
                'columns' => [['attribute_group_alias' => '~EDITABLE~VISIBLE[:SORT(alias, asc)]' . $suffix]]
            ]));

            $this->assertSame(
                ['DESCRIPTION' . $suffix, 'NAME' . $suffix],
                array_values($sheet->getColumns()->getColumnsExpressions())
            );
        }
    }

    /**
     * Related groups keep full paths, while relation keys remain keys rather than labels.
     */
    public function testRelatedGroupsPreservePathsAndRawRelationKeys() : void
    {
        $sheet = $this->createSheet();
        $object = $sheet->getMetaObject();
        $key = $object->getAttribute('ID');
        $key->setRelationFlag(true);
        $object->setLabelAttributeAlias('NAME');
        $relation = new Relation(
            $sheet->getWorkbench(),
            RelationCardinalityDataType::N_TO_ONE($sheet->getWorkbench()),
            $key->getId(),
            'ID',
            '',
            $object,
            $key,
            $key->getId()
        );
        $rightObject = new ReflectionProperty(Relation::class, 'rightObject');
        $rightObject->setAccessible(true);
        $rightObject->setValue($relation, $object);
        $object->addRelation($relation);

        $sheet->importUxonObject(new UxonObject([
            'columns' => [
                ['attribute_group_alias' => '~ALL'],
                ['attribute_group_alias' => 'ID__~EDITABLE:LIST_DISTINCT']
            ]
        ]));
        $this->assertSame(
            ['ID', 'NAME', 'DESCRIPTION', 'ID__NAME:LIST_DISTINCT', 'ID__DESCRIPTION:LIST_DISTINCT'],
            array_values($sheet->getColumns()->getColumnsExpressions())
        );
    }

    /**
     * Valid empty groups add no columns and do not interrupt following entries.
     */
    public function testEmptyGroupAddsNoColumns() : void
    {
        $sheet = $this->createSheet();
        $sheet->importUxonObject(new UxonObject([
            'columns' => [['attribute_group_alias' => '~REQUIRED'], 'NAME']
        ]));
        $this->assertSame(['NAME'], array_keys($sheet->getColumns()->getAll()));
    }

    /**
     * Unknown groups must surface the model error rather than silently adding nothing.
     */
    public function testUnknownGroupFails() : void
    {
        $sheet = $this->createSheet();
        $this->expectException(MetaAttributeGroupNotFoundError::class);
        $sheet->importUxonObject(new UxonObject([
            'columns' => [['attribute_group_alias' => 'my.App.UNKNOWN']]
        ]));
    }

    /**
     * Group selectors must be non-empty strings.
     */
    public function testInvalidGroupAliasesFail() : void
    {
        foreach ([null, '', ' ', false, 1, []] as $alias) {
            $sheet = $this->createSheet();
            try {
                $sheet->importUxonObject(new UxonObject([
                    'columns' => [['attribute_group_alias' => $alias]]
                ]));
                $this->fail('Invalid group alias was accepted');
            } catch (UxonParserError $error) {
                $this->assertStringContainsString('non-empty string', $error->getMessage());
            }
        }
    }

    /**
     * A group cannot also select one expression or reuse a fixed name for every column.
     */
    public function testAmbiguousGroupTemplatesFail() : void
    {
        foreach (['attribute_alias', 'expression', 'name'] as $property) {
            $sheet = $this->createSheet();
            try {
                $sheet->importUxonObject(new UxonObject([
                    'columns' => [['attribute_group_alias' => '~EDITABLE', $property => 'NAME']]
                ]));
                $this->fail('Ambiguous group template was accepted');
            } catch (UxonParserError $error) {
                $this->assertStringContainsString('Cannot combine attribute_group_alias with "' . $property . '"', $error->getMessage());
            }
        }
    }

    /**
     * Creates a small model without starting Workbench or accessing a database.
     * 
     * @return DataSheet
     */
    private function createSheet() : DataSheet
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'sheet-attribute-group-unit-test']);
        $workbench->getConfig()->loadConfigFile(dirname(__DIR__, 5) . '\\Config\\exface.Core.config.json');
        $object = new MetaObject(new Model($workbench));
        $object->setAlias('EXAMPLE');
        $object->setNamespace('my.App');
        foreach (['ID', 'NAME', 'DESCRIPTION'] as $alias) {
            $attribute = new Attribute($object, $alias, $alias);
            $attribute->setEditable($alias !== 'ID');
            $object->addAttribute($attribute);
        }
        return DataSheetFactory::createFromObject($object);
    }
}
