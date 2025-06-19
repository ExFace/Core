<?php

namespace exface\Core\Events\Mutations;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Mutations\MutationPointInterface;
use exface\Core\Widgets\DebugMessage;

class OnMutationsAppliedEvent extends AbstractEvent implements iCanGenerateDebugWidgets
{
    private MutationPointInterface $mutationPoint;
    private array $mutations;
    private string $subjectName;

    public function __construct(MutationPointInterface $mutationPoint, array $mutationsApplied, string $subjectName)
    {
        $this->mutationPoint = $mutationPoint;
        $this->mutations = $mutationsApplied;
        $this->subjectName = $subjectName;
    }

    /**
     * @inheritDoc
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        foreach ($this->getMutationsApplied() as $i => $mutationApplied) {
            $debug_widget = $mutationApplied->createDebugWidget($debug_widget, $i);
        }
        return $debug_widget;
    }

    public function getMutationPoint() : MutationPointInterface
    {
        return $this->mutationPoint;
    }

    public function getMutationsApplied() : array
    {
        return $this->mutations;
    }

    public function getSubjectName() : string
    {
        return $this->subjectName;
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->mutationPoint->getWorkbench();
    }
}