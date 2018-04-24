# Setting up behaviors for meta objects

Behaviors, as the name suggest, are models for behavior rules of a meta object. For example:

- Many business objects, for example, have a state. Depending on that state differen operations are possible and there are rules for transitions between states. This means, that this object behaves like a state machine and, thus, it needs a `StateMachineBehavior` in our model. 
- Another typical example are objects, that remember the time they were last edited. This is not only convenient, but also helps detect concurrent write operations (e.g. from different users working at the same time). These object need the `TimeStampingBehavior`

## Behavior configuration

TODO

## Behavior types

TODO