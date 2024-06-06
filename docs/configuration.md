# Configuration

For this create `config/packages/dbp_relay_authorization.yaml` in the app.

If you were using the [DBP API Server Template](https://gitlab.tugraz.at/dbp/relay/dbp-relay-server-template)
as template for your Symfony application, then the configuration file should have already been generated for you.

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

Here is the content of an exmple config file:

```yaml
    dbp_relay_authorization:
      database_url: 'mysql://%env(AUTHORIZATION_DATABASE_USER)%:%env(AUTHORIZATION_DATABASE_PASSWORD)%@%env(AUTHORIZATION_DATABASE_HOST)%:%env(AUTHORIZATION_DATABASE_PORT)%/%env(AUTHORIZATION_DATABASE_DBNAME)%?serverVersion=mariadb-10.3.30'
      create_groups_policy: 'user.get("ROLE_ADMIN")'
      resource_classes:
        - identifier: VendorMyAppMyResource
          manage_resource_collection_policy: 'user.get("ROLE_GROUP_CREATOR")'
      dynamic_groups:
          -  # user whose user attribute 'ROLE_ADMIN' evaluates to true are member of 'admins'
          - identifier: resourceFooWriters
            is_user_group_member: 'user.get("ROLE_ADMIN") || user.get("ROLE_WRITER")'
```

### database_url (required)

The bundle has one required setting `database_url` that you can specify in your
app, either by hardcoding it, or by referencing environment variables.

```yaml
  database_url: 'mysql://db:secret@mariadb:3306/db?serverVersion=mariadb-10.3.30'
  # database_url: 'mysql://%env(AUTHORIZATION_DATABASE_USER)%:%env(AUTHORIZATION_DATABASE_PASSWORD)%@%env(AUTHORIZATION_DATABASE_HOST)%:%env(AUTHORIZATION_DATABASE_PORT)%/%env(AUTHORIZATION_DATABASE_DBNAME)%?serverVersion=mariadb-10.3.30'
```

### create_groups_policy (optional)

To define who is initially allowed to create new groups you need to define the `create_groups_policy`, which is condition
in the form of a Symfony expression. Read the chapter on 
[Access Control Policies](https://handbook.digital-blueprint.org/frameworks/relay/admin/access_control/#access-control-policies) 
to learn how to write policies.

### resource_classes (optional)

Like for groups, you can define policies on who is initially allowed to _manage_ a resource collection. This entails the rights to
* create new resource instances (i.e. POST to resource collection) 
* issue resource collection grants to other users/groups

Read the chapter on
[Access Control Policies](https://handbook.digital-blueprint.org/frameworks/relay/admin/access_control/#access-control-policies)
to learn how to write policies.

* `identifier` is the fully qualified resource class name you are using to register and query a resource
* `manage_resource_collection_policy` is the condition which the logged-in user must fulfill in order to have 'manage' 
permissions on the resource collection

### dynamic_groups (optional)

See [Dynamic Groups](./groups.md/#dynamic-groups) and 
[Access Control Policies](https://handbook.digital-blueprint.org/frameworks/relay/admin/access_control/#access-control-policies) for 
information on how to write policies.

* `identifier` is the fully qualified resource class name you are using to register and query a resource
* `is_user_group_member` is the condition which the logged-in user must fulfill in order to be member of the dynamic group
