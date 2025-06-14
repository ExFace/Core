<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;
use exface\Core\Mutations\AppliedEmptyMutation;
use exface\Core\Mutations\AppliedMutation;

/**
 * Allows to modify a UI page
 *
 * @author Andrej Kabachnik
 */
class UiPageMutation extends AbstractMutation
{
    private UxonObject|null $widgetMutationUxon = null;
    private GenericUxonMutation|null $widgetMutation = null;
    private string|null $changedName = null;

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        /* @var $subject \exface\Core\CommonLogic\Model\UiPage */
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        $stateBefore = null;

        // Apply widget mutations
        if ((null !== $mutation = $this->getWidgetMutation()) && $subject instanceof UiPageInterface) {
            $stateBefore = $stateBefore ?? $subject->exportUxonObject()->toJson(true);
            $uxon = $subject->getContentsUxon();
            $mutation->apply($uxon);
            $subject->setContents($uxon);
        }

        // Apply changes to regular properties of the page
        if (null !== $val = $this->getChangedName()) {
            $subject->setName($val);
        }
        // TODO implement a mutation to change all properties of the page: e.g. name, description, etc.
        // A GenericUxonPrototypeMutation would be cool. We could use the on attributes, objects, etc.

        if ($stateBefore !== null) {
            $stateAfter = $subject->exportUxonObject()->toJson(true);
        }
        return new AppliedMutation($this, $subject, $stateBefore ?? '', $stateAfter ?? '');
    }

    /**
     * @see MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return $subject instanceof UiMenuItemInterface;
    }

    /**
     * @return GenericUxonMutation|null
     */
    protected function getWidgetMutation() : ?GenericUxonMutation
    {
        if ($this->widgetMutation === null && $this->widgetMutationUxon !== null) {
            $this->widgetMutation = new GenericUxonMutation($this->getWorkbench(), $this->widgetMutationUxon);
        }
        return $this->widgetMutation;
    }

    /**
     * Modifies the widget of the page by applying UXON mutation rules
     *
     * @uxon-property change_widget
     * @uxon-type \exface\Core\Mutations\Prototypes\GenericUxonMutation
     * @uxon-template {"": ""}
     *
     * @param UxonObject $uxonMutation
     * @return $this
     */
    protected function setChangeWidget(UxonObject $uxonMutation) : UiPageMutation
    {
        $this->widgetMutationUxon = $uxonMutation;
        $this->widgetMutation = null;
        return $this;
    }

    /**
     * @deprecated use change_widget instead
     *
     * @param UxonObject $uxonMutation
     * @return $this
     */
    protected function setWidget(UxonObject $uxonMutation) : UiPageMutation
    {
        return $this->setChangeWidget($uxonMutation);
    }

    /**
     * @return string|null
     */
    protected function getChangedName() : ?string
    {
        return $this->changedName;
    }

    /**
     * Changes the name of the page
     *
     * @uxon-proeprty change_name
     * @uxon-type string|metamodel:formula
     *
     * @param string $changedName
     * @return $this
     */
    protected function setChangeName(string $changedName) : UiPageMutation
    {
        $this->changedName = $changedName;
        return $this;
    }
}