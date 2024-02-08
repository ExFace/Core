# Data authorization point

This authorization point allows to restrict access to specific CRUD (create, read, update, delete) operations or limit them to a subset of the data by adding mandatory filters.

By default all data visible on a page is accessible to all users, that have permissions for this page.

## How to restrict access to a subset of data

Add filters to a permitting policy to apply the foricbly, thus allowing a user to see only data matching those filters. If a user has multiple filtering policies, the superset of the data will be accessible - that is, the filter will be combined with an `OR`. 

### Allow a user to view own documents only

A typical restriction is to allow users to work with data they (or their company) has created but no with that of user authors. Lets assume, we have an object `my.App.DOCUMENT` with an attribute `CREATED_BY` containing the username of the author.

We can now create a user role `my.App.DOC_EDITORS` and give it the following permission to restrict access to a users own documents only:

- Authoriziation point: `Access to data`
- Effect: `Permit`
- User role: `my.App.DOC_EDITORS`
- Meta object: `my.App.DOCUMENT`
- Additional conditions:

```
{
  "add_filters": {
    "operator": "AND",
    "conditions": [
      {
        "expression": "CREATED_BY_USER",
        "comparator": "==",
        "value": "=User('USERNAME')"
      }
    ]
  }
}

```

The `add_filters` condition of the policy will now ensure all CRUD operations will contain a filter of the username of the current user.

### Allow access to different objects, that belong to a company

It is possible to restrict access to multiple objects using a single policy. This is very handy if you need users to see only data associated with their company, project or similar. 

1. Create the policy for the meta object that represents the company or project, or whatever is the main object. 
2. Use `add_filters` to restrict access to this main object
3. Use `apply_to_related_objects` to apply this restriction also to any other ojects, that have a relation to the main object.

In the following example, we are going to let users only see data for the companies 1002 and 1003. The filter itself restricts access to the `my.App.COMPANY` object. This restriction is then applied to related objects like `ORDER` and `PRODUCT`.

```
{
  "add_filters": {
    "operator": "AND",
    "conditions": [
      {
        "expression": "COMPANY_NO",
        "comparator": "[",
        "value": "1002,1003"
      }
    ]
  },
  "apply_to_related_objects": [
    {
      "related_object": "my.App.ORDER",
      "relation_path_from_policy_object": "SUPPLIER_COMPANY"
    },
    {
      "related_object": "my.App.PRODUCT",
      "relation_path_from_policy_object": "SUPPLIER__COMPANY"
    }
  ]
}
```

### Multi-dimension restrictions: e.g. permissions for companies and countries

If you have roles limiting the view of a user, that are based on different objects, multiple data policies will be applied at the same time. In this case, policies are grouped by filtering scopes. Filters applied within a scope are combined by OR and the scopes themselves are combined via AND. By default, the scopes are defined by the target object of a policy.
     
For example, if there are policies, that allow a user to only see a certain company, and policies, limiting the view to a country, a user that has roles for Company1 and Company2 in Germany will receive the following filter: `(Company = "Company1" OR Company = "Company2") AND Country = "Germany"`.

If you need the filters from multiple policies with different target objects to be combined via OR, set the same explicitly defined scope in all policies using `add_filters_in_scope`.

### Let a user see everything explicitly

If you have a lot of policies, it can be hard to ensure, that key users really see everything. You can tell a policy to provide unfiltered access explicitly by specifying `add_filters` without conditions! Of course, these policies can also apply to related objects as described above.

```
{
  "add_filters": {
    "operator": "AND"
  }
}
```