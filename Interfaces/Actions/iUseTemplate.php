<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Common interface for actions that use rendering templates
 * 
 * @author Andrej Kabachnik
 *
 */
interface iUseTemplate extends ActionInterface
{
    /**
     * 
     * @param mixed $value
     * @return iUseTemplate
     */
    public function setTemplate(string $value) : iUseTemplate;
}