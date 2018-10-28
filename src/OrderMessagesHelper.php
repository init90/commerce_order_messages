<?php

namespace Drupal\commerce_order_messages;

class OrderMessagesHelper {

  /**
   * Get order message based on current order state.
   *
   * @return string|NULL
   */
  public function getMessage(\Drupal\commerce_order\Entity\OrderInterface $order) {
    $state_id = $order->getState()->getString();
    $workflow_id = $order::getWorkflowId($order);
    $config_name = "commerce_order_messages.{$workflow_id}.{$state_id}";
    $order_message = \Drupal::config($config_name)->get('message');

    if (!empty($order_message)) {
      $token_service = \Drupal::token();
      $token_context = [
        'commerce_order' => $order,
      ];

      $order_message = $token_service->replace($order_message, $token_context);
    }

    return $order_message;
  }
}
