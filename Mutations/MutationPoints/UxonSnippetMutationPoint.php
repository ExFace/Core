<?php
namespace exface\Core\Mutations\MutationPoints;

use exface\Core\CommonLogic\Mutations\AbstractMutationPoint;
use exface\Core\Events\Model\OnBeforeSnippetLoadedEvent;
use exface\Core\Events\Mutations\OnMutationsAppliedEvent;
use exface\Core\Mutations\MetaObjectUidMutationTarget;

/**
 * Applies mutations before a snippet is instantiated - right after the TranslatableBehavior
 *
 * @author Andrej Kabachnik
 */
class UxonSnippetMutationPoint extends AbstractMutationPoint
{
    /**
     * Event listener
     *
     * See static event listeners in System.config.json
     *
     * @param OnBeforeSnippetLoadedEvent $event
     * @return void
     */
    public static function onSnippetLoadedApplyMutations(OnBeforeSnippetLoadedEvent $event) : void
    {
        $point = $event->getWorkbench()->getMutator()->getMutationPoint(self::class);
        $target = new MetaObjectUidMutationTarget('exface.Core.UXON_SNIPPET', $event->getSnippetUid());
        $applied = $point->applyMutations($target, $event->getUxon());

        if (! empty($applied)) {
            $point->getWorkbench()->eventManager()->dispatch(new OnMutationsAppliedEvent($applied, 'snippet "' . $event->getSnippetAlias() . '"', $point));
        }
    }
}