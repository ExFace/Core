<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\Behaviors\UnionBehavior;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\BehaviorFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Verifies lazy union mapping initialization without starting Workbench or reading data.
 */
class UnionBehaviorLazyMappingsTest extends TestCase
{
    /**
     * Configuration must not instantiate mappings; repeated access reuses them.
     * 
     * @return void
     */
    public function testConfigurationDefersAndCachesMappings() : void
    {
        $behavior = $this->createBehavior();
        $cache = new ReflectionProperty(UnionBehavior::class, 'unionObjects');
        $cache->setAccessible(true);

        $this->assertNull($cache->getValue($behavior));
        $mappings = $behavior->getUnionMappings();
        $this->assertCount(1, $mappings);
        $this->assertSame($mappings, $cache->getValue($behavior));
        $this->assertSame($mappings, $behavior->getUnionMappings());
    }

    /**
     * Copies must remain lazy even if the original already initialized its mappings.
     * 
     * @return void
     */
    public function testCopiesBuildIndependentMappingsLazily() : void
    {
        $behavior = $this->createBehavior();
        $uninitializedCopy = $behavior->copy();
        $original = $behavior->getUnionMappings()[0];
        $initializedCopy = $behavior->copy();
        $cache = new ReflectionProperty(UnionBehavior::class, 'unionObjects');
        $cache->setAccessible(true);
        $owner = new ReflectionProperty(get_class($original), 'behavior');
        $owner->setAccessible(true);

        foreach ([$uninitializedCopy, $initializedCopy] as $copy) {
            $this->assertNull($cache->getValue($copy));
            $mapping = $copy->getUnionMappings()[0];
            $this->assertNotSame($original, $mapping);
            $this->assertSame($copy, $owner->getValue($mapping));
            $this->assertSame($behavior->getUnionBaseObject(), $copy->getUnionBaseObject());
        }
    }

    /**
     * Replacing configuration must discard cached mappings, including empty results.
     * 
     * @return void
     */
    public function testReplacingConfigurationInvalidatesCache() : void
    {
        $behavior = $this->createBehavior();
        $original = $behavior->getUnionMappings()[0];
        $cache = new ReflectionProperty(UnionBehavior::class, 'unionObjects');
        $cache->setAccessible(true);
        $configuration = new UxonObject([['object_alias' => 'my.App.REPLACEMENT']]);
        $behavior->setUnionObjects($configuration);
        $configuration->setProperty(0, new UxonObject(['object_alias' => 'my.App.CHANGED']));

        $this->assertNull($cache->getValue($behavior));
        $replacement = $behavior->getUnionMappings()[0];
        $this->assertNotSame($original, $replacement);
        $this->assertSame('my.App.REPLACEMENT', $replacement->exportUxonObject()->getProperty('object_alias'));
        $behavior->setUnionObjects(new UxonObject([]));
        $this->assertNull($cache->getValue($behavior));
        $this->assertSame([], $behavior->getUnionMappings());
        $this->assertSame([], $cache->getValue($behavior));
    }

    /**
     * @return UnionBehavior
     */
    private function createBehavior() : UnionBehavior
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'union-lazy-mappings-unit-test']);
        $workbench->getConfig()->loadConfigFile(dirname(__DIR__, 3) . '\\Config\\exface.Core.config.json');
        $object = new MetaObject(new Model($workbench));
        $object->setAlias('UNION_BASE');
        return BehaviorFactory::createFromUxon($object, UnionBehavior::class, new UxonObject([
            'union_objects' => [['object_alias' => 'my.App.SOURCE']]
        ]));
    }
}