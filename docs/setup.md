# Setup

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

## Database migration

Run this script after installation and every update of the bundle in order to create or update the database tables:

```bash
php bin/console doctrine:migrations:migrate --em=dbp_relay_authorization_bundle
```
