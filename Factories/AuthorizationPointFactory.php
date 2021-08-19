<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Selectors\AuthorizationPointSelectorInterface;

/**
 * Constructs authorization points
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AuthorizationPointFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param AuthorizationPointSelectorInterface $selector
     * @return mixed
     */
    public static function createFromSelector(AuthorizationPointSelectorInterface $selector, array $constructorArguments = null)
    {
        $appSelector = $selector->getPrototypeAppSelector();
        $app = $selector->getWorkbench()->getApp($appSelector);
        if ($constructorArguments === null) {
            $constructorArguments = [$app];
        }
        return $app->get($selector, null, $constructorArguments);
    }
}