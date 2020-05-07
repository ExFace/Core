# Action authorization point

This authorization point allows to restrict access to specific actions to selected user roles only. These actions could then only be used by users with these roles.

Most actions are accessible by any user by default but it might be necessary to restrict access to certain actions, e.g. the action to delete a dataset.

## How to restrict access to a facade

### Restrict access for all users except for certain user roles

To restrict access to an action for users and make it accessible for only a certain user role, e.g only users with the role `Manager` should be allowed to delete datasets, use this authorization point.

First the authorization point `Policy combining algorithm` needs to be set to `Permit Overrides`. The `Default Effect` of the authorization point needs to beset to `Permit`. This means if no policy can be applied for for a user he will have access to the action.

Second add a policy to that authorization point denying every user the access to the delete action. The policy needs to have `Deny` as `Effect` and no user role. It is possible to either set `Action prototype` or `Action Model` for a policy but **NOT** both at the same time. Set `Action prototype` if every action based on that prototype, eg. `exface.core.DeleteObject`, should be restriced, or set `Action Model` if only that specific action model should be restricted. After adding this policy no user will be able to use that action, so a second policy is needed to allow the desired user role, e.g. `Manager`, access to that action.

Third add a second policy with the `Effect` `Permit`, the desired user role, e.g. `Manager`, as `User role` and the action as in the first policy.

Those settings will have the effect that no user, except users with the role `Manager`, will have access to the action.
Should also users with another user role be able to access that action, just add a third policy with the same settings as the second one, except the new user roles as `User role`.

### Restrict access for certain user role

To only restrict access for one user role add one policy to the action authorization point. The `Effect` of the policy needs to be `Deny`, as `Action protoype` or `Action model` set the action access should be restricted for and as `User role` set the user role which should not have access to the action. With that policy, being the only policy affecting that action, every user will have access to the action except the users having the role definied in the just added policy.

It is also possible to restrict access for users to a certain action prototype but grant access to an action that is based on that prototype. For example the access to see dialogs can be completely restricted for a certain user role, but grant access to the action to show a specific dialog that is based on that action prototype.

To get that restriction two policies have to be added to the authorization point.
The first policy needs to restrict access to the show dialog action protoype for desired user role, meaning the `Effect` of the policy needs to be `Deny`, the `Action prototype` needs to be set to the `exface.core.ShowDialog` action and  `User role` needs to be set to the user role you want to restrict access for.

The second policy to add need to have `Permit` as `Effect`, the same user role as `User role` and as `Action Model` the action that has `exface.core.ShowDialog` as prototype and that users with that role should be allowed to see.

With those two policies in effect users with that role will now not be allowed to access dialogs except the one defined in the second policy.