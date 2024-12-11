# Logging

The workbench includes a flexible logging engine, that produces easily browsable and very verbose logs visible in `Administration > Logs` and powering higher level features like the Monitor, MS Teams channel alerts, etc.

Technically the log system is defined by our own `LoggerInterface` that in turn adheres to the [PSR-3](https://www.php-fig.org/psr/psr-3/) standard, so the logger can be directly shared with third-party applications and libraries. This interface provides methods to add log messages and to register log handlers to actually write log messages to some storage (classes implementing the `LogHandlerInterface`).

The workbench provides a standard logger instance with a default log handler populating the visible logs and the option to register additional ones - e.g. separate log handlers for the Monitor, the tracer, etc. 

The default log handler is based on the wide spread PHP library [Monolog](https://github.com/Seldaek/monolog) with some custom log handlers and processors to customize it. Using Monolog for the default log allows us quickly set up log forwarding to any external aggregator, that supports Monolog!  

## Default log structure

The default logger puts all messages into CSV files - one file per day. The CSV format allows to easily read the log via `CsvBuilder` and a local file connection, so that logs are browsable in the administration UI.

Each log entry (message) has a short unique id called "Log-ID", that users should provide to the support team in case they need assistant. 

While the log itself basically only contains messages with some additional information, a detailed "debug widget" is saved separately if the sender of the log entry was a class capable of generating such debug widgets. This is what you see when you open a log entry. The very verbose debug widgets allow app designers and support people to understand what happend even without technical knowledge of the PHP or JavaScript code.

```
Inatalltaion folder
    - logs <-- main logs folder, which is maintained by the LogCleaner
        - Details
            - 2022-09-21
                - LogID1.json <-- debug widget for a log message (as UXON)
                - LogID2.json
                - ...
            - ...
        - Traces
            - TraceOfRequest1.csv <-- separate log file for a request trace with the same structure as the main log
            - ...
        - 2022-09-21.log <-- main log file for a day
        - 2022-09-22.log
        - ...
```

## Code overview

The workbench provides a default logger available via `$workbench->getLogger()`, which is instantiated upon first request via `LoggerFactory::createDefaultLogger()`. In order to populate the log structure described above, the following classes are used:

- `Logger` - our own PSR-3 logger to accept messages and pass them to its handlers
- `LoggerFactory` - static factory to setup the Logger and log handlers for different purposes
- Different log handlers to quickly set up ready-to use loggers implementing our own `LoggerInterface`
    - `MonologCsvFileHandler` saves the main log file in CSV format using the Monolog library. It implements the log structure described above by putting together Monolog parts like log processors and handlers - see below.
    - `MonitorLogHanlder` - writes logs to the monitor tables. This logger is not based on Monolog
    - `BufferingHanlder` - a wrapper to defer the actual log writing. This is used in the `Tracer` for example to make sure the trace is only written at the very end of the request
- Monolog add-ons
    - Monolog processors to populate log messages with information
        - see `MonologCsvFileHandler::getMonolog()`
    - Monolog log handlers. Not to be confused with our own log handlers! These here are Monolog add-ons and follow the rules of the Monolog library, while are own log handlers are library agnostic.
        - `FemtoPixel\Monolog\Handler\CsvHandler` - external CSV handler for Monolog
        - `DebugMessageMonologHandler` - a custom Monolog handler to generated log details files for every log message, that contains a "sender" capable of generating debug messages (i.e. implementing `iCanGenerateDebugWidgets` interface)
- `LogCleaner` - a static utility class to truncate logs, repair them and migrate to new versions