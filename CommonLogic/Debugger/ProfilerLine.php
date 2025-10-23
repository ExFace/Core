<?php

namespace exface\Core\CommonLogic\Debugger;

use exface\Core\DataTypes\TimeDataType;

class ProfilerLine
{
    private Profiler $profiler;
    private string $name;
    private ?string $category = null;
    private ?string $phpClass = null;
    private array $data = [];
    
    private array $laps = [];
    
    private ?float $startMs = null;
    private ?float $startMemory = null;
    
    public function __construct(Profiler $profiler, string $name, ?string $category = null, ?string $phpClass, array $data = [])
    {
        $this->profiler = $profiler;
        $this->name = $name;
        $this->category = $category;
        $this->phpClass = $phpClass;
        $this->data = $data;
    }
    
    public function getProfiler(): ?Profiler
    {
        return $this->profiler;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getCategory(): ?string
    {
        return $this->category;
    }
    
    public function getPhpClass(): ?string
    {
        return $this->phpClass;
    }
    
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return ProfilerLap[]
     */
    public function getLaps() : array
    {
        return $this->laps;
    }
    
    public function getLap(int $id) : ProfilerLap
    {
        return $this->laps[$id];
    }
    
    public function startLap() : ProfilerLap
    {
        $idx = count($this->laps);
        if ($idx === 0) {
            $this->startMs = Profiler::getCurrentTimeMs();
            $this->startMemory = Profiler::getCurrentMemoryBytes();
        }
        $lap = new ProfilerLap($this);
        $this->laps[] = $lap;
        return $lap;
    }
    
    public function stopLap(?int $lapId = null) : ProfilerLap
    {
        $lapId = $lapId ?? array_key_last($this->laps);
        $lap = $this->laps[$lapId];
        $lap->stop();
        return $lap;
    }
    
    public function getTimeStartMs() : ?float
    {
        return $this->startMs;
    }
    
    public function getTimeStopMs() : ?float
    {
        $max = null;
        foreach ($this->getLaps() as $lap) {
            if ($lap->isMilestone() === true) {
                continue;
            }
            $max = max($max, $lap->getTimeStopMs());
        }
        return $max;
    }
    
    public function getTimeTotalMs() : ?float
    {
        $sum = null;
        foreach ($this->getLaps() as $lap) {
            if ($lap->isMilestone() === true) {
                continue;
            }
            $sum += $lap->getTimeTotalMs();
        }
        return $sum;
    }

    public function getTimeElapsedMs() : ?float
    {
        $start = $this->getTimeStartMs();
        $stop = $this->getTimeStopMs();
        return $stop === null ? null : $stop - $start;
    }
    
    public function getMemoryStartBytes() : ?int
    {
        return $this->startMemory;
    }

    public function getMemoryConsumedBytes() : int
    {
        $total = 0;
        foreach ($this->getLaps() as $lap) {
            $total += $lap->getMemoryConsumedBytes();
        }
        return $total;
    }
    
    public function getMemoryAvgBytes() : float
    {
        $sum = $this->getMemoryConsumedBytes();
        return $this->countLaps() === 0 ? 0 : ($sum / $this->countLaps());        
    }

    public function getTimeAvgMs() : float
    {
        $sum = $this->getTimeTotalMs();
        return $this->countLaps() === 0 ? 0 : ($sum / $this->countLaps());
    }
    
    public function isMilestone() : bool
    {
        $hasStop = false;
        foreach ($this->getLaps() as $lap) {
            if (! $lap->isMilestone()) {
                $hasStop = true;
                break;
            }
        }
        return ! $hasStop;
    }
    
    public function countLaps() : int
    {
        return count($this->laps);
    }
}