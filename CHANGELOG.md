# Changelog

## Unreleased

- Add attribute `grantedActions` to resource action grants, which indicates, whether the grant 
can be deleted by the current user (contains `"delete"` if so)

## v0.5.5

- Require at least one action to be defined for a resource class for it to be 'available'
- Require the available actions for a resource class to be stored in the database instead of requesting them from subscribers
on each request (using, e.g., `ResourceActionGrantService::setAvailableResourceClassActions`).
- Only return grants for actions that are actually available for the grant's resource class.
- Do not expose `AuthorizationResource` anymore via REST API (always identify by resource class and identifier)
- Do not explicitly register resources anymore. Create `AuthorizationResource` implicitly when adding a resource action grant
for a resource that does not yet exist.
- Allow adding and removing resources to/from group resources, which are normal authorization resources, i.e., there can be
grants for them. If a grant is valid for a group resource, it is implicitly valid for all resources that are members of the group resource.
- Update core bundle
- Implement `ResetInterface::reset()` where possible for automatic cleanup of request state

## v0.5.4

- Add `removeResourceActionGrants` to PHP API
- Make creation of manager grant disable-able for `registerResource` PHP API methods

## v0.5.3

- Add test utility function

## v0.5.2

- Replace api-resource `AuthorizationLocalizedAction` by a OpenAPI documented array object of localized action names, e.g.:
  ```json
  {
      "itemActions": {
        "edit": {
          "en": "Edit",
          "de": "Editieren"
        }
      },
      "collectionActions": {
        "create": {
          "en": "Create",
          "de": "Erstellen"
        }
      }
  }
  ```

## v0.5.1

- Temporarily disable `AuthorizationResource`, and `GrantedAction` operations.

## v0.5.0

- Add localized action names to `AuthorizationAvailableResourceClassActions`. Subscribers of the
`GetAvailableResourceClassActionsEventSubscriber` should now return localized names for available actions of their resource classes.

## v0.4.1

- Add event `ResourceActionGrantAddedEvent` triggered when a new resource action grant is added
- TestResourceActionGrantServiceFactory: Allow passing an event dispatcher instead of the subscribers

## v0.4.0

- Remove `whereContainsAnyOfActions` from API and replace by a single action to check if granted
- For is-granted checks: implicitly evaluate to true if an available action (like `read`) is requested and the user has 
a manage-grant

## v0.3.21

- add an implementation of the Dbp\Relay\CoreBundle\User\UserAttributeProviderInterface, which allows querying the 
current user's resource action grants using user attributes

## v0.3.20

- declare custom DQL string functions (e.g., UNHEX) in doctrine config, instead of addCustomStringFunctions which proved 
to not work reliably when multiple entity managers are defined.

## v0.3.19

- fix dynamic groups the current user is a member of

## v0.3.18

- fix GroupMemberProcessor

## v0.3.17

- replace api-platform QueryParameter by open-api Parameter

## v0.3.16

- add support for api-platform 4.1

## v0.3.14

- extend ResourceActionGrantService by addResourceActionGrant method and registerResource method by optional userIdentifier parameter

## v0.3.13

- Update TestEntityManager interface

## v0.3.12

- Add built-in dynamic group 'everybody' which grants access to all (authenticated) users

## v0.3.11

- On adding new resource action grants, allow the authorization resource to be specified by 
resource class and identifier (for cases where the authorization resource identifier is not known)
- Add /authorization/granted-actions/{resource class}:{resource identifier} endpoint, which returns the list of
actions the current user is granted to perform on the given authorization resource (specified by resource class and identifier)
- Only return available resource class items if the current user has read permissions to at least one resource of the 
requested resource class (otherwise returning 404), to be in line with the collection endpoint
- Drop support for api-platform 2.7
- Add support for newer doctrine versions

## v0.3.10

- Fix DB exception on container load without DB available

## v0.3.9

- Update core and adapt

## v0.3.8

- Update core bundle
- Add API tests
- Enhance test utilities
- Add group patch operation

## v0.3.7

- Add property "writable" to AuthorizationResource entity. True if the requesting user is authorized to add/delete grants 
to/from this authorization resource

## v0.3.5

- Extend/unify test utilities

## v0.3.4

- Automatically add/update/delete authorization resources and grants in the DB for the manage resource collection policies 
configured in the bundle config after app cache clear. 

## v0.3.3

- Disallowed child groups now include groups that are already a member of a group 

## v0.3.2

- Add query parameter 'getChildGroupCandidatesForGroupIdentifier' to GET group collection endpoint

## v0.3.1

- Replace the authorization_group_members.child_group_index unique constraint by a regular index

## v0.3.0

- Re-design PHP API
- PHP API: Return all resource actions of the user for a returned resource (instead of only the requested once). The actions parameter now works as 
filter for returned resources: Only return actions for resources where the actions contain at least one of the given actions.

## v0.2.2

- REST API: Add search query parameter to GET Group collection endpoint

## v0.2.1

- PHP API: Provide both getGrantedResourceItemActions (returning ?ResourceAction) and getGrantedResourceItemActionsPage (returning array) methods

## v0.2.0

- PHP API: rename to (de)registerResource(s)
- PHP API: only return one ResourceAction per resource (item or collection) with the set of actions 
the current user is authorized to perform on the resource item or collection 

## v0.1.16

- Assure that elements of ResourceAction arrays returned by the PHP API are unique
- REST API: add endpoints to get dynamic group item/collection
- REST API: add collection endpoint for available resource class actions returning available
item and collection actions for the resource classes the current user is authorized to see

## v0.1.15

- Introduce authorization for GET Group collection operation (only return groups the current user is authorized to read)
- Validate action attribute of posted resource action grants (only accept available actions, i.e. actions returned by a
get-available-actions subscriber for the respective resource class)
- Add documentation
- REST API: Restrict authorization resource and resource action grant collections to entities the user is authorized to read

## v0.1.14

- Add support for system account clients which may be holders of grants with a dynamic group set 

## v0.1.13

- switch from 'currentPageNumber' to 'firstResultIndex' for flexibility

## v0.1.12

- extend group loop check to the whole lineage of a parent group candidate
- add operation that returns all available item and collection actions for a given resource class

## v0.1.11

- enable ON CASCADE DELETE for foreign key associations
- add ResourceActionGrant::removeResources to remove multiple resources at once
- enable group loop check for new group members
- add unit tests

## v0.1.10

- enhance pagination support
- add unit tests for pagination
- add method hasUserGrantedResourceItemActions to ResourceActionGrantService for authentication time, where the 
current user session is not yet available

## v0.1.9

- fix pagination in authorization service
- extended public resource action grant API by has... functions
- add unit tests for public resource action grant API

## v0.1.8

- Doctrine: Replace anntoations by PHP attributes
- Enhance test utilities for other bundles