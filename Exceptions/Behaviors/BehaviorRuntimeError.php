<?php
namespace exface\Core\Exceptions\Behaviors;

/**
 * Exception thrown if a behavior experiences an error at runtime (e.g.
 * not detectable at compile time).
 *
 * @author Andrej Kabachnik
 *        
 */
class BehaviorRuntimeError extends AbstractBehaviorException
{
}