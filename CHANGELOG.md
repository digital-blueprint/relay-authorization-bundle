# Changelog

## Unreleased

* extend group loop check to the whole lineage of a parent group candidate 

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