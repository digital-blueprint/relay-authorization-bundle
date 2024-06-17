# PHP Backend API

Resources registered for access control are uniquely identified by 
* `resourceClass` the fully qualified classname of the resource
* `resourceIdentifier` the resource identifier uniquely identifying the resource within `resourceClass`

**_Resource action grants_** define an **action** the grant **holder** is authorized to perform on a **resource**
(identified by `resourceClass` and `resourceIdentifier`). The available set of `actions` can be freely defined
by the application, except for the **manage** action, which is predefined.

### Class `ResourceAction`

References one resource item or the resource collection of a resource class and the subset of requested actions 
that the current user is authorized to perform on the resource (collection). 

### Method `registerResource`

Registers a new resource for access control, usually on resource creation. The user logged-in during the 
registration is automatically issued a **mange** grant, which entitles to issue additional grants for that resource.

### Method `deregisterResource`

De-registers a resource from access control, usually on resource deletion, and deletes all grants for the resource.

### Method `deregisterResources`

De-registers multiple resources from access control, usually on resource deletion, and deletes all grants for the resources.

### Method `getGrantedResourceItemAction`

Gets a `ResourceAction` object with the subset of requested `actions` that the logged-in user is authorized to perform on the given resource item
or `null` if there are none.

### `getGrantedResourceItemActionsPage(string $resourceClass, ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array`

Gets a page `ResourceAction` objects with the subset of requested `actions` that the logged-in user is authorized to perform on the respective resource items.

### Method `getGrantedResourceCollectionActions'

Gets a `ResourceAction` object with the subset of requested `actions` that the logged-in user is authorized to perform on the given resource collection
or `null` if there are none.