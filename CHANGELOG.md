# Changelog

## Unreleased

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