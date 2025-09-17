<?php
namespace exface\Core\Mutations\Prototypes;

use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\Mutations\AbstractMutation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Mutations\AppliedMutationInterface;
use exface\Core\Interfaces\Mutations\MutationInterface;
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

    private array $changesForMenuItems = [];
    private array $changesForPages = [];

    /**
     * @see MutationInterface::apply()
     */
    public function apply($subject): AppliedMutationInterface
    {
        /* @var $subject \exface\Core\Interfaces\Model\UiMenuItemInterface */
        if (! $this->supports($subject)) {
            throw new InvalidArgumentException('Cannot apply page mutation to ' . get_class($subject) . ' - only instances of pages supported!');
        }

        if ($subject instanceof UiPageInterface) {
            $applied = $this->applyToPage($subject);
        } else {
            $applied = $this->applyToMenuItem($subject);
        }
        return $applied;
    }

    protected function applyToPage(UiPageInterface $page) : AppliedMutationInterface
    {
        $stateArrayBefore = $page->exportUxonObject()->toArray();

        // Apply changes to properties of menu items in general
        $changes = $this->getChangesForMenuItem();
        $changes = array_merge($changes, $this->getChangesForPages());
        $page->importUxonObject(new UxonObject($changes));

        // Apply widget mutations
        if (null !== $mutation = $this->getWidgetMutation()) {
            $uxon = $page->getContentsUxon();
            $mutation->apply($uxon);
            $page->setContents($uxon);
        }
        // TODO implement a mutation to change all properties of the page: e.g. name, description, etc.
        // A GenericUxonPrototypeMutation would be cool. We could use the on attributes, objects, etc.

        $stateBefore = (new UxonObject($stateArrayBefore))->toJson(true);
        $stateAfter = $page->exportUxonObject()->toJson(true);

        return new AppliedMutation($this, $page, $stateBefore ?? '', $stateAfter ?? '');
    }

    protected function applyToMenuItem(UiMenuItemInterface $menuItem): AppliedMutationInterface
    {
        // Apply changes to properties of menu items in general
        $changes = $this->getChangesForMenuItem();

        if (! empty($changes)) {
            // Menu items cannot be converted to UXON, so we just put those things in the state array, that
            // can be changed for a menu item
            $stateArrayBefore = [
                'name' => $menuItem->getName(),
                'description' => $menuItem->getDescription(),
                'intro' => $menuItem->getIntro()
            ];

            if (null !== $val = ($changes['name'] ?? null)) {
                $menuItem->setName($val);
            }
            if (null !== $val = ($changes['description'] ?? null)) {
                $menuItem->setDescription($val);
            }
            if (null !== $val = ($changes['intro'] ?? null)) {
                $menuItem->setIntro($val);
            }

            $stateBefore = (new UxonObject($stateArrayBefore))->toJson(true);
            $stateAfter = (new UxonObject(array_merge($stateArrayBefore, $changes)))->toJson(true);
        }

        return new AppliedMutation($this, $menuItem, $stateBefore ?? '', $stateAfter ?? '');
    }

    /**
     * @see MutationInterface::supports()
     */
    public function supports($subject): bool
    {
        return ($subject instanceof UiMenuItemInterface && $this->hasChangesForMenuItems()) || $subject instanceof UiPageInterface;
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
     * @return bool
     */
    protected function hasChanges() : bool
    {
        return ! empty($this->changesForMenuItems) || ! empty($this->changesForPages) || $this->widgetMutation !== null;
    }

    protected function hasChangesForMenuItems() : bool
    {
        return ! empty($this->changesForMenuItems);
    }

    /**
     * @return array
     */
    protected function getChangesForMenuItem() : array
    {
        return $this->changesForMenuItems;
    }

    /**
     * @return array
     */
    protected function getChangesForPages() : array
    {
        return $this->changesForPages;
    }

    /**
     * Changes the name of the page
     *
     * @uxon-property change_name
     * @uxon-type string|metamodel:formula
     *
     * @param string $changedName
     * @return $this
     */
    protected function setChangeName(string $changedName) : UiPageMutation
    {
        $this->changesForMenuItems['name'] = $changedName;
        return $this;
    }

    /**
     * Changes the description of the page
     *
     * @uxon-property change_description
     * @uxon-type string
     *
     * @param string $changedDescription
     * @return $this
     */
    protected function setChangeDescription(string $changedDescription) : UiPageMutation
    {
        $this->changesForMenuItems['description'] = $changedDescription;
        return $this;
    }

    /**
     * Changes the intro of the page
     *
     * @uxon-property change_intro
     * @uxon-type string
     *
     * @param string $changedIntro
     * @return $this
     */
    protected function setChangeIntro(string $changedIntro) : UiPageMutation
    {
        $this->changesForMenuItems['intro'] = $changedIntro;
        return $this;
    }
}