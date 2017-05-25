<?php
namespace exface\Core\Interfaces\Widgets;

interface iCanBeRequired extends iHaveValue
{

    public function isRequired();

    public function setRequired($value);
}