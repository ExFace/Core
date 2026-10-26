# Authorization and permissions

## Introduction

Once the user is [authenticated](../Authentication/index.md) (i.e. we know, which user it is), we can define, what this user is authorized (= allowed) to do. This is done by assigning roles to the user and creating policies to allow or deny access in authorization points (APs).

The whole system follows ABAC principles (Attribute Based Access Control) and aligns with the mighty XACML standard.

- **Authorization points** are gateways in the code. They check if a user is authorized to access something (a page, an action, some data) by evaluating all policies available for them. Every app can add its own APs.
- **Policies** are rules, that can allow or deny access based on a set of attributes of the subject (typically the user with his roles, employment, etc), the object (e.g. an action) and the current context (time, configuration, etc.).
- **Decisions** are what policies produce when evaluated - `permit`, `deny`, `not applicable` or `indeterminate`.
- **Combining algorithms** are ways to combine the decisions of individual policies to a final decision - `permit` or `deny`. Each AP can be configured to use a specific algorithm - e.g. `permit-unless-deny`.

This system is extremely flexible and allows us to control access to virtually anything! However, it is the app designers responsibility to configure the authorization engine correctly. Misconfiguration can also lead to security breaches.

## Combination of authorization points

A user's request must pass all authorization points to be successful. A prominent example is the "partnership" between the `UiPageAuthorizationPoint` and the `ActionAuthorization` point: all actions of a page are by default accessible to anyone, who has access to the page. This is why the action AP is permissive by default - the page AP takes care of first-level authorization. Only actions that need special permissions will actually have action AP policies.

This principle helps decrease the number of policies. After all, we would have to add at least one policy for every single action model, if it was not for the page AP.

## Core authorization points

- [Facade authorization points](Authorization_points/Facade_AP.md) to restrict access to facades
- [Page authorization point](Authorization_points/Page_AP.md) to control access to UI pages and navigation items
- [Action authorization point](Authorization_points/Action_AP.md) to restrict access to certain actions
- [Data authorization point](Authorization_points/Data_AP.md) to restrict access to CRUD operations on metamodel objects
- [Context authorization point](Authorization_points/Conext_AP.md) to control access to contexts

## Policies

Policies are basically voters. Each can vote permit, deny, unapplicable or indeterminate (error). Policies match their rules against subject, object and context to determine, how they vote. The combination of votes leads to a decision in the AP.

Each authorization point has its own policy prototype, that allows to configure various conditions. Most of them will define, when the policy will be applicable. For example an action policy with perfit effect may be applicable for a certain user role and action alias, but only for a specific object or if the action input data matches a filter.
### Policy targets

Dispite having very different configurations, all policies have a common set of targets - especially important attributes, that are used for filtering policies in the administration UIs. Each AP has allows one or more of these targets:

- User role
- Metaobject
- UI page
- Facade
- Action (prototype or model)
- App (= all pages, actions or objects of the app)

## Common use cases

### Action access

If you need to make certain action to be accessible only to part of the users the see their buttons - use a global deny policy for that action and individual permit policies for every user role, that should have access to it. You can also deny all actions of an entire app to avoid individual policies.

## Extending the authorization by adding new APs

TODO