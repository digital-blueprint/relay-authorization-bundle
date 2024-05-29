# PHP Backend API

Resources registered for access control are uniquely identified by 
* `resourceClass` the fully qualified classname of the resource
* `resourceIdentifier` the resource identifier uniquely identifying the resource within `resourceClass`

**_Resource action grants_** define an **action** the grant **holder** is authorized to perform on a **resource**
(identified by `resourceClass` and `resourceIdentifier`). The available set of `actions` can be freely defined
by the application, except for the **manage** action, which is predefined.

### `addResource(string $resourceClass, string $resourceIdentifier): void`

Registers a new resource for access control, usually on resource creation. The user logged-in during the 
registration is automatically issued a **mange** grant, which entitles to issue additional grants for that resource.

### `removeResource(string $resourceClass, string $resourceIdentifier): void`

De-registers a resource from access control, usually on resource deletion, and deletes all grants for the resource.

### `removeResources(string $resourceClass, array $resourceIdentifiers): void`

De-registers multiple resources from access control, usually on resource deletion, and deletes all grants for the resources.

### `getGrantedResourceItemActions(string $resourceClass, ?string $resourceIdentifier = null, ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array`

Get grants of the logged-in user to perform any of the given actions on the given resource item.
* `resourceIdentifier` null matches any resource item of the given resource class
* `actions` null matches any action

### `getGrantedResourceCollectionActions(string $resourceClass, ?array $actions = null, int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_DEFAULT): array`

Get grants of the logged-in user to perform any of the given actions on the given resource collection
(e.g. create new resource instances).
* `actions` null matches any action