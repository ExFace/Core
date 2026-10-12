# Security

## Quick Links

- [Authentication (logging in)](Authentication/index.md)
  - Single-Sign-On
    - [LDAP (logging in with windows credentials)](Authentication/LDAP.md)
    - [SSO with any data connection (web services, databases, etc.)](Authentication/SSO_with_any_data_connection.md)
- [Authorization (verifying access to things)](Authorization/index.md)
- [Securing sensitive files and folders](Securing_installation_folders.md) in your installation via server-side access restrictions
- [Securing web facades](Securing_web_facades.md) by setting correct HTTP headers, etc.
- [Technical documentation](../developer_docs/Security/index.md)
- [Securing connections](Secure_data_sources/index.md) to data sources
  - [Securing SQL databases](Secure_data_sources/Securing_SQL_databases.md)

## Overview

The security system of any web application has lots of layers, that all need to be configured for every installation according to its requirements. Development and testing environments obviously need different security security settings than production. In reality, these settings are always a tradeoff between security, ease of use and transparency. In other words, the more your secure, the more complicated your system becomes.

At the heart of the security system of our no code platform, there is a centralized access control and permission system with three main layers:

- [**authentication**](Authentication/index.md) answers the question "who is the user and is it him or her really?"
- [**authorization**](Authorization/index.md) determines if that user is permitted to do something.
- **validation** checks user input, makes sure it matches expectations of the function the user tries to call and is not dangerous

Apart from these access control mechanisms, there are other features critical for security too. Only if all of them are configured carefully on a specific installation, it will have a high level of security:

- **client security** - use reliable devices and browsers adhering to modern security standards
- **transport security** - use secure communication protocols like HTTPS, VPN, etc. - see [installation docs](../Installation/index.md) for details.
- **server security** - protect your web server before and after the request is processed by the workbench - see [installation docs](../Installation/index.md) and [securing installation folders](Securing_installation_folders.md) for details.
- **encryption** - protect sensitive data inside data sources (see `EncryptedDataType` - TODO add docs)
- **data sensitivity markers** - clearly mark data, that requires special protection - TODO add docs
- [**security settings for data connections**](Secure_data_sources/index.md) - protect the communication with data sources
- **security settings inside data sources** - similarly to the server, your data need to be configured for security independently of the no-code platform.

## Access control

Obviously, authentication happens first to find out who the user actually is. Knowing the user now, [authorization](Authorization/index.md) is done every time the authenticated user attempts to access something behind an authorization point - a facade, an action, certain data, etc. So [authorization](Authorization/index.md) is actually checked many times during a request and only if all those authorization points are passed, the request is successful. Similarly, validation also happens multiple times in specific validation points.

All access control layers are highly extensible and built upon configurable model components.

## Users

The connecting part between all access control layers is the user with his roles and properties. Every person and every technical identity is always represented by a single core user entity. After Tha user is authenticated