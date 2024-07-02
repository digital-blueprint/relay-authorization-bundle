# Changelog

## Unreleased

## v0.3.4

* Automatically add/update/delete authorization resources and grants in the DB for the manage resource collection policies 
configured in the bundle config after app cache clear. 

## v0.3.3

* Disallowed child groups now include groups that are already a member of a group 

## v0.3.2

* Add query parameter 'getChildGroupCandidatesForGroupIdentifier' to GET group collection endpoint

## v0.3.1

* Replace the authorization_group_members.child_group_index unique constraint by a regular index

## v0.3.0

* Re-design PHP API
* PHP API: Return all resource actions of the user for a returned resource (instead of only the requested once). The actions parameter now works as 
filter for returned resources: Only return actions for resources where the actions contain at least one of the given actions.

## v0.2.2

* REST API: Add search query parameter to GET Group collection endpoint

## v0.2.1

* PHP API: Provide both getGrantedResourceItemActions (returning ?ResourceAction) and getGrantedResourceItemActionsPage (returning array) methods

## v0.2.0

* PHP API: rename to (de)registerResource(s)
* PHP API: only return one ResourceAction per resource (item or collection) with the set of actions 
the current user is authorized to perform on the resource item or collection 

## v0.1.16

* Assure that elements of ResourceAction arrays returned by the PHP API are unique
* REST API: add endpoints to get dynamic group item/collection
* REST API: add collection endpoint for available resource class actions returning available
item and collection actions for the resource classes the current user is authorized to see

## v0.1.15

* Introduce authorization for GET Group collection operation (only return groups the current user is authorized to read)
* Validate action attribute of posted resource action grants (only accept available actions, i.e. actions returned by a
get-available-actions subscriber for the respective resource class)
* Add documentation
* REST API: Restrict authorization resource and resource action grant collections to entities the user is authorized to read

## v0.1.14

* Add support for system account clients which may be holders of grants with a dynamic group set 

## v0.1.13

* switch from 'currentPageNumber' to 'firstResultIndex' for flexibility

## v0.1.12

* extend group loop check to the whole lineage of a parent group candidate
* add operation that returns all available item and collection actions for a given resource class

## v0.1.11

* enable ON CASCADE DELETE for foreign key associations
* add ResourceActionGrant::removeResources to remove multiple resources at once
* enable group loop check for new group members
* add unit tests

## v0.1.10

* enhance pagination support
* add unit tests for pagination
* add method hasUserGrantedResourceItemActions to ResourceActionGrantService for authentication time, where the 
current user session is not yet available

## v0.1.9

* fix pagination in authorization service
* extended public resource action grant API by has... functions
* add unit tests for public resource action grant API

## v0.1.8

* Doctrine: Replace anntoations by PHP attributes
* Enhance test utilities for other bundles