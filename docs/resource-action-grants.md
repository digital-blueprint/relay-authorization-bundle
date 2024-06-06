# Resource Action Grants

Represent an access grant that entitles the [Grant Holder](#grant-holder)
to perform an [Action](#action) on a [Resource](#resource).

## Grant Holder

Each grant has exactly one of the 3 possible grant holder types. See [Groups](groups.md).

## Action

Actions that can be performed on a resource depend on the resource class and are defined by the application. There are two kinds
of actions for a resource class:
* resource item actions
* resource collection actions

Like for REST APIs, 'create' (POST) new resources is usually a collection action, whereas 'read', 'update', and 'delete' 
are typical resource item actions.

However, the only action which is defined and understood by the DbpRelayAuthorizationBundle is 'manage'. It's automatically 
issued to the logged-in user when a new resource is registered for access control
(see [PHP API](php-api.md/#addresourcestring-resourceclass-string-resourceidentifier-void)) and entitles the holder to issue
further grants ('manage' or other) for the same resource.

## Resource

A resource can be any uniquely identifiable application object (document, post, form, ...) whose access is to be controlled.

The DbpRelayAuthorizationBundle distinguishes
* resource items (identified by a combination of the _resource class_ and a _resource identifier_)
* resource collections (identified by the _resource class_ and a `null` _resource identifier_)

The application defines separate sets of actions for resource items and resource collections
(see [Events](events.md/#getavailableresourceclassactionsevent) on how).

Resources can be added (registered) and removed (deregistered) using the [PHP API](php-api.md/#addresourcestring-resourceclass-string-resourceidentifier-void).
The resource creator automatically becomes the manager of the resource and may issue grants for that resource. On resource 
removal, all associated grants are removed as well.

Since resource collections (like for REST) are not created and registered over the API, there must be an alternative way
to (initially) grant resource collection actions to users (like for example to 'create' new resource items). This can be done by
configuring _manage resource collection policies_ in the bundle [Configuration](configuration.md/#resource_classes-optional).