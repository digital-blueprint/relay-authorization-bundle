# REST Web API

For details on parameters and schemas, see the OpenAPI documentation of all available operations under the **Authorization** tag.

## `POST /authorization/groups`

Creates a new group.

| relay:errorId                       | Status code | Description                     | relay:errorDetails    | Example                          |
|-------------------------------------|-------------|---------------------------------|-----------------------|----------------------------------|
| `authorization:adding-group-failed` | 500         | The group could not be created. | `message`             | `['message' => 'Error message']` |
| `authorization:group-invalid`       | 400         | The group is invalid.           | `<invalid attribute>` | `['name']                        |

### `GET /authorization/groups`

Gets one page of groups the logged-in user is authorized to read.

| relay:errorId                                   | Status code | Description                        | relay:errorDetails | Example                          |
|-------------------------------------------------|-------------|------------------------------------|--------------------|----------------------------------|
| `authorization:getting-group-collection-failed` | 500         | The groups could not be retrieved. | `message`          | `['message' => 'Error message']` |

### `GET /authorization/groups/{identifier}`

Gets the group with the given identifier.

| relay:errorId                             | Status code | Description                       | relay:errorDetails | Example                          |
|-------------------------------------------|-------------|-----------------------------------|--------------------|----------------------------------|
| `authorization:getting-group-item-failed` | 500         | The group could not be retrieved. | `message`          | `['message' => 'Error message']` |

### `DELETE /authorization/groups/{identifier}`

Deletes the group with the given identifier.

| relay:errorId                         | Status code | Description                     | relay:errorDetails | Example                          |
|---------------------------------------|-------------|---------------------------------|--------------------|----------------------------------|
| `authorization:removing-group-failed` | 500         | The group could not be removed. | `message`          | `['message' => 'Error message']` |


### `POST /authorization/groups-members`

Adds a new group member to a group.

| relay:errorId                              | Status code | Description                          | relay:errorDetails    | Example                          |
|--------------------------------------------|-------------|--------------------------------------|-----------------------|----------------------------------|
| `authorization:adding-group-member-failed` | 500         | The group member could not be added. | `message`             | `['message' => 'Error message']` |
| `authorization:group-member-invalid`       | 400         | The group member is invalid.         | `<invalid attribute>` | `['name']                        |

### `GET /authorization/groups-members`

Gets one page of group members for a given group.

| relay:errorId                                    | Status code | Description                                | relay:errorDetails | Example                          |
|--------------------------------------------------|-------------|--------------------------------------------|--------------------|----------------------------------|
| `authorization:getting-group-member-item-failed` | 500         | The group members could not be retrieved.  | `message`          | `['message' => 'Error message']` |
| `authorization:required-parameter-missing`       | 400         | A required parameter is missing.           | `groupIdentifier`  | `['groupIdentifier']`            |
| `authorization:group-not-found`                  | 404         | Group with given identifier was not found. | `groupIdentifier`  | `['groupIdentifier']`            |

### `GET /authorization/groups-members/{identifier}`

Gets the group member with the given identifier.

| relay:errorId                                          | Status code | Description                                  | relay:errorDetails | Example                          |
|--------------------------------------------------------|-------------|----------------------------------------------| ------------------ |----------------------------------|
| `authorization:getting-group-member-collection-failed` | 500         | The group member could not be retrieved. | `message`          | `['message' => 'Error message']` |

### `DELETE /authorization/groups-members/{identifier}`

Deletes the group member with the given identifier.

| relay:errorId                                | Status code | Description                            | relay:errorDetails    | Example                          |
|----------------------------------------------|-------------|----------------------------------------|-----------------------|----------------------------------|
| `authorization:removing-group-member-failed` | 500         | The group member could not be removed. | `message`             | `['message' => 'Error message']` |

### `GET /authorization/resources`

Gets one page of the resources, the logged-in user is authorized to read.

| relay:errorId                                      | Status code | Description                           | relay:errorDetails | Example                          |
|----------------------------------------------------|-------------|---------------------------------------|--------------------|----------------------------------|
| `authorization:getting-resource-collection-failed` | 500         | The resources could not be retrieved. | `message`          | `['message' => 'Error message']` |


### `GET /authorization/resources/{identifier}`

Gets the resource with the given identifier.

| relay:errorId                                | Status code | Description                          | relay:errorDetails    | Example                          |
|----------------------------------------------|-------------|--------------------------------------|-----------------------|----------------------------------|
| `authorization:getting-resource-item-failed` | 500         | The resource could not be retrieved. | `message`             | `['message' => 'Error message']` |


### `POST /authorization/resource-action-grants`

Creates a new grant to perform a given action on the given resource:

| relay:errorId                                                  | Status code | Description                                                             | relay:errorDetails | Example                          |
|----------------------------------------------------------------|-------------|-------------------------------------------------------------------------|--------------------|----------------------------------|
| `authorization:adding-resource-action-grant-failed`            | 500         | The grant could not be added.                                           | `message`          | `['message' => 'Error message']` |
| `authorization:resource-action-grant-invalid-action-missing`   | 400         | The grant is invalid: action is missing.                                |                    | `['action']`                     |
| `authorization:resource-action-grant-invalid-action-undefined` | 400         | The grant is invalid: action is undefined for the given resource class. |                    | `['action']`                     |

### `GET /authorization/resource-action-grants`

Gets one page of the grants, the logged-in user is authorized to read.

| relay:errorId                                                   | Status code | Description                        | relay:errorDetails | Example                          |
|-----------------------------------------------------------------|-------------|------------------------------------|--------------------|----------------------------------|
| `authorization:getting-resource-action-grant-collection-failed` | 500         | The grants could not be retrieved. | `message`          | `['message' => 'Error message']` |

### `GET /authorization/resource-action-grants/{identifier}`

Gets the grant with the given identifier.

| relay:errorId                                             | Status code | Description                       | relay:errorDetails | Example                          |
|-----------------------------------------------------------|-------------|-----------------------------------| ------------------ |----------------------------------|
| `authorization:getting-resource-action-grant-item-failed` | 500         | The grant could not be retrieved. | `message`          | `['message' => 'Error message']` |

### `DELETE /authorization/resource-action-grants/{identifier}`

Deletes the grant with the given identifier.

| relay:errorId                                         | Status code | Description                     | relay:errorDetails    | Example                          |
|-------------------------------------------------------|-------------|---------------------------------|-----------------------|----------------------------------|
| `authorization:removing-resource-action-grant-failed` | 500         | The grant could not be removed. | `message`             | `['message' => 'Error message']` |

### `GET /authorization/available-resource-class-actions`

Get the list available item and collection operations for the given resource class.

| relay:errorId                              | Status code | Description                      | relay:errorDetails | Example             |
|--------------------------------------------|-------------|----------------------------------|--------------------|---------------------|
| `authorization:required-parameter-missing` | 400         | A required parameter is missing. |                    | `['resourceClass']` |