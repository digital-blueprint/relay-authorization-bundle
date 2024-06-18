# PHP Backend API

Resources registered for access control are uniquely identified by 
* `resourceClass` the fully qualified classname of the resource
* `resourceIdentifier` the resource identifier uniquely identifying the resource within `resourceClass`

**_Resource action grants_** define an **action** the grant **holder** is authorized to perform on a **resource**
(identified by `resourceClass` and `resourceIdentifier`). The available set of `actions` can be freely defined
by the application, except for the **manage** action, which is predefined.

### Method `registerResource`

Registers a new resource for access control, usually on resource creation. The user logged-in during the 
registration is automatically issued a **mange** grant, which entitles to issue additional grants for that resource.

### Method `deregisterResource`

De-registers a resource from access control, usually on resource deletion, and deletes all grants for the resource.

### Method `deregisterResources`

De-registers multiple resources from access control, usually on resource deletion, and deletes all grants for the resources.

### Method `getGrantedItemActionsForCurrentUser`

Gets the item actions that the current user is authorized to perform on the given resource item, e.g.:
```php
['read']
```

### Method `getGrantedItemActionsPageForCurrentUser`

Gets a page (=subset) of all resource item actions that the current user is authorized to perform and 
that contain at least one of the given item actions.

The result is a associative array mapping the resource identifiers to the granted actions:
```php
[
   '01902a56-4cc7-71ba-aa71-72a27f1ba9b6' => ['manage', 'read'],
   '01902a56-b7f2-78f0-a46a-a886b02291a2' => ['write'],
]
```

### Method `getGrantedCollectionActionsForCurrentUser`

Gets the collection actions that the current user is authorized to perform on the given resource collection, e.g.:
```php
['manage', 'create']
```
