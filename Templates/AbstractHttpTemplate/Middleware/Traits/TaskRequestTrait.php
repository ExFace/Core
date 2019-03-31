<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware\Traits;

use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Factories\TaskFactory;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
Trait TaskRequestTrait
{    
    /**
     * 
     * @param ServerRequestInterface $request
     * @param string $attributeName
     * @param HttpFacadeInterface $facade
     * @return HttpTaskInterface
     */
    protected function getTask(ServerRequestInterface $request, string $attributeName, HttpFacadeInterface $facade) : HttpTaskInterface
    {
        $task = $request->getAttribute($attributeName);
        if ($task === null) {
            $task = TaskFactory::createHttpTask($facade, $request);
        }
        
        return $task;
    }
    
    /**
     * 
     * @param HttpTaskInterface $task
     * @param string $methodName
     * @param mixed $value
     * @throws \LogicException
     * @return HttpTaskInterface
     */
    protected function updateTask(HttpTaskInterface $task, string $methodName, $value) : HttpTaskInterface
    {
        if (! method_exists($task, $methodName)) {
            throw new \LogicException('Call to undefined method ' . get_class($task) . '::' . $methodName);
        }
        return call_user_func([$task, $methodName], $value);
    }
}