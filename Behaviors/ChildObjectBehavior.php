<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\DataSheet\AbstractDataSheetEvent;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReplaceDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnReplaceDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\Interfaces\Model\IAffectMetaObjectsInterface;

/**
 * Triggers an update for related objects, whenever the data on this object changes.
 * 
 * You can specify which other objects are affected with the property `changes_affect_relations`. Any object
 * listed in that property will be synchronized with the object this behavior is attached to.
 * 
 * Imagine, for example, an object called `PendingOrder` that represents a list of ordered materials. It consists of 
 * any number of objects called `PendingOrderPosition`, which hold information on what material was ordered and how much of it.
 * Now imagine that the user updates a `PendingOrderPosition`: They will expect to see that change reflected in their `PendingOrder`, 
 * but since we are working with two distinct MetaObjects, this won't be the case. 
 * 
 * You can solve this issue with the `ChildObjectBehavior`: Simply attach it to the `PendingOrderPosition` MetaObject and add 
 * `PendingOrder` to its `changes_affect_relations` property. Now every time any `PendingOrderPosition` is changed, the `PendingOrder` 
 * they are related to will be updated as well.
 * 
 * In technical terms any `OnUpdateData` event of `PendingOrderPosition` triggers `OnUpdateData` for its related `PendingOrder`. 
 * The full event mapping is as follows:
 * 
 * | Source Event | Target Event |
 * |--|--|
 * | OnBefore**Create**Data | OnBefore**Update**Data |
 * | OnBefore**Update**Data | OnBefore**Update**Data |
 * | OnBefore**Replace**Data | OnBefore**Update**Data |
 * | OnBefore**Delete**Data | OnBefore**Update**Data |
 * | On**Create**Data | On**Update**Data |
 * | On**Update**Data | On**Update**Data |
 * | On**Replace**Data | On**Update**Data |
 * | On**Delete**Data | On**Update**Data |
 * 
 * ## Underlying rules for loading data
 * 
 * For this behavior to work, the input sheet needs to have a UID column. If the input-UID is missing, the event
 * cannot fetch the target data and will fail to relay the event. If this happens, a corresponding entry will be added to
 * the tracer log.
 * 
 * If an input-UID is present, the behavior works as follows:
 * - For any pre-transaction events (`OnBefore...Data`) the behavior will try to fetch the target data first from the input sheet and then, if that fails from the data source. 
 * Fetching from the source is slow, so try to include all relation columns in the input sheet if possible.
 * - Any target data loaded for pre-transaction events will be cached for use by the corresponding post-transaction event.
 * - For any post-transaction events (`On...Data`) the behavior will try to fetch their target data from the cache.
 * - For `OnCreateData` and `OnDeleteData` this is always sufficient.
 * - For `OnUpdateData` and `OnReplaceData` the target objects may have been altered by the transaction itself, which this behavior can detect
 * and resolve by loading additional data from source.
 * 
 * ## Examples
 * 
 * ### Order positions change their order
 * 
 * The behavior is to be attached to `PendingOrderPosition`. Assuming this object has a relation called `PendingOrder`, the behavior
 * configuration would look like this:
 * 
 * ```
 * 
 * {
 *	    "changes_affect_relations": [
 *		    PendingOrder
 *	    ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik, Georg Bieger
 * 
 */
class ChildObjectBehavior 
    extends AbstractBehavior
    implements IAffectMetaObjectsInterface 
{
    private array $affectedRelations = [];
    
    private ?array $mapToOnUpdate = null;
    
    private ?array $mapToOnBeforeUpdate = null;
    
    private array $registeredEventListeners = [];
    
    private array $dataCache = [];
    
    private bool $inProgress = false;

    /**
     * An event mapping that's marked as `CACHE_ONLY` will not cause an event dispatch.
     */
    private const CACHE_ONLY_FLAG = 'CACHE_ONLY';
    
    /**
     *
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $eventMapping = $this->getFullEventMapping();
        
        if (! empty($eventMapping)) {
            $eventMgr = $this->getWorkbench()->eventManager();
            foreach ($eventMapping as $fromEvent => $toEvent) {
                $eventMgr->addListener($fromEvent, [$this, 'onEventRelay'], $this->getPriority());
                $this->registeredEventListeners[$fromEvent] = $toEvent;
            }
        }

        return $this;
    }
    
    /**
     *
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $eventMgr = $this->getWorkbench()->eventManager();
        
        if (! empty($this->registeredEventListeners)) {
            foreach ($this->registeredEventListeners as $fromEvent => $toEvent) {
                $eventMgr->removeListener($fromEvent, [$this, 'onEventRelay']);
            }
        }
        
        $this->registeredEventListeners = [];
        return $this;
    }

    /**
     * Executes the action if applicable
     *
     * @param AbstractDataSheetEvent $event
     * @return void
     */
    public function onEventRelay(AbstractDataSheetEvent $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $inputSheet = $event->getDataSheet();
        // Ignore empty sheets - nothing to calculate here!
        if ($inputSheet->isEmpty()) {
            return;
        }
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $inputSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
		
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        
        $logbook->addDataSheet('Input data', $inputSheet);
        $logbook->setIndentActive(1);
        $logbook->addLine('Found input data for object ' . $inputSheet->getMetaObject()->__toString());
        $logbook->setIndentActive(1);
        
        $eventManager = $this->getWorkbench()->eventManager();
        $eventManager->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));

        // Ignore loops
        if ($this->inProgress === true) {
            $logbook->addLine('**Skipped** because of operation performed from within the same behavior (e.g. reading missing data)');
            $eventManager->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        $this->inProgress = true;
        
        $typeOfSourceEvent = get_class($event);
        $transaction = $event->getTransaction();
        $typeOfTargetEvent = $this->getActiveEventMapping()[mb_strtolower($event::getEventName())];
        
        if($typeOfTargetEvent === null) {
            throw new BehaviorConfigurationError($this, "Could not relay update, because no valid event mapping was found for ".get_class($event)."!");
        }
        
        $logbook->setIndentActive(0);
        $logbook->addLine('Processing configured relations:');
        foreach ($this->getAffectedRelationPaths() as $relationString) {
            $logbook->setIndentActive(0);
            $logbook->addLine('Relation alias: "'.$relationString.'"...');
            $logbook->setIndentActive(1);
            
            $targetData = $this->loadTargetData($inputSheet, $relationString, $typeOfSourceEvent, $logbook);
            $logbook->setIndentActive(1);
            
            if($typeOfTargetEvent === self::CACHE_ONLY_FLAG) {
                $logbook->addLine('Event mapping for "'.$event::getEventName().'" specified "CACHE_ONLY". Event will not be relayed.');
                continue;
            }
            
            foreach ($targetData as $targetSheet) {
                if($targetSheet) {
                    $dispatch = new $typeOfTargetEvent($targetSheet->copy(), $transaction);
                    $logbook->addLine('Dispatching "'.$dispatch::getEventName().'" to '.$targetSheet->getMetaObject().'.');
                    $eventManager->dispatch($dispatch);
                } else {
                    $logbook->addLine('Failed to dispatch: Target data is NULL!');
                }
            }
        }

        $this->inProgress = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * Load the target data, either from the internal data cache or from the database.
     *
     * @param DataSheetInterface $inputSheet
     * @param string             $relation
     * @param string             $typeOfSourceEvent
     * @param BehaviorLogBook    $logbook
     * @return DataSheetInterface[]
     */
    private function loadTargetData(
        DataSheetInterface $inputSheet, 
        string $relation, 
        string $typeOfSourceEvent,
        BehaviorLogBook $logbook
    ) : array
    {
        $result = [];
        
        // Resolve event type.
        $typeComponents = explode('\\', $typeOfSourceEvent);
        $shortType = array_pop($typeComponents);
        $onAfter = !str_contains($shortType, 'Before');
        $cacheKey = str_replace('Before', '', $shortType);

        // Try to get Target-UID column from input sheet. 
        $targetUidCol = false;// $inputSheet->getColumns()->getByExpression($relation);
        
        // After (Create, Update, Replace):
        // Load sheet with old target data from cache.
        if($onAfter) {
            $logbook->addLine('Event is post-transaction, loading data from cache with key "'.$cacheKey.'"...');
            $logbook->setIndentActive(2);
            if(key_exists($cacheKey, $this->dataCache)) {
                $cachedSheet = $this->dataCache[$cacheKey];
                unset($this->dataCache[$cacheKey]);
                
                $logbook->addLine('Successfully loaded target data from cache.');
                $logbook->addDataSheet('CachedTargetData', $cachedSheet);
            } else {
                $logbook->addLine('No matching target data found in cache for key "'.$cacheKey.'".');
            }
            
            $result[] = $cachedSheet;
            $targetsHaveChanged = $targetUidCol && $this->targetsHaveChanged(
                previousTargets: $cachedSheet->getUidColumn()->getValues(), 
                currentTargets: $targetUidCol->getValues()
            );
            
            if( !$targetsHaveChanged ||
                is_a($typeOfSourceEvent, OnCreateDataEvent::class, true) ||
                is_a($typeOfSourceEvent, OnDeleteDataEvent::class, true)) {
                return $result;
            } else {
                $logbook->addLine('Loading from cache complete. Moving on to load additional data from source.');
                $logbook->setIndentActive(1);
            }
        } else {
            $logbook->addLine('Event is pre-transaction, target data must be loaded from source.');
        }

        $logbook->addLine('Loading target data from source...');
        $logbook->setIndentActive(2);
        
        $relPath = RelationPathFactory::createFromString($this->getObject(), $relation);
        $targetObject = $relPath->getEndObject();
        $targetSheet = DataSheetFactory::createFromObject($targetObject);
        $targetSheet->getColumns()->addFromSystemAttributes();

        // If the input sheet did not contain the Target-UID, we need to load it from the database.
        if(! $targetUidCol){
            $logbook->addLine('Target relation column not found. Attempting to read target UIDs from data source.');
            if ($inputSheet->hasUidColumn()) {
                $relSheet = $inputSheet->copy();
                $relSheet->getFilters()->addConditionFromColumnValues($relSheet->getUidColumn());
                $targetUidCol = $relSheet->getColumns()->addFromExpression($relation);
                // TODO (idea) geb 22-11-2024: As a potential optimization, we might be able to cache this across all 
                // TODO (idea) 'Before' events, but that requires a mechanism that ensures the underlying data didn't change. 
                $relSheet->dataRead();
            } else {
                $logbook->addLine('Cannot read target UIDs from data source because input data has no UID column.');
                return $result;
            }
        }

        $targetUids = $targetUidCol->getValues();
        $targetUidsString = '['.implode(',',$targetUids).']';
        $logbook->addLine('Found the following target UIDs: '.$targetUidsString);
        $targetSheet->getFilters()->addConditionFromValueArray($targetSheet->getUidColumn()->getExpressionObj(), $targetUids);
        $targetSheet->dataRead();
        
        if($targetSheet->countRows() > 0) {
            $logbook->addLine('Successfully loaded target data for the following UIDs: '.$targetUidsString);
            $logbook->addDataSheet('LoadedTargetData-'.$targetUidsString, $targetSheet);

            // Save data to cache.
            if(!$onAfter) {
                $logbook->addLine('Saving loaded data to cache, with key "' . $cacheKey . '".');
                $this->dataCache[$cacheKey] = $targetSheet;
                $result[] = $targetSheet;
            }
        } else {
            $logbook->addLine('No target data found for the following UIDs: '.$targetUidsString);
        }
        
        return $result;
    }

    /**
     * Check whether the UIDs of the target objects have been changed by the event source.
     * 
     * @param array $previousTargets
     * @param array $currentTargets
     * @return bool
     */
    private function targetsHaveChanged(array $previousTargets, array $currentTargets) : bool
    {
        foreach ($currentTargets as $target) {
            if(!in_array($target, $previousTargets, true)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Relations to the objects, that will be affected by changes on this one
     *
     * For example, for an `ORDER_POSITION` object
     *
     * - `ORDER`
     * - `ORDER__CUSTOMER__CUSTOMER_STATS`
     *
     * @uxon-property changes_affect_relations
     * @uxon-type metamodel:relation[]
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfRelationPaths
     * @return ChildObjectBehavior
     */
    public function setChangesAffectRelations(UxonObject $arrayOfRelationPaths) : ChildObjectBehavior
    {
        $this->affectedRelations = $arrayOfRelationPaths->toArray();

        /* IDEA mark related attributes as system to check ORDER timestamp when saving ORDER_POS.
         * Will probably not work yet because system attributes are only direct ones.
         * Do this LATER!
        foreach ($this->relations as $relationString) {
            $relPath = RelationPathFactory::createFromString($this->getObject(), $relationString);
            $relObj = $relPath->getEndObject();
            foreach ($relObj->getAttributes()->getSystem() as $systemAttr) {
                // If ORDER has system attribute MODIFIED_ON, give ORDER_POS the system attibute ORDER__MODIFIED_ON
                $this->getObject()->getAttribute(RelationPath::join($relationString, $systemAttr->getAlias()))->setSystem();
            }
            $this->getObject()->getRelation($relKey)->getRightObject();

        }
        */
        
        return $this;
    }


    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject() : UxonObject
    {
        // TODO
        return parent::exportUxonObject();
    }

    /**
     * 
     * @return string[]
     */
    protected function getAffectedRelationPaths() : array
    {
        return $this->affectedRelations;
    }

    /**
     * @return array|string[]
     */
    public function getActiveEventMapping() : array
    {
        return $this->registeredEventListeners;
    }

    /**
     * Maps all events in this collection to `OnUpdateData`.
     *
     * Whenever they are triggered, this behavior will trigger the `OnUpdateData` event for all
     * the target objects of its synchronized relations.
     *
     * @param UxonObject $mapToOnUpdate
     * @return $this
     */
    public function setMapToOnUpdate(UxonObject $mapToOnUpdate) : static
    {
        $onUpdate = OnUpdateDataEvent::class;
        $array = $mapToOnUpdate->toArray();
        
        if(empty($array)) {
            $this->mapToOnUpdate = [];
            return $this;
        } 
        
        foreach ($array as $eventName) {
            $this->mapToOnUpdate[mb_strtolower($eventName)] = $onUpdate;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getMapToOnUpdate() : array
    {
        return $this->mapToOnUpdate !== null ? $this->mapToOnUpdate : [
            mb_strtolower(OnCreateDataEvent::getEventName()) => OnUpdateDataEvent::class,
            mb_strtolower(OnUpdateDataEvent::getEventName()) => OnUpdateDataEvent::class,
            mb_strtolower(OnReplaceDataEvent::getEventName()) => OnUpdateDataEvent::class,
            mb_strtolower(OnDeleteDataEvent::getEventName()) => OnUpdateDataEvent::class,
        ];
    }

    /**
     * Maps all events in this collection to `OnBeforeUpdateData`.
     *
     * Whenever they are triggered, this behavior will trigger the `OnBeforeUpdateData` event for all
     * the target objects of its synchronized relations.
     *
     * @param UxonObject $mapToOnBeforeUpdate
     * @return $this
     */
    public function setMapToOnBeforeUpdate(UxonObject $mapToOnBeforeUpdate) : static
    {
        $onBeforeUpdate = OnBeforeUpdateDataEvent::class;
        $array = $mapToOnBeforeUpdate->toArray();
        if(empty($array)) {
            $this->mapToOnBeforeUpdate = [];
            return $this;
        }
        
        foreach ($mapToOnBeforeUpdate->toArray() as $eventName) {
            $this->mapToOnBeforeUpdate[mb_strtolower($eventName)] = $onBeforeUpdate;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getMapToOnBeforeUpdate() : array
    {
        return $this->mapToOnBeforeUpdate !== null ? $this->mapToOnBeforeUpdate : [
            mb_strtolower(OnBeforeCreateDataEvent::getEventName()) => OnBeforeUpdateDataEvent::class,
            mb_strtolower(OnBeforeUpdateDataEvent::getEventName()) => OnBeforeUpdateDataEvent::class,
            mb_strtolower(OnBeforeReplaceDataEvent::getEventName()) => OnBeforeUpdateDataEvent::class,
            mb_strtolower(OnBeforeDeleteDataEvent::getEventName()) => OnBeforeDeleteDataEvent::class
        ];
    }

    /**
     * Merges all event mappings into one and returns the result.
     * 
     * @return array
     */
    public function getFullEventMapping() : array
    {
        // The full mapping includes OnBefore events that map to nowhere, because regardless of the actual event
        // mapping, we need these hooks to properly cache the parent reference.
        $mapping = [
            mb_strtolower(OnBeforeCreateDataEvent::getEventName()) => self::CACHE_ONLY_FLAG,
            mb_strtolower(OnBeforeUpdateDataEvent::getEventName()) => self::CACHE_ONLY_FLAG,
            mb_strtolower(OnBeforeReplaceDataEvent::getEventName()) => self::CACHE_ONLY_FLAG,
            mb_strtolower(OnBeforeDeleteDataEvent::getEventName()) => self::CACHE_ONLY_FLAG
        ];
        
        $mapping = array_merge($mapping, $this->getMapToOnBeforeUpdate());
        return array_merge($mapping, $this->getMapToOnUpdate());
    }

    /**
     * @inheritdoc 
     */
    public function getAffectedMetaObjects(): array
    {
        $result = [];
        
        foreach ($this->getAffectedRelationPaths() as $relationString) {
            $relPath = RelationPathFactory::createFromString($this->getObject(), $relationString);
            $result[] = $relPath->getEndObject();
        }
        
        return  $result;
    }

    /**
     * Try to convert an event name into a valid classname with namespaces.
     *
     * NOTE: This method is redundant at the moment. If however, we decide to make
     * the event mapping more flexible, it might come in handy.
     *
     * @param string $eventName
     * @return string
     */
    private function tryGetClassFromEventName(string $eventName) : string
    {
        $eventName = str_replace('.', '\\', $eventName);
        $before = StringDataType::substringBefore($eventName, '\\DataSheet');
        $result = $before.'\\Events'.StringDataType::substringAfter($eventName, '\\Core').'Event';

        if(is_a($result, AbstractDataSheetEvent::class, true)) {
            return $result;
        }

        throw new BehaviorConfigurationError($this, 'Could not resolve '.$eventName.' to a valid class name!');
    }
}