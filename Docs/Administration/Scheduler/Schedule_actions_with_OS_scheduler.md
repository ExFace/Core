# Schedule actions via built-in scheduler of the OS

Actions, that can be called from the command line, can be scheduled by means of the built-in task scheduler of the server's operating system: i.e. "crontab" on Linux or the "Windows Task Scheduler".

**HINT:** To find out, which actions can be used on the command line, simply go to `Administration > Console` in the main menu: all available commands will be listed when the console starts.

## Windows Task Scheduler

1. Open the "Task Scheduler" application
2. Click `Create Task...` on the actions-panel (right side of the screen)
3. Follow the wizard until being asked about the action to perform
4. Pick `Start a program` as action type and fill out the other fields as follows:

    - Program/script: `cmd`
    - Add arguments: `/c vendor\bin\action.bat exface.Core:ClearCache >> scheduler.log 2>&1`
    - Start in: `C:\wamp\www\exface`
    
Replace the action `exface.Core:ClearCache` and the path according to your needs.

Technically this configuration will result in the execution of a command in the `cmd` shell. The output of the action will be logged to the file `scheduler.log` located in the start-in-path.

**IMPORTANT:** this requires, that php is registered in your windows PATH environment variable. Otherwise the command line actions will not work.

## Linux crontab

TODO