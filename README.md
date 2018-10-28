Description
-----------

This module allows to create your own messages with tokens support for each state of the order.
To do this you need to go to the page - "admin/config/commerce_order_messages" and write the needed messages.

Usage
-----

At the moment, the module does not support any automation, so we can manage messages using Drupal Events or something similar.

Examples
--------

######Send SMS when order is done.

MODULE_NAME.services.yml:
```
services:
  MODULE_NAME.order_subscriber:
    class: Drupal\MODULE_NAME\EventSubscriber\OrderEventSubscriber
    arguments: ['@commerce_order_messages.messages_helper']
    tags:
      - { name: event_subscriber }
```

OrderEventSubscriber.php:
```php
namespace Drupal\MODULE_NAME\EventSubscriber;

use Drupal\smsc\Smsc\DrupalSmsc;
use Drupal\commerce_order_messages\OrderMessagesHelper;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * @var OrderMessagesHelper
   */
  protected $orderMessageHelper;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param OrderMessagesHelper $orderMessageHelper
   */
  public function __construct(OrderMessagesHelper $orderMessageHelper) {
    $this->orderMessageHelper = $orderMessageHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.done.post_transition' => ['orderDone', -100],
    ];

    return $events;
  }

  /**
   * Send sms message when an order is done.
   */
  public function orderDone(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    
    if ($message = $this->orderMessageHelper->getMessage($order)) {
      $phone = $order->getBillingProfile()->get('field_phone')->value;
      DrupalSmsc::sendSms($phone, $message);
    }
  }
}
```

######With 'commerce_log' module for logging changes with the custom message.

MODULE_NAME.commerce_log_categories.yml:
```
custom_log_messages:
  label: Custom log messages
  entity_type: commerce_order
```

MODULE_NAME.commerce_log_templates.yml
```
order_done:
  category: custom_log_messages
  label: 'Order done'
  template: "<p>{{ text }}</p>"
```

MODULE_NAME.services.yml:
```
services:
  MODULE_NAME.order_subscriber:
    class: Drupal\MODULE_NAME\EventSubscriber\OrderEventSubscriber
    arguments: ['@entity_type.manager', '@commerce_order_messages.messages_helper']
    tags:
      - { name: event_subscriber }

```

OrderEventSubscriber.php:
```php
namespace Drupal\MODULE_NAME\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order_messages\OrderMessagesHelper;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * @var OrderMessagesHelper
   */
  protected $orderMessageHelper;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param OrderMessagesHelper $orderMessageHelper
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    OrderMessagesHelper $orderMessageHelper
  ) {
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
    $this->orderMessageHelper = $orderMessageHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.done.post_transition' => ['orderDone', -100],
    ];

    return $events;
  }

  /**
   * Creates a log when an order is done.
   */
  public function orderDone(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    
    if ($message = $this->orderMessageHelper->getMessage($order)) {
      $this->logStorage
        ->generate($order, 'order_done', ['text' => $message])
        ->save();
    }
  }
}
```