<?php
namespace exface\Core\CommonLogic\Debugger;

class ProfilerLine
{
    private Profiler $profiler;
    private string $name;
    private ?string $category = null;
    private ?string $phpClass = null;
    private array $data = [];

    /**
     * @var ProfilerLap[] 
     */
    private array $laps = [];
    private ?ProfilerLap $lastLap = null;
    
    private ?float $startMs = null;
    private ?float $startMemory = null;

    /**
     * @param Profiler $profiler
     * @param string $name
     * @param string|null $category
     * @param string|null $phpClass
     * @param array $data
     */
    public function __construct(Profiler $profiler, string $name, ?string $category = null, ?string $phpClass = null, array $data = [])
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
    
    public function getLap(int $id) : ?ProfilerLap
    {
        return $this->laps[$id] ?? null;
    }
    
    public function startLap() : ProfilerLap
    {
        $nowMs = Profiler::getCurrentTimeMs();
        $nowMem = Profiler::getCurrentMemoryBytes();
        if (empty($this->laps)) {
            $this->startMs = $nowMs;
            $this->startMemory = $nowMem;
        }
        $lap = new ProfilerLap($this, $nowMs, $nowMem);
        $this->laps[] = $lap;
        $this->lastLap = $lap;
        return $lap;
    }
    
    public function stopLap(?int $lapId = null) : ProfilerLap
    {
        if ($lapId !== null) {
            $lap = $this->lastLap;
        } else {
            $lap = end($this->laps);
        }
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
        foreach ($this->laps as $lap) {
            $stopMs = $lap->getTimeStopMs();
            if ($stopMs !== null) {
                $max = max($max, $stopMs);
            }
        }
        return $max;
    }
    
    public function getTimeTotalMs() : ?float
    {
        $sum = null;
        foreach ($this->laps as $lap) {
            if (null !== $ms = $lap->getTimeTotalMs()) {
                $sum += $ms;
            }
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

    public function getMemoryStopBytes() : ?int
    {
        return $this->lastLap === null ? null : $this->lastLap->getMemoryStopBytes();
    }

    public function getMemoryAllocatedBytes() : int
    {
        $total = 0;
        foreach ($this->laps as $lap) {
            $total += $lap->getMemoryAllocatedBytes() ?? 0;
        }
        return $total;
    }

    public function getMemoryAvgBytes() : float
    {
        $cnt = 0;
        $sum = 0;
        foreach ($this->laps as $lap) {
            $sum += $lap->getMemoryAvgBytes();
            $cnt++;
        }
        return $cnt === 0 ? 0 : ($sum / $cnt);
    }

    public function getMemoryPeakBytes() : float
    {
        $max = $this->startMemory;
        foreach ($this->laps as $lap) {
            $max = max($max, $lap->getMemoryPeakBytes());
        }
        return $max;
    }
    
    public function getMemoryAvgBytesPerLap() : float
    {
        $sum = $this->getMemoryAllocatedBytes();
        $cnt = $this->countLaps();
        return $cnt === 0 ? 0 : ($sum / $cnt);        
    }

    public function getTimeAvgMs() : float
    {
        $sum = $this->getTimeTotalMs();
        $cnt = $this->countLaps();
        return $cnt === 0 ? 0 : ($sum / $cnt);
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