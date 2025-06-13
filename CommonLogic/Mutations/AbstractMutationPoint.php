<?php
namespace exface\Core\CommonLogic\Mutations;

use exface\Core\Interfaces\Mutations\MutationPointInterface;
use exface\Core\Interfaces\Mutations\MutationTargetInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Common base for standard mutation points
 */
abstract class AbstractMutationPoint implements MutationPointInterface
{
    private $workbench = null;
    private $mutationsLoaded = [];
    private $mutationsApplied = [];

    /**
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }

    /**
     * @param MutationTargetInterface $target
     * @return array|null
     */
    public function getMutations(MutationTargetInterface $target) : array
    {
        $cacheKey = $target->__toString();
        if (null !== $cache = ($this->mutationsLoaded[$cacheKey] ?? null)) {
            return $cache;
        }
        $mutations = $this->getWorkbench()->model()->getModelLoader()->loadMutations($this, $target);
        $this->mutationsLoaded[$cacheKey] = $mutations;
        return $mutations;
    }

    /**
     * {@inheritDoc}
     * @see MutationPointInterface::applyMutations()
     */
    public function applyMutations(MutationTargetInterface $target, $subject) : array
    {
        if ($this->isDisabled() === true) {
            return [];
        }
        $applied = [];
        foreach ($this->getMutations($target) as $mutation) {
            if (! $mutation->supports($subject)) {
                continue;
            }
            $applied[] = $mutation->apply($subject);
        }
        $this->mutationsApplied[] = [
            'target' => $target->__toString(),
            'subject' => $subject,
            'mutations' => $applied
        ];
        return $applied;
    }

    /**
     * {@inheritDoc}
     * @see MutationPointInterface::getMutationsApplied()
     */
    public function getMutationsApplied($subject): array
    {
        $results = [];
        foreach ($this->mutationsApplied as $item) {
            if ($item['subject'] === $subject) {
                $results[] = array_merge($results, $item['mutations']);
            }
        }
        return $results;
    }

    /**
     * {@inheritDoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * {@inheritDoc}
     * @see MutationPointInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        try {
            $globalSwitch = $this->getWorkbench()->getConfig()->getOption('MUTATIONS.ENABLED');
        } catch (\Exception $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
        if ($globalSwitch === false) {
            return true;
        }
        return false;
    }
}