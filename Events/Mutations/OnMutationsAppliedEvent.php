<?php

namespace exface\Core\Events\Mutations;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
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
        $tab = $debug_widget->createTab();
        $tab->setCaption('Mutations');
        $tab->setWidgets(new UxonObject([
            [
                'widget_type' => 'Markdown',
                'height' => '100%',
                'width' => '100%',
                'hide_caption' => true,
                'value' => $this->buildMarkdownSummary()
            ]
        ]));
        $debug_widget->addTab($tab);
        foreach ($this->getMutationsApplied() as $i => $mutationApplied) {
            $debug_widget = $mutationApplied->createDebugWidget($debug_widget, $i + 1);
        }
        return $debug_widget;
    }

    protected function buildMarkdownSummary() : string
    {
        $applied = $this->getMutationsApplied();
        $appliedCnt = count($applied);
        $appliedList = '';
        foreach ($applied as $i => $mutationApplied) {
            $appliedList .= "\n" . ($i + 1) . ". {$mutationApplied->getMutation()->getName()}}";
        }
        return <<<MD
# Mutations for {$this->getSubjectName()}

Found {$appliedCnt} mutations.
{$appliedList}
MD;

    }

    public function getMutationPoint() : MutationPointInterface
    {
        return $this->mutationPoint;
    }

    /**
     * @return AppliedMutationInterface[]
     */
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