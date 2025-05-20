<?php

namespace exface\Core\Mutations;

use exface\Core\CommonLogic\Selectors\MutationPointSelector;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Mutations\MutationPointInterface;
use exface\Core\Interfaces\Mutations\MutatorInterface;
use exface\Core\Interfaces\Selectors\MutationPointSelectorInterface;

class Mutator implements MutatorInterface
{
    private $workbench = null;
    private $mutationPoints = [];

    public function __construct(Workbench $workbench)
    {
        $this->workbench = $workbench;
    }

    public function getMutationPoints() : array
    {
        return $this->mutationPoints;
    }

    public function getMutationPoint($selectorOrString) : MutationPointInterface
    {
        switch (true) {
            case $selectorOrString instanceof MutationPointSelectorInterface:
                $selector = $selectorOrString;
                break;
            case is_string($selectorOrString):
                $selector = new MutationPointSelector($this->getWorkbench(), $selectorOrString);
                break;
            default:
                throw new InvalidArgumentException('Invalid mutation point selector provided: expecting string or MutationPointSlector instance');
        }
        if ($selector->isClassname()) {
            $class = $selector->toString();
        } else {
            $class = PhpFilePathDataType::findClassInFile($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $selector->toString());
        }
        foreach ($this->mutationPoints as $mutationPoint) {
            if (is_a($mutationPoint, $class)) {
                return $mutationPoint;
            }
        }
        if (class_exists($class)) {
            $mutationPoint = new $class($this->getWorkbench());
            $this->mutationPoints[] = $mutationPoint;
            return $mutationPoint;
        }
        throw new UnexpectedValueException('Mutation point "' . $selector->toString() . '" not found');
    }

    public function getMutationsApplied($subject): array
    {
        $result = [];
        foreach ($this->getMutationPoints() as $mutationPoint) {
            $result[] = $mutationPoint->getMutationsApplied($subject);
        }
        return $result;
    }

    public function getWorkbench()
    {
        return $this->workbench;
    }
}