# How does the security system work technically?

All the services related to security are accessible via `$workbench->getSecurity()`. This method returns an instance of the `SecurityManagerInterface`, which provides methods to handle

- authentication (which user are we dealing with?)
	- `authenticate($token)`
	- `getAuthenticatedUser()` and `getAuthenticatedToken`
	- `getUser($token)`
- authorization (what is the user allowed to do?)
	- `getAutheorizationPoint($selector)`
	
Most notable classes and interfaces in the security system are

- `AuthenticationProvider`s (implementing `AuthenticationProviderInterface`) are classes that can authenticate tokens
	- `Authenticator`s - implementaions of workbench authentication logic and single-sign-on
	- `DataConnector`s are also authentication providers as a workbench user usually must authenticate in the data source to gain access to it.
- `AuthenticationToken`s - containers for information required for authentication. Once authenticated, a token can exchanged for the corresponding user via `$workbench->getSecurity()->getUser($token)`.
- `User`s - metamodel instances representing a workbench user with it's UID, username, roles and other information.
- `AuthorizationPoint`s (APs) represent places in the business logic that may require a special permission for a user to access or execute. For example, the `PageAuthorizationPoint` controls the access to UI pages and menu items, the `ActionAuthorizationPoing` determines if a user has permission for a specific action, etc.
- `AuthorizationPolicie`s are the rules, that define permissions for authorization points. Each authorization point can have it's own policies and logic, but a unified stucture of the authorization system is essential to keep the configuration as simple and understanable as possible.

## Authentication

The main task of authentication is to verify if an `AuthenticationToken` provided from outside (e.g. by a facade and ultimately by the user) is valid and to find the corresponding user in the metamodel, which will be used in the authorization processes later on. 

There are different type of authentication tokens. Each contains all relevant information about the user, that is known at the time of authentication: e.g. the username and password if the user simply tries to login or even "nothing" if it is an `AnonymousAuthToken`.

The authentication is triggered in the following cases:

- An authentication token is actively passed to `$workbench->getSecurity()->authenticate()` either by a facade or the `Login` action - both attempting to authenticate a user based on the provided credentials like username and password.
	- The provided token is passed to every `Àuthenticator` registered in the `System.config.json` first calling `$authenticator->isSupported($token)` and, if so, trying `$authenticator->authenticate($token)`. The latter either returns an authenticated token or throws an `AuthenticationFailedError`.
	- If any of the authenticators could authenticate the token, the user behind it is concidered logged on.
- The method `$workbench->getSecurity->getAuthenticatedToken()` is called.
	- If a token was authenticated previously, it will be returned
	- If not, a `RememberMeAuthToken` will be created an passed through the chain of authenticators to see if any of them can authenticate it.
	- If all the above fails, an `AnonymousAuthToken` will be returned.
- The method `$workbench->getSecurity->getAuthenticatedUser()` is called, which basically simply uses the authenticated token from above to load the corresponding user and return it.

Both, the token and the user identify a physical user in the end. They both implement the `UserImpersonationInterface` that allows to use both in authorization processes later on. The token however is a more lightweight object, that only contains information required for the authentication. 

### Single-sign-on with external systems

A very important aspect of authorization is the possibility to log in using credentials of an external system like Windows, a directory service like LDAP, a cloud like Azure or even a data source like SAP. This is where the different `Authenticator`s come into play: each is responsible for a specific authentication logic - internal (username and password) or external.

If a token is authenticated against an external authentication provider, the authenticator will find the corresponding workbench user (or even create one) and that user will be concidered logged in.

Read more about the available authenticators in the corresponding [user manual chapter](../../Security/Authentication/index.md).

### Login and logout actions

TODO

## Authentication in data sources

Similarly to logging into the workbench, the user also must authenticate in every data source that is to be used. `DataConnector`s also have a method `authenticate($token)`. In fact, they all implement the `AuthenticationProviderInterface` too. 

However, the credentials required for a data source (username, password, API-key, etc.) and, thus, the authentication tokens are mostly different from those used in the workbenchs authenticators. These credentials can either be stored in the data connection model (for all users) or in the internal secure user credential storage.

Using similar authentication logic for the workbench and the data sources allows to use the same `Login` and `Logout` actions and reuse the code to perform the authentication.

Ther is even a bridge between the two: the `DataConnectionAuthenticator` allows to use any data source for single-sign-on!

## Authorization

TODO