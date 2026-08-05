<?php
namespace exface\Core\CommonLogic\Security\Authorization\Obligations;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Security\ObligationInterface;
use exface\Core\Interfaces\Security\PermissionInterface;

/**
 * This obligation tells the authorization point (or any other authorization requester), which rows of a data sheet are permitted
 * 
 * @author Andrej Kabachnik
 *
 */
class DataRowPermissionsObligation implements ObligationInterface
{
    private bool $fulfilled = false;
    private array $rowPermissions = [];
    private DataSheetInterface $dataSheet;

    /**
     *
     * @param PermissionInterface[] $rowPermissions
     */
    public function __construct(DataSheetInterface $dataSheet, array $rowPermissions)
    {
        $this->rowPermissions = $rowPermissions;
        $this->dataSheet = $dataSheet;
    }

    /**
     * @return PermissionInterface[]
     */
    public function getRowPermissions(): array
    {
        return $this->rowPermissions;
    }
    
    public function getRowIndexesPermitted() : array
    {
        $idxs = [];
        foreach ($this->rowPermissions as $i => $rowPermission) {
            if ($rowPermission->isPermitted()) {
                $idxs[] = $i;
            }
        }
        return $idxs;
    }

    public function getRowIndexesDenied() : array
    {
        $idxs = [];
        foreach ($this->rowPermissions as $i => $rowPermission) {
            if ($rowPermission->isDenied()) {
                $idxs[] = $i;
            }
        }
        return $idxs;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\ObligationInterface::isFulfilled()
     */
    public function isFulfilled(): bool
    {
        return $this->fulfilled;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\ObligationInterface::setFulfilled()
     */
    public function setFulfilled(bool $trueOrFalse): ObligationInterface
    {
        $this->fulfilled = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\ObligationInterface::getExplanation()
     */
    public function getExplanation(): string
    {
        return count($this->getRowIndexesPermitted()) . ' of ' . count($this->getRowPermissions() . ' permitted');
    }
}