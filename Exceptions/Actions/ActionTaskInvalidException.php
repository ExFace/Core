<?php

namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

class ActionTaskInvalidException extends ActionInputError
{
    public const ISSUE_INVALID_OBJECT = 'invalidObject';
    public const ISSUE_UNEXPECTED_COLUMN = 'unexpectedColumn';
    
    private TaskInterface $task;
    private array $issues = [];
    
    public function __construct(ActionInterface $action, TaskInterface $task, $message, $alias = null, $previous = null)
    {
        $this->task = $task;
        parent::__construct($action, $message, $alias, $previous);
    }

    public function getTask() : TaskInterface
    {
        return $this->task;
    }
    
    public function addIssue(string $issue, string $alias) : void
    {
        $this->issues[$issue][$alias] = $alias;
    }
    
    public function getIssuesAll() : array
    {
        return $this->issues;
    }
    
    public function getIssue(string $issue) : array
    {
        return $this->issues[$issue] ?? [];
    }
}