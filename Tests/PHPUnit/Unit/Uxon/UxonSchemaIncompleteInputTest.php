<?php
namespace exface\Core\Tests\PHPUnit\Unit\Uxon;

use exface\Core\CommonLogic\Log\Logger;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Uxon\ActionSchema;
use exface\Core\Uxon\CommunicationMessageSchema;
use exface\Core\Uxon\DatatypeSchema;
use exface\Core\Uxon\SnippetSchema;
use exface\Core\Uxon\UxonSchema;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Throwable;

/**
 * Exercises autosuggest with real in-memory objects, without starting Workbench.
 */
class UxonSchemaIncompleteInputTest extends TestCase
{
    /**
     * Editing a relation prefix or modifier must not fail any attribute-based suggestion.
     */
    public function testIncompleteRelationPathsReturnNoSuggestions() : void
    {
        $workbench = $this->createWorkbench();
        $object = $this->createObject($workbench);
        $schema = $this->createSchema($workbench);

        foreach (['attribute', 'relation', 'attribute_group', 'expression'] as $type) {
            foreach ([
                'MassnahmeMassnahmeTaetigkeitsPlan__Erfassungsart:COUNT_IF(== MENGENBASIERT)',
                'Massnahme[__Erfassungsart',
                'Massnahme__Missing__Erfassungsart'
            ] as $search) {
                $this->assertSame([], $schema->getValidValues(
                    new UxonObject(),
                    ['value'],
                    $search,
                    'metamodel:' . $type,
                    $object
                ), $type . ': ' . $search);
            }
        }
    }

    /**
     * Completing the relation restores suggestions, preserving their full paths.
     */
    public function testCompletedRelationRestoresSuggestions() : void
    {
        $workbench = $this->createWorkbench();
        $object = $this->createObject($workbench);
        $schema = $this->createSchema($workbench);
        $values = $schema->getValidValues(
            new UxonObject(),
            ['value'],
            'Massnahme__Erfassungsart:COUNT_IF(== MENGENBASIERT)',
            'metamodel:attribute',
            $object
        );

        $this->assertContains('Massnahme__Erfassungsart', $values);
        $this->assertContains('Massnahme__Massnahme__', $values);
    }

    /**
     * An unfinished object namespace still permits suggestions independent of that object.
     */
    public function testIncompleteObjectSelectorKeepsBooleanSuggestions() : void
    {
        $schema = $this->createSchema($this->createWorkbench());
        $this->assertSame(['true', 'false'], $schema->getValidValues(
            new UxonObject(['object_alias' => 'exface.']),
            ['value'],
            '',
            'boolean'
        ));
    }

    /**
     * A stored UXON failure wrapped as not-found must remain visible to the editor.
     */
    public function testWrappedStoredDefinitionErrorPropagates() : void
    {
        $workbench = $this->createWorkbench();
        $schema = $this->createSchema($workbench);
        $error = new MetaObjectNotFoundError(
            'Cannot load object',
            null,
            new UxonParserError(new UxonObject(), 'Broken stored UXON')
        );
        $schema->objectError = $error;

        $this->expectExceptionObject($error);
        $schema->getValidValues(new UxonObject(), ['value'], '', 'boolean');
    }

    /**
     * Annotation data-source errors must not be converted into fake property suggestions.
     */
    public function testAnnotationReadErrorPropagates() : void
    {
        $schema = new class($this->createWorkbench()) extends UxonSchema {
            /**
             * Avoids persistent caches for this isolated annotation-read check.
             *
             * {@inheritDoc}
             * @see UxonSchema::getCache()
             */
            protected function getCache(string $prototypeClass, string $key)
            {
                return null;
            }

            /**
             * Simulates the annotation data-source boundary, not Workbench services.
             *
             * {@inheritDoc}
             * @see UxonSchema::loadPropertiesSheet()
             */
            protected function loadPropertiesSheet(string $prototypeClass, string $aliasOfAnnotationObject = 'exface.Core.UXON_PROPERTY_ANNOTATION') : DataSheetInterface
            {
                throw new RuntimeException('Annotation data source failed');
            }
        };
        $cache = new ReflectionProperty(UxonSchema::class, 'prototypePropCache');
        $cache->setAccessible(true);
        $cache->setValue($schema, ['' => null]);

        $this->expectExceptionMessage('Annotation data source failed');
        $schema->getProperties('', new UxonObject(), []);
    }

    /**
     * Partial prototype selectors fall back, but unavailable model services must not.
     */
    public function testPrototypeResolutionDistinguishesInputFromBackendFailure() : void
    {
        $workbench = $this->createWorkbench();
        foreach ([ActionSchema::class, DatatypeSchema::class] as $schemaClass) {
            $schema = new $schemaClass($workbench);
            $fallback = $schema->getPrototypeClass(new UxonObject(), []);
            $this->assertSame(
                $fallback,
                $schema->getPrototypeClass(new UxonObject(['alias' => 'exface.']), [])
            );
        }

        $schema = new DatatypeSchema($workbench);
        $this->expectExceptionMessage('Meta model not initialized');
        $schema->getPrototypeClass(new UxonObject([
            'alias' => '0x35370000000000000000000000000000'
        ]), []);
    }

    /**
     * Action model loading errors are not an incomplete action alias.
     */
    public function testActionModelFailurePropagates() : void
    {
        $schema = new ActionSchema($this->createWorkbench());
        $this->expectExceptionMessage('Meta model not initialized');
        $schema->getPrototypeClass(new UxonObject(['alias' => 'exface.Core.UnknownAction']), []);
    }

    /**
     * Communication model failures must not silently select AbstractMessage.
     */
    public function testCommunicationModelFailurePropagates() : void
    {
        $schema = new CommunicationMessageSchema($this->createWorkbench());
        $this->expectExceptionMessage('Meta model not initialized');
        $schema->getPrototypeClass(new UxonObject(['channel' => 'exface.Core.UnknownChannel']), []);
    }

    /**
     * Snippet model failures must not silently fall back to generic parameter names.
     */
    public function testSnippetModelFailurePropagates() : void
    {
        $schema = new SnippetSchema($this->createWorkbench());
        $this->expectExceptionMessage('Meta model not initialized');
        $schema->getProperties('', new UxonObject([
            'call' => ['~snippet' => 'exface.Core.UnknownSnippet', 'parameters' => []]
        ]), ['call', 'parameters', 'value']);
    }

    /**
     * Supplies annotation types locally while keeping value completion logic unchanged.
     *
     * @param Workbench $workbench
     * @return UxonSchema
     */
    private function createSchema(Workbench $workbench) : UxonSchema
    {
        return new class($workbench) extends UxonSchema {
            public ?Throwable $objectError = null;

            /**
             * {@inheritDoc}
             * @see UxonSchema::getPropertyTypes()
             */
            public function getPropertyTypes(string $prototypeClass, string $property) : array
            {
                return [$prototypeClass];
            }

            /**
             * Uses the supplied in-memory object instead of loading a persisted model.
             *
             * {@inheritDoc}
             * @see UxonSchema::getMetaObject()
             */
            public function getMetaObject(UxonObject $uxon, array $path, MetaObjectInterface $rootObject = null) : MetaObjectInterface
            {
                if ($this->objectError !== null) {
                    throw $this->objectError;
                }
                return $rootObject ?? parent::getMetaObject($uxon, $path);
            }
        };
    }

    /**
     * Creates a real Workbench with an isolated, handler-free logger.
     *
     * @return Workbench
     */
    private function createWorkbench() : Workbench
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'uxon-incomplete-input-test']);
        $workbench->getConfig()->loadConfigFile(dirname(__DIR__, 4) . '\\Config\\exface.Core.config.json');
        $logger = new ReflectionProperty(Workbench::class, 'logger');
        $logger->setAccessible(true);
        $logger->setValue($workbench, new Logger());
        return $workbench;
    }

    /**
     * Creates one in-memory object with a self-relation and no persisted model access.
     *
     * @param Workbench $workbench
     * @return MetaObject
     */
    private function createObject(Workbench $workbench) : MetaObject
    {
        $object = new MetaObject(new Model($workbench));
        $object->setAlias('Verortung');
        $object->setNamespace('my.App');
        $key = new Attribute($object, 'Massnahme', 'Massnahme');
        $key->setRelationFlag(true);
        $object->addAttribute($key);
        $object->addAttribute(new Attribute($object, 'Erfassungsart', 'Erfassungsart'));
        $relation = new Relation(
            $workbench,
            RelationCardinalityDataType::N_TO_ONE($workbench),
            'test-relation',
            'Massnahme',
            '',
            $object,
            $key,
            'test-object'
        );
        $rightObject = new ReflectionProperty(Relation::class, 'rightObject');
        $rightObject->setAccessible(true);
        $rightObject->setValue($relation, $object);
        $object->addRelation($relation);
        return $object;
    }
}
