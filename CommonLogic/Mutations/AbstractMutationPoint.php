<?php
namespace exface\Core\CommonLogic\Mutations;

use exface\Core\Interfaces\Mutations\MutationPointInterface;
use exface\Core\Interfaces\Mutations\MutationTargetInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class AbstractMutationPoint implements MutationPointInterface
{
    private $workbench = null;
    private $mutationsLoaded = [];
    private $mutationsApplied = [];

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }

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

    public function applyMutations(MutationTargetInterface $target, $subject) : array
    {
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

    public function getWorkbench()
    {
        return $this->workbench;
    }
}