<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;

Interface CommunicationExceptionInterface extends ErrorExceptionInterface
{
    public function getCommunicationMessage() : CommunicationMessageInterface;
}