# Changelog

## Unreleased

* Assure that elements of ResourceAction arrays returned by the PHP API are unique

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