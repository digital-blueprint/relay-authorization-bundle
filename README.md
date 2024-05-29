# DbpRelayAuthorizationBundle

[GitHub](https://github.com/digital-blueprint/relay-authorization-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-authorization-bundle) |
[Frontend Application](https:/github.com/{{app-path}}) |
[Authorization Website](https://mywebsite.org/site/software/authorization.html)

The DbpRelayAuthorizationBundle is a pluggable PHP 8.1+/Symfony authorization module which allows you to create
and manage user groups as well as user/group access rights (_grants_) to arbitrary resources,
where group and grant data is stored in a database. 

It integrates seamlessly with the [Relay API Server]([DBP API Server Template](https://packagist.org/packages/dbp/relay-server-template)).

Please see the [documentation](./docs) for more information.

## Bundle Installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-authorization-bundle).

```bash
composer require dbp/relay-authorization-bundle
```

To update the bundle and its dependencies:
```bash
composer update dbp/relay-authorization-bundle
```

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer lint`
