<?php
namespace exface\Core\Interfaces\Mutations;

interface AppliedMutationOnArrayInterface extends AppliedMutationInterface
{
    public function dumpStateBeforeAsArray() : array;

    public function dumpStateAfterAsArray() : array;
}