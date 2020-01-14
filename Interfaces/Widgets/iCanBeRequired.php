<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * Interface for widgets, that can be marked as required.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanBeRequired extends iHaveValue
{
    public function isRequired();

    public function setRequired($value);
}