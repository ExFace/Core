<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\ConfigurationInterface;

Interface ConfigurationExceptionInterface
{
    /**
     *
     * @return ConfigurationInterface
     */
    public function getConfiguration() : ConfigurationInterface;
}