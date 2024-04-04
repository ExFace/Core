# Facade authorization points

Facades should use their own authorization points to allow authorization for resources they provide access to. By default, the authorization points described in the following chapters are provided.

Note, that facade authorization points are the outer-most security tear of the workbench. The define, if a user is authorized to query a facade. Depending on the nature of the facade, other authorization points may be triggered subsequently: e.g.

- GUI facades will attempt to perform actions on UI pages, thus triggering [action](Action_AP.md) and [page](Page_AP.md) authorization points.
- CLI facades will also perform actions, but are generally not bound to pages
- the `HttpFileServerFacade` will query data sources triggering the [Data authorization point](Data_AP.md)

## HTTP request authorization point

This authorization point is used in all standard HTTP facades. It is denying by default, so HTTP access needs to be permitted explicitly. However, most facades come with their own preconfigured policies: for example GUI facades will generally be allowed for everyone, while API facades like the built-in `HttpTaskFacade` is only available for logged in users.

Policies can be applied to all requests or only to those matching conditions like `url_path_pattern`, `body_pattern`, etc. This can be used to restrict access to certain areas similarly to the authorization logic of many typical router-based frameworks. For example, using these conditions, you can restrict access to certain areas of the documentation via the `DocsFacade`.

## Command line authorization point

This authorization point is used by the `ConsoleFacade` to control access from the local command line on the server. It is permissive by default:
 
- The `Policy combining algorithm` is `Permit overrides`, which basically means that if a policy exists that permits access to the facade, users with the role configured in that policy, will have access, no matter what other policies are applicable to that role. 
- The `Default Effect` is `Permit`. This means, if no policy can be applied for a user, access will be granted.

### Restrict CLI access for all users except for certain user roles

1. Deny every user role access to that facade add a policy for the facade authorization point. After doing that no user will have access to that facade, so there has to be an other policy added, that will permit access for the user role that should have access.
  - Effect: `Deny`
  - User role: none
  - Facade: the desired CLI facade - e.g. `ConsoleFacade`
2. Add another policy to the autorization point with the effect `Permit`, the same facade as `Facade` and as `User role` the role that should have access to the facade.

With these two policies no user has access to the facade, except the users that have the role as definied in the second policy.
When the facade should also be accessible for users with another user role just add another policy, with the same settings like the policy granting access for the first user role, but with the second user role as `User role`.

### Deny CLI access for one user role

To only restrict access for one user role add the following policy to the facade authorization point: 

- Effect: `Deny`, 
- Facade: the desired CLI facade - e.g. `ConsoleFacade`
- User role: the user role which should not have access to the facade. 

With that policy, being the only policy affecting that facade, every user will have access to the facade except the users having the role definied in the just added policy.