<?php
namespace exface\Core\Mutations;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\Widgets\DebugMessage;

class AppliedEmptyMutation implements AppliedMutationInterface
{
    private MutationInterface $mutation;
    private mixed $subject;

    /**
     * @param MutationInterface $mutation
     * @param object $subject
     */
    public function __construct(MutationInterface $mutation, $subject)
    {
        $this->mutation = $mutation;
        $this->subject = $subject;
    }

    /**
     * 
     * @see AppliedMutationInterface::getMutation()
     */
    public function getMutation(): MutationInterface
    {
        return $this->mutation;
    }

    /**
     * 
     * @see AppliedMutationInterface::getSubject()
     */
    public function getSubject(): mixed
    {
        return $this->subject;
    }

    /**
     * 
     * @see AppliedMutationInterface::hasChanges()
     */
    public function hasChanges(): bool
    {
        return false;
    }

    /**
     * 
     * @see AppliedMutationInterface::dumpStateBefore()
     */
    public function dumpStateBefore(): string
    {
        return '';
    }

    /**
     * 
     * @see AppliedMutationInterface::dumpStateAfter()
     */
    public function dumpStateAfter(): string
    {
        return '';
    }

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
                'value' => 'No mutations applied'
            ]
        ]));
        $debug_widget->addTab($tab);
        return $debug_widget;
    }
}