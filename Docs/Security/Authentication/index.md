# Authentication

## Introduction

Authentication is the process of verifying the identity of a user. 

1. **Get credentials from request**. The users sends a set o credentials (username and password, a token string, etc.) when calling a facade
2. **Create a token container**. The facade determines the type of credentials and packs them into an authentication token instance. There are 
different types of tokens available and new types can be easily added.
3. **Verify token via authenticator**. The token object is considered "anonymous" until it is authenticated (verified) by an authenticator. The token is 
passed to the central security system, which will try to authenticate it using its list of acitve authenticators 
from `SECURITY.AUTHENTICATORS` in `System.config.json`. Each authenticator knows, which type of tokens it can handle.
If it can handle a token, it attempts to verify it: e.g. comparte the password, search for the API token among existing
user API tokens, etc. As soon, as the first authenticator is able to successfully verify the token, it creates and returns
a new token instance, which will return TRUE when the `isAnonymous()` method is called.
4. **Save authenticated token**. The token returned from a completed authentication run is stored in the security system and is considered as the
currently authenticated token/user. That token is anonymous or not depending on the outcome of the authentication 
process. It can be accessed any time via `$workbench->getSecurity()->getAuthenticatedToken()`.

## Available Authenticators

- [LDAP](LDAP.md) - logging in with windows credentials via Active Directory
- [Single-sign-on with a data connection](SSO_with_any_data_connection.md) - logging in with credentials for a data connection: a web service, an SQL-DB, etc.
- [Single-Sign-On via OAuth 2.0 and/or OpenID](https://github.com/axenox/OAuth2Connector/blob/master/Docs/index.md)
	- [OAuth with Google](https://github.com/axenox/GoogleConnector/blob/master/Docs/index.md)
	- [OAuth with Microsoft 365 / Azure](https://github.com/axenox/Microsoft365Connector/blob/master/Docs/index.md)
- [Single-Sign-On via SQL](SSO_via_SQL.md) - logging in with a password stored in any SQL database by another application.
- [One-time-password (OTP) for two-factor authentication](Two-factor_authentication.md) on-top of any other credential set (e.g. username + password)

## Token types

- Username and password tokens
  - `UsernamePasswordAuthToken` - a generic token for username + password pairs
  - `MetamodelUsernamePasswordAuthToken` - a special token for our own users. This is just handy to keep them apaort
  from external users/passwords.
  - `DomainUsernamePasswordAuthToken` - contains an additional "domain" field for authenticators like LDAP.
- Token-based authentication tokens
  - `ApiKeyAuthToken` - used to for our built-in API keys. The token is anonymous as long as no user was found for the API key.
  - `JWTAuthToken` - generic container for Java Web Tokens. It is considered anonymous until an authenticator decodes
  and verifies the token.
- Other token types
  - `OTPAuthToken` - a wrapper to carry a one-time password (OTP) for two-factor authentication on-top of any other
  credential set. These tokens will first get processed by the `TwoFactorTOPTAuthenticator` before the inner token is
  passed to the other authenticators.
  - `AnonymousAuthToken` - always anonymous, symbolizing that no credentials were supplied
  - `CliEnvAuthToken` - contains the currently authenticated user from the operating system. This is never anonymous,
  because the CLI always runs under some user (even it is a "nobody" user).
  - `RememberMeAuthToken` - contains credentials stored in the session.
  - `ExpiredAuthToken` - a special token indicating, that a previously remembered user is about to be signed out. The
  token is still considered verified (non-anonymous) and allows the workbench to finish its business even if the users
  session has already expired.