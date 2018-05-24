# Overview of the technical architecture

In a nutshell the platform acts as command bus behind a changable facade:

![Processing a request](../diagrams/sequence_overview.png)

Regardless of where it is called from (an HTTP endpoint, a console application or anything else), the caller talks to a template (= facade), which actually defines what sort of input it accepts and what the output will be. This input data can be anything, but in general, it must contain some kind of reference to the action that is to be performed. Once the caller passes some valid input data (e.g. an HTTP request or a CLI command), this data gets transformed by the template into an internal structure called "Task", which essentially is a data trasnfer object (DTO) similar to a classical command. The task contains all information neccessary for further processing. 

The template now passes the task to an app to get it handled. The app is comparable to a command bus at this point: it uses it's internal logic to resolve a handler capable to actually perform the required action and return a result-object (another type of internal DTO). In most apps these handlers are action classes: each responsible for a single action [prototype](../understanding_the_metamodel/prototypes.md). The result object is returned back to the template. 

Using the result type and contents, the template can now render a response that is understandable to caller (e.g. an HTTP response or CLI output) and finally return it to the caller.