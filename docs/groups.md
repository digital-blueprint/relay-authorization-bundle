# Groups

There are 3 types of [Resource Action Grant](./resource-action-grants.md) holders:

* [Users](#users)
* [Groups](#groups)
* [Dynamic Groups](#dynamic-groups)

## Users

API users are uniquely identified by their user identifier.

If a user holds a [Resource Action Grant](./resource-action-grants.md), they are authorized to perform
the respective action on the resource defined by the grant.

## User Groups

A user group represents a fixed set of API users with unique identifiers.

If a user group holds a [Resource Action Grant](./resource-action-grants.md), all members of that user group are authorized to perform
the respective action on the resource defined by the grant.

## Dynamic User Groups

_Resource action grants_ may not only be issued to single users and user groups, but also by so-called _dynamic user groups_.
As opposed to user groups, where the group members are statically defined by their user identifier, _dynamic user group_ membership 
is defined at runtime by the evaluation of [policies](https://handbook.digital-blueprint.org/frameworks/relay/admin/access_control/#access-control-policies).
This means that group membership may change, depending on the user attributes used in the policy expression.

If a _dynamic user group_ holds a [Resource Action Grant](./resource-action-grants.md), all users, for which the membership policy
evaluates to `true` are authorized to perform the respective action on the resource defined by the grant.

_Dynamic user groups_ can be defined in the bundle [configuration](configuration.md#dynamic_groups-optional).