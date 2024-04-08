Relay-API Bundle README Template
================================

# DbpRelayAuthorizationBundle

[GitHub](https://github.com/digital-blueprint/relay-authorization-bundle) |
[Packagist](https://packagist.org/packages/dbp/relay-authorization-bundle) |
[Frontend Application](https:/github.com/{{app-path}}) |
[Authorization Website](https://mywebsite.org/site/software/authorization.html)

The authorization bundle provides an API for managing group and user authorization attributes.

## Bundle installation

You can install the bundle directly from [packagist.org](https://packagist.org/packages/dbp/relay-authorization-bundle).

```bash
composer require dbp/relay-authorization-bundle
```

## Integration into the Relay API Server

* Add the bundle to your `config/bundles.php` before `DbpRelayCoreBundle`:

```php
...
Dbp\Relay\AuthorizationBundle\DbpRelayAuthorizationBundle::class => ['all' => true],
Dbp\Relay\CoreBundle\DbpRelayCoreBundle::class => ['all' => true],
];
```

If you were using the [DBP API Server Template](https://packagist.org/packages/dbp/relay-server-template)
as template for your Symfony application, then this should have already been generated for you.

* Run `composer install` to clear caches

## Configuration

For this create `config/packages/dbp_relay_authorization.yaml` in the app with the following
content:

```yaml
dbp_relay_authorization:
```

If you were using the [DBP API Server Template](https://packagist.org/packages/dbp/relay-server-template)
as template for your Symfony application, then the configuration file should have already been generated for you.

For more info on bundle configuration see <https://symfony.com/doc/current/bundles/configuration.html>.

## Development & Testing

* Install dependencies: `composer install`
* Run tests: `composer test`
* Run linters: `composer run lint`
* Run cs-fixer: `composer run cs-fix`

## Bundle dependencies

Don't forget you need to pull down your dependencies in your main application if you are installing packages in a bundle.

```bash
# updates and installs dependencies of dbp/relay-authorization-bundle
composer update dbp/relay-authorization-bundle
```

## Scripts

### Database migration

Run this script to migrate the database. Run this script after installation of the bundle and
after every update to adapt the database to the new source code.

```bash
php bin/console doctrine:migrations:migrate --em=dbp_relay_authorization_bundle
```

## Error codes

### `/authorization/groups`

#### POST

| relay:errorId                       | Status code | Description                                     | relay:errorDetails | Example                          |
|-------------------------------------|-------------|-------------------------------------------------| ------------------ |----------------------------------|


### `/authorization/groups/{identifier}`

#### GET

| relay:errorId                    | Status code | Description               | relay:errorDetails | Example |
| -------------------------------- | ----------- | ------------------------- | ------------------ | ------- |


## Roles


## Events



