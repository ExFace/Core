<?php

namespace exface\Core\CommonLogic\Utils;

use exface\Core\CommonLogic\DataSheets\CrudCounter;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * A development-only stopwatch for tracking execution times and CRUD (database) operations while
 * hunting performance bottlenecks. It is the PHP counterpart to the JS profiler `exfTools.perf`.
 *
 * IMPORTANT: never ship this. Remove every reference before merging to origin. The constructor
 * deliberately logs an exception on every instantiation as a reminder.
 *
 * ## Usage
 *
 * There are two ways to use it - a local instance, or an optional global singleton.
 *
 * ### Local instance
 * ```
 * $sw = new DebugStopWatch($workbench);
 * $sw->start();                                         // begin timing
 * $sw->takeLap('after query');                          // record a named checkpoint
 * $rows = $sw->measure('read', fn() => $sheet->dataRead()); // time a single block, get its result
 * $sw->stop();                                          // log all laps + CRUD totals
 * ```
 *
 * ### Global singleton
 * Use this to instrument code deep in the call stack without threading an instance through every method:
 * ```
 * DebugStopWatch::startGlobal($workbench);
 * // ... somewhere else entirely ...
 * DebugStopWatch::takeLapGlobal('after import');
 * DebugStopWatch::measureGlobal('save', fn() => $action->handle($task));
 * DebugStopWatch::stopGlobal();
 * ```
 *
 * ## Laps
 * Laps are keyed by label. Taking the same label again accumulates its time and CRUD deltas and bumps
 * the lap count, so you get per-label totals (and can derive averages). `takeLap()` measures the span
 * since the previous lap (or a `$from` you choose); `measure()` wraps a callable, times ONLY that block,
 * records it as a lap and returns the callable's result - even if it throws.
 *
 * ## CRUD counting
 * Pass meta objects to `start([$obj1, $obj2])` to also count Create/Read/Update/Delete operations on
 * those objects. Per-lap figures are deltas; the grand totals are printed by `stop()`.
 *
 * ## Cleaning up
 * When you think you are done, call `DebugStopWatch::setDetectReferences(true)` once at bootstrap: every
 * remaining `*Global()` call then throws with a stack trace, so you can find and delete all leftover
 * references before pushing.
 *
 * @see \exface\Core\CommonLogic\DataSheets\CrudCounter
 */
class DebugStopWatch
{
    private const TIME = 'time';
    private const DELTA_TIME = 'delta_time';
    private const FROM = 'from';
    private const LAPS = 'laps';
    private const READS = 'reads';
    private const WRITES = 'writes';
    private const UPDATES = 'updates';
    private const DELETES = 'deletes';

    private static ?DebugStopWatch $globalInstance = null;

    /**
     * Set this to TRUE via `setDetectReferences()` to make every global call throw. Use it to locate
     * and remove all leftover `*Global()` references before pushing your changes.
     *
     * @var bool
     */
    private static bool $detectReferences = false;

    /**
     * Properties listed in this array will be displayed as lap output.
     *
     * @var array|string[]
     */
    private static array $lapOutput = [
        //self::TIME,
        self::DELTA_TIME,
        self::FROM,
        self::READS,
        self::LAPS,
        //self::WRITES,
        //self::UPDATES,
        //self::DELETES
    ];

    private CrudCounter $crudCounter;
    private float $startTime = -1;
    private float $lastTime = -1;
    private float $pinnedTime = -1;
    private array $laps = [];
    // CrudCounter exposes cumulative totals, so per-lap figures are derived as deltas against this snapshot.
    private array $crudSnapshot = [
        self::READS => 0,
        self::WRITES => 0,
        self::UPDATES => 0,
        self::DELETES => 0
    ];

    public function __construct(WorkbenchInterface $workbench)
    {
        $workbench->getLogger()->logException(new RuntimeException(
            'DebugStopWatch initialized.' .
            ' The stopwatch should not be initialized in live builds.' .
            ' Remove all references to DebugStopWatch in production!'
        ));
        $this->crudCounter = new CrudCounter($workbench);
    }

    /**
     * Start the stopwatch. As long as it is running you can take laps anytime.
     *
     * NOTE: If the stopwatch is already running, calling `start` will reset the stopwatch and start a new timing session.
     *
     * @param array $listenForObjects
     * @param bool  $safe
     * If TRUE, this function does nothing if this instance is already running.
     * @return $this
     */
    public function start(array $listenForObjects = [], bool $safe = false) : DebugStopWatch
    {
        if($safe && $this->isRunning()) {
            return $this;
        }

        $this->stop();

        $this->startTime = $this->lastTime = microtime(true);
        $this->pinnedTime = -1;

        if(!empty($listenForObjects)) {
            $this->crudCounter->start($listenForObjects);
        }

        $this->crudSnapshot = $this->currentCrudCounts();

        return $this;
    }

    /**
     * Stop the current timing session, recording the lap 'final' and stops the CRUD counter if available.
     *
     * NOTE: This function does nothing if the stopwatch has not been started.
     *
     * @return $this
     */
    public function stop(bool $sortOutput = false) : DebugStopWatch
    {
        if($this->startTime > -1) {
            $this->takeLapInternal('final', false);
            $this->crudCounter->stop();
            $this->startTime = -1;

            if($sortOutput) {
                uasort($this->laps, function($a, $b) {
                    return $b[self::DELTA_TIME] <=> $a[self::DELTA_TIME];
                });
            }

            $lapOutput = [];
            foreach ($this->laps as $label => $lap) {
                foreach (array_keys($lap) as $property) {
                    if(in_array($property, self::$lapOutput)) {
                        $value = $lap[$property];
                        $value = is_float($value) ? number_format($value, 6) : $value;
                        $lapOutput[$label][$property] = $value;
                    }
                }
            }

            $this->crudCounter->getWorkbench()->getLogger()->logException(new RuntimeException(
                'DebugStopWatch stopped.' . PHP_EOL . PHP_EOL .
                json_encode($lapOutput, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL .
                self::READS . ': ' . $this->crudCounter->getReads() . PHP_EOL .
                self::WRITES . ': ' . $this->crudCounter->getWrites() . PHP_EOL .
                self::UPDATES . ': ' . $this->crudCounter->getUpdates() . PHP_EOL .
                self::DELETES . ': ' . $this->crudCounter->getDeletes()
            ));
        }

        return $this;
    }

    /**
     * Take a lap and store it under the given label. Labels are unique and calling this function
     * again with the same label adds the deltas to the existing lap.
     *
     * @param string      $label
     * The label for your lap. Cannot be 'final'.
     * @param string|bool $from
     * If TRUE, the lap time will be calculated from the last lap. If FALSE, it will be calculated from the start time.
     * If you provide a string, it will be treated as the label of a previous lap to calculate the time from.
     * @param bool        $safe
     * If TRUE, this function does nothing if the stopwatch isn't running.
     * @return $this
     * @throws InvalidArgumentException
     * Throws an exception when called while the stopwatch isn't running or if 'final' is used as a label.
     */
    public function takeLap(string $label, string|bool $from = true, bool $safe = false) : DebugStopWatch
    {
        if(!$this->isRunning()) {
            if($safe) {
                return $this;
            } else {
                throw new InvalidArgumentException("Stopwatch has not been started.");
            }
        }

        if($label === 'final') {
            throw new InvalidArgumentException("Label '$label' is reserved and cannot be used.");
        }

        return $this->takeLapInternal($label, $from);
    }

    /**
     * Run $callback, record ONLY its execution time (and CRUD delta) under $label, and return its result.
     *
     * This is the closure-oriented counterpart to `takeLap()`: instead of measuring the span since the
     * previous lap, it isolates the callable. The lap is recorded even if $callback throws. Time spent
     * before the block (since the last lap) is not attributed to it, and later laps continue from the
     * block's end.
     *
     * @param string   $label
     * The label for the lap. Cannot be 'final'.
     * @param callable $callback
     * @param bool     $safe
     * If TRUE, the callback is run untimed (instead of throwing) when the stopwatch isn't running.
     * @return mixed
     * Whatever $callback returns.
     * @throws InvalidArgumentException
     * When the stopwatch isn't running (unless $safe) or if 'final' is used as a label.
     */
    public function measure(string $label, callable $callback, bool $safe = false) : mixed
    {
        if(!$this->isRunning()) {
            if($safe) {
                return $callback();
            } else {
                throw new InvalidArgumentException("Stopwatch has not been started.");
            }
        }

        if($label === 'final') {
            throw new InvalidArgumentException("Label '$label' is reserved and cannot be used.");
        }

        $start = microtime(true);
        $crudBefore = $this->currentCrudCounts();

        try {
            return $callback();
        } finally {
            $now = microtime(true);
            $crudAfter = $this->currentCrudCounts();
            $crud = [
                self::READS => $crudAfter[self::READS] - $crudBefore[self::READS],
                self::WRITES => $crudAfter[self::WRITES] - $crudBefore[self::WRITES],
                self::UPDATES => $crudAfter[self::UPDATES] - $crudBefore[self::UPDATES],
                self::DELETES => $crudAfter[self::DELETES] - $crudBefore[self::DELETES]
            ];
            $this->recordLap($label, $now, $now - $start, 'measured', $crud);
            // Advance the boundaries so later laps/timings continue from the end of the measured block.
            $this->lastTime = $now;
            $this->crudSnapshot = $crudAfter;
        }
    }

    /**
     * @param string      $label
     * @param string|bool $from
     * @return $this
     */
    protected function takeLapInternal(string $label, string|bool $from = true) : DebugStopWatch
    {
        $now = microtime(true);
        $this->lastTime = $now;
        $crud = $this->takeCrudDeltas();
        // Evaluate the delta before reading $from: takeTimeInternal() normalizes it to 'previous'/'start'.
        $deltaTime = $this->takeTimeInternal($from);

        $this->recordLap($label, $now, $deltaTime, $from, $crud);

        return $this;
    }

    /**
     * Write a lap: create it, or accumulate deltas and bump the lap count if the label already exists.
     *
     * @param string      $label
     * @param float       $time
     * @param float       $deltaTime
     * @param string|bool $from
     * @param array       $crud
     * @return void
     */
    private function recordLap(string $label, float $time, float $deltaTime, string|bool $from, array $crud) : void
    {
        if(!key_exists($label, $this->laps)) {
            $this->laps[$label] = [
                self::TIME => $time,
                self::DELTA_TIME => $deltaTime,
                self::FROM => $from,
                self::LAPS => 1,
                self::READS => $crud[self::READS],
                self::WRITES => $crud[self::WRITES],
                self::UPDATES => $crud[self::UPDATES],
                self::DELETES => $crud[self::DELETES]
            ];
        } else {
            $this->laps[$label][self::TIME] = $time;
            $this->laps[$label][self::DELTA_TIME] += $deltaTime;
            $this->laps[$label][self::LAPS] += 1;
            $this->laps[$label][self::READS] += $crud[self::READS];
            $this->laps[$label][self::WRITES] += $crud[self::WRITES];
            $this->laps[$label][self::UPDATES] += $crud[self::UPDATES];
            $this->laps[$label][self::DELETES] += $crud[self::DELETES];
        }
    }

    /**
     * Measure the time from now to some specified point in the past.
     *
     * @param string|bool $from
     * If TRUE, the time will be calculated from the last lap or pinned time. If FALSE, it will be calculated from the start time.
     * If you provide a string, it will be treated as the label of a previous lap to calculate the time from.
     * @param bool        $pinTime
     * If TRUE, the time taken will be pinned for the next call of this function. If you wish to take time from the last pinned
     * time pass TRUE for `FROM`.
     * @return float
     */
    public function takeTime(string|bool $from = true, bool $pinTime = false) : float
    {
        return $this->takeTimeInternal($from, $pinTime);
    }

    /**
     * @param string|bool $from
     * @param bool        $pinTime
     * @return float
     */
    protected function takeTimeInternal(string|bool &$from, bool $pinTime = false) : float
    {
        $now = microtime(true);

        switch (true) {
            case is_string($from):
                if(key_exists($from, $this->laps)) {
                    $last = $this->laps[$from][self::TIME];
                } else {
                    $last = $this->lastTime;
                    $from = 'previous';
                }
                break;
            case $from === true:
                $last =  $this->pinnedTime > -1 ? $this->pinnedTime : $this->lastTime;
                if ($pinTime) {
                    $this->pinnedTime = $now;
                }
                $from = 'previous';
                break;
            default:
                $last = $this->startTime;
                $from = 'start';
                break;
        }

        return $now - $last;
    }

    /**
     * Returns the current cumulative CRUD totals from the counter, coalescing unknown values to 0.
     *
     * @return array
     */
    private function currentCrudCounts() : array
    {
        return [
            self::READS => $this->crudCounter->getReads() ?? 0,
            self::WRITES => $this->crudCounter->getWrites() ?? 0,
            self::UPDATES => $this->crudCounter->getUpdates() ?? 0,
            self::DELETES => $this->crudCounter->getDeletes() ?? 0
        ];
    }

    /**
     * Returns the CRUD operations counted since the previous lap and advances the snapshot.
     *
     * @return array
     */
    private function takeCrudDeltas() : array
    {
        $current = $this->currentCrudCounts();
        $deltas = [
            self::READS => $current[self::READS] - $this->crudSnapshot[self::READS],
            self::WRITES => $current[self::WRITES] - $this->crudSnapshot[self::WRITES],
            self::UPDATES => $current[self::UPDATES] - $this->crudSnapshot[self::UPDATES],
            self::DELETES => $current[self::DELETES] - $this->crudSnapshot[self::DELETES]
        ];
        $this->crudSnapshot = $current;

        return $deltas;
    }

    /**
     * @return array
     */
    public function getLaps() : array
    {
        return $this->laps;
    }

    /**
     * @return bool
     */
    public function isRunning() : bool
    {
        return $this->startTime > -1;
    }

    /**
     * @see DebugStopWatch::start()
     *
     * @param WorkbenchInterface $workbench
     * @param array              $listenForObjects
     * @return DebugStopWatch
     */
    public static function startGlobal(WorkbenchInterface $workbench, array $listenForObjects = []) : DebugStopWatch
    {
        self::throwRemoveReferenceError();

        if (self::$globalInstance === null) {
            self::$globalInstance = new DebugStopWatch($workbench);
        }

        self::$globalInstance->start($listenForObjects, true);

        return self::$globalInstance;
    }

    /**
     * @param string      $label
     * @param string|bool $from
     * @return DebugStopWatch|null
     * @see DebugStopWatch::takeLap()
     *
     */
    public static function takeLapGlobal(string $label, string|bool $from = true) : ?DebugStopWatch
    {
        self::throwRemoveReferenceError();
        self::$globalInstance?->takeLap($label, $from, true);
        return self::$globalInstance;
    }

    /**
     * If the global stopwatch hasn't been started, $callback is run untimed and its result returned.
     *
     * @param string   $label
     * @param callable $callback
     * @return mixed
     * @see DebugStopWatch::measure()
     */
    public static function measureGlobal(string $label, callable $callback) : mixed
    {
        self::throwRemoveReferenceError();
        if (self::$globalInstance === null) {
            return $callback();
        }
        return self::$globalInstance->measure($label, $callback, true);
    }

    /**
     * Returns 0, if the global stop watch hasn't been started yet.
     *
     * @param string|bool $from
     * @param bool        $pinTime
     * @return float
     * @see DebugStopWatch::takeTime()
     */
    public static function takeTimeGlobal(string|bool $from = true, bool $pinTime = false) : float
    {
        self::throwRemoveReferenceError();
        return self::$globalInstance?->takeTime($from, $pinTime) ?? 0;
    }

    /**
     * @param bool $sortOutput
     * @return DebugStopWatch|null
     * @see DebugStopWatch::stop()
     */
    public static function stopGlobal(bool $sortOutput = false) : ?DebugStopWatch
    {
        self::throwRemoveReferenceError();
        self::$globalInstance?->stop($sortOutput);
        return self::$globalInstance;
    }

    /**
     * @return bool
     */
    public static function isRunningGlobal() : bool
    {
        self::throwRemoveReferenceError();
        return self::$globalInstance?->isRunning() ?? false;
    }

    /**
     * @return array
     */
    public static function getLapsGlobal() : array
    {
        self::throwRemoveReferenceError();
        return self::$globalInstance?->getLaps() ?? [];
    }

    /**
     * Enable or disable stale-reference detection for the global stopwatch.
     *
     * While enabled, every `*Global()` call throws, so leftover references surface (with a stack
     * trace) and can be removed before pushing.
     *
     * @param bool $trueOrFalse
     * @return void
     */
    public static function setDetectReferences(bool $trueOrFalse) : void
    {
        self::$detectReferences = $trueOrFalse;
    }

    /**
     * @return void
     */
    private static function throwRemoveReferenceError() : void
    {
        if (self::$detectReferences) {
            throw new \RuntimeException("Stale reference to DebugStopWatch detected! Remove it before pushing your changes.");
        }
    }
}