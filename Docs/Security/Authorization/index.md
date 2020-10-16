# Authorization and permissions

## Introduction

Once the user is [authenticated](../Authentication/index.md) (i.e. we know, which user it is), we can define, what this user is authorized (= allowed) to do. This is done by assigning roles to the user and creating policies allowing or denying access for these roles.  

Policies can be defined for any place in the workbench or any app running on it, that supports access permissions. These places are called "authorization points" (APs): e.g. the page AP to authorize access to UI pages, the action AP to authorize actions, etc. The core provides multiple authorization points out-of-the-box (see below), but even more can be added by apps if they need their own special authorization logic.

## Authorization points

- [Page authorization point](Authorization_points/Page_AP.md) to control access to UI pages and navigation items
- [Context authorization point](Authorization_points/Conext_AP.md) to control access to contexts
- [Action authorization point](Authorization_points/Action_AP.md) to restrict access to certain actions
- [Facade authorization point](Authorization_points/Facade_AP.md) to restrict access to certain facades
 
## Policies
 
## Extending the authorization by adding new APs 