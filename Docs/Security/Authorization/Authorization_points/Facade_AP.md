# Facade authorization point

This authorization point allows to restrict access to specific facades to selected user roles only. These facades could then only be used by users with these roles regardless of the resources being accessed through the facade.

Most facades are accessible by any user by default (even unauthorized guests) - permissions are defined for the resources behind the facade like pages, actions, etc. However, there are facades, that have their own logic and do not work with instances of the metamodel: e.g. the `DocsFacade` in the core, that is responsible for rendering the app documentation or custom facades built to expose web services. In these cases, the facade authorization point can be used to create simple authorization rules without the overhead to write a custom AP.

## How to restrict access to a facade

### Restrict access for all users except for certain user roles

By default every newly added facade is accessible by any user by default, if it is needed to make a facade only accessible by a certain user group, for example if it is a facade to expose a web service, there are only a few rules to add to the Authorization Point for facades.

First the `Policy combining algorithm` of the authorization point needs to be set to `Permit overrides`, which basically means that if a policy exists that permits access to facade, users with the role, configured in that policy, will have permission to a facade, no matter other policies that include that role.

Second to deny every user role access to that facade add a policy for the facade authorization point. The policy needs to have the `Effect` `Deny`, no `User role` set and as `Facade` the facade that should be to restricted.
After doing that no user will have access to that facade, so there has to be an other policy added, that will permit access for the user role that should have access.

Third add another policy to the autorization point with the effect `Permit`, the same facade as `Facade` and as `User role` the role that should have access to the facade.

With these two policies no user has access to the facade, except the users that have the role as definied in the second policy.
When the facade should also be accessible for users with another user role just add another policy, with the same settings like the policy granting access for the first user role, but with the second user role as `User role`.

### Restrict access for one user role

To only restrict access for one user role add one policy to the facade authorization point. The `Effect` of the policy needs to be `Deny`, as `Facade` set the facade the access should be restricted for and as `User role` the user role which should not have access to the facade. With that policy, being the only policy affecting that facade, every user will have access to the facade except the users having the role definied in the just added policy.