<?php

namespace exface\Core\CommonLogic\Debugger;

class ProfilerLap
{
    private ProfilerLine $profilerLine;
    
    private float $startMs;
    private ?float $endMs = null;
    
    private int $startMemory;
    private ?int $endMemory = null;
    
    public function __construct(ProfilerLine $line, ?float $startMs = null, ?int $startMemory = null)
    {
        $this->profilerLine = $line;
        $this->startMs = $startMs ?? Profiler::getCurrentTimeMs();
        $this->startMemory = $startMemory ?? Profiler::getCurrentMemoryBytes();
    }
    
    public function stop() : ProfilerLap
    {
        $this->endMs = Profiler::getCurrentTimeMs();
        $this->endMemory = Profiler::getCurrentMemoryBytes();
        return $this;
    }
    
    public function getTimeStartMs() : float
    {
        return $this->startMs;
    }
    
    public function getTimeStopMs() : ?float
    {
        return $this->endMs;
    }
    
    public function getTimeTotalMs() : ?float
    {
        return $this->endMs === null ? null : $this->endMs - $this->startMs;
    }
    
    public function getMemoryStartBytes() : int
    {
        return $this->startMemory;
    }
    
    public function getMemoryStopBytes() : int
    {
        return $this->endMemory ?? $this->startMemory;
    }

    public function getMemoryAllocatedBytes() : int
    {
        return ($this->endMemory ?? $this->startMemory) - $this->startMemory;
    }

    public function getMemoryAvgBytes() : int
    {
        return (($this->endMemory ?? $this->startMemory) + $this->startMemory) / 2;
    }
    
    public function getMemoryPeakBytes() : int
    {
        return max($this->startMemory, $this->endMemory);
    }
    
    public function getProfilerLine() : ProfilerLine
    {
        return $this->profilerLine;
    }
    
    public function isMilestone() : bool
    {
        return $this->endMs === null;
    }
}