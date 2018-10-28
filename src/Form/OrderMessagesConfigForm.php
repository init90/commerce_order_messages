<?php

namespace Drupal\commerce_order_messages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine\WorkflowManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OrderMessagesConfigForm.
 */
class OrderMessagesConfigForm extends ConfigFormBase {

  /**
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var WorkflowManager
   */
  protected $workflowManager;

  /**
   * Constructs a new OrderMessagesConfigForm.
   *
   * @param ConfigFactoryInterface $config_factory
   * @param EntityTypeManager $entityTypeManager
   * @param WorkflowManager $workflowManager
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManager $entityTypeManager,
    WorkflowManager $workflowManager
  ) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->workflowManager = $workflowManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.workflow')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_order_messages.config_form';
  }

  /**
   * Return list with active order workflows.
   *
   * @return array
   */
  protected function getActiveOrderWorkflows() {
    $active_workflows = [];
    $order_types = $this->entityTypeManager
      ->getStorage('commerce_order_type')
      ->loadMultiple();

    foreach ($order_types as $order_type) {
      $workflow_label = $this->workflowManager->getDefinition($order_type->getWorkflowId())['label'];
      $active_workflows[$order_type->getWorkflowId()] = $workflow_label;
    }

    return $active_workflows;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$workflows = $this->getActiveOrderWorkflows()) {
      return $form;
    }

    $input = $form_state->getUserInput();
    $selected_workflow = isset($input['order_workflow']) ? $input['order_workflow'] : key($workflows);
    $workflow_data = $this->workflowManager->getDefinition($selected_workflow);

    if (count($workflows) > 1) {
      $form['order_workflow'] = [
        '#type' => 'select',
        '#title' => $this->t('Order workflow'),
        '#options' => $workflows,
        '#ajax' => [
          'event' => 'change',
          'callback' => '::updateMessageContainer',
          'wrapper' => 'message_container',
        ],
      ];
    }

    $form['message_container'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => 'message_container',
      ],
    ];

    foreach ($workflow_data['states'] as $state_id => $state_label) {
      $config = $this->config("commerce_order_messages.{$selected_workflow}.{$state_id}");

      $form['message_container'][$state_id] = [
        '#type' => 'details',
        '#title' => reset($state_label),
        '#open' => TRUE,
      ];

      $form['message_container'][$state_id]['message'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Message'),
        '#default_value' => $config->get('message'),
      ];
    }

    $form['selected_workflow'] = [
      '#type' => 'hidden',
      '#value' => $selected_workflow,
    ];

    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['user', 'commerce_order'],
      '#show_restricted' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback: update message container based on selected workflow.
   */
  public function updateMessageContainer(array &$form, FormStateInterface $form_state) {
    return $form['message_container'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config_factory = \Drupal::configFactory();
    $selected_workflow = $form_state->getValue('selected_workflow');

    foreach ($form_state->getValue('message_container') as $state => $message) {
      $config_factory->getEditable("commerce_order_messages.{$selected_workflow}.{$state}")
        ->set('message', reset($message))
        ->save();
    }
  }
}
