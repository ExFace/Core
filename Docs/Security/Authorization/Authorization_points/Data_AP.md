# Data authorization point

This authorization point allows to restrict access to specific CRUD (create, read, update, delete) operations or limit them to a subset of the data by adding mandatory filters.

By default all data visible on a page is accessible to all users, that have permissions for this page.

## How to restrict access to a subset of data

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

