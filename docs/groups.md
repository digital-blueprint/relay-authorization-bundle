# Groups

There are 3 types of [Resource Action Grant](./resource-action-grants) holders:
* [Users](#users)
* [Groups](#groups)
* [Dynamic Groups](#dynamic-groups)

## Users

API users are uniquely identified by their user identifier.

If a user holds a [Resource Action Grant](./resource-action-grants), they are authorized to perform
the respective action on the resource defined by the grant.

## Groups

A (static) group represents a fixed set of API users. A member of a group can be either a
* user with a unique identifiers or
* another group

i.e. groups can be defined recursively. Note that the validity of a group lineage is checked 
when a new group member is being added to a group, in order to avoid endless recursions.

If a group holds a [Resource Action Grant](./resource-action-grants), all members of that group are authorized to perform
the respective action on the resource defined by the grant.

## Dynamic Groups

_Resource action grants_ may not only be issued to single users and (static) groups, but also by so-called _dynamic groups_.
As opposed to static groups, where the group members are defined by their user identifier, _dynamic group_ membership is defined by the
evaluation of [policies](https://handbook.digital-blueprint.org/frameworks/relay/admin/access_control/#access-control-policies).
This means that group membership may change, depending on the user attributes used in the policy expression.

If a _dynamic group_ holds a [Resource Action Grant](./resource-action-grants), all users, for which the membership policy
evaluates to `true` are authorized to perform the respective action on the resource defined by the grant.

_Dynamic groups_ can be defined in the bundle [configuration](configuration.md/#dynamic_groups-optional).