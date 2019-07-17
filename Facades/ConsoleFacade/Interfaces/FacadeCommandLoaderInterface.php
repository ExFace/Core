<?php
namespace exface\Core\Facades\ConsoleFacade\Interfaces;

use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

interface FacadeCommandLoaderInterface extends CommandLoaderInterface
{
    public function getFacade() : FacadeInterface;
    
    public function getCommandNameFromAlias(string $alias) : string;
}