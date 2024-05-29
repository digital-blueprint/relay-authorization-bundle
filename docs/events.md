# Events

### GetAvailableResourceClassActionsEvent

This event is callback used by the [Rest Web API](./rest-api.md) to get available actions for a resource class.

Your event subscriber receives an instance of `Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent` and 
can specify the set of available
* resource item actions (like 'get' (item), 'delete')
* resource collection actions (like 'create', 'get' (collection))

for your resource class(es).

For example:

```php
<?php

namespace Vendor\MyApp\EventSubscriber;

use Dbp\Relay\AuthorizationBundle\Event\GetAvailableResourceClassActionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetAvailableResourceClassActionsEventSubscriber implements EventSubscriberInterface
{
    private const RESOURCE_CLASS = 'VendorMyAppMyResource';
    
    public static function getSubscribedEvents(): array
    {
        return [
            GetAvailableResourceClassActionsEvent::class => 'onGetAvailableResourceClassActionsEvent',
        ];
    }

    public function onGetAvailableResourceClassActionsEvent(GetAvailableResourceClassActionsEvent $event)
    {
        switch ($event->getResourceClass()) {
            case self::RESOURCE_CLASS:
                $event->setItemActions(['read', 'update', 'delete']);
                $event->setCollectionActions(['create']);
                break;
            // case self::ANOTHER_RESOURCE_CLASS:
            //   ...
        }
    }
}
```

Be sure to only set the actions for your own resource classes, becuase your subscriber will
be called whenever available actions are requested.
