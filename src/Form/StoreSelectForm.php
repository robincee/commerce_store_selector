<?php

/**
 * @file
 * Contains \Drupal\commerce_store_selector\Form\StoreSelectForm.
 */

namespace Drupal\commerce_store_selector\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_store\Entity\Store;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StoreSelectForm extends FormBase {

  /**
   * Dependency injection through the constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(RequestStack $request_stack) {
    $this->setRequestStack($request_stack);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Forms that require a Drupal service or a custom service should access
    // the service using dependency injection.
    // @link https://www.drupal.org/node/2203931.
    // Those services are passed in the $container through the static create
    // method.
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'store_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $current_request = $this->requestStack->getCurrentRequest();
    $cookie_store_id = (int) $current_request->cookies->get('Drupal_visitor_store_id');


    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $entity_type_manager->getStorage('commerce_store');

    $query = \Drupal::entityQuery('commerce_store');
    $entity_ids = $query->execute();

    $stores = [];
    foreach ($entity_ids as $key => $id) {
      $store = Store::load($id);
      $stores[$store->id()] = $store->getName();
    }

    // Call the current store resolver service.
    $current_store = \Drupal::service('commerce_store.current_store')->getStore();

    $form['store_markup_default'] = array(
        '#markup' => $this->t('Resolved: @name (id: @store_id)', array('@store_id' => $current_store->id(), '@name' => $current_store->getName())),
      '#weight' => 1,
      '#suffix' => '</br>',
    );

    $form['store_id'] = array(
      '#type' => 'select',
      '#title' => t('Select store'),
      '#options' => $stores,
      '#weight' => 10,
    );
    if (!empty($cookie_store_id)) {
      $store = Store::load($cookie_store_id);
      $form['store_markup_current'] = array(
        '#markup' => $this->t('From cookie: @name (id: @store_id)', array('@store_id' => $store->id(), '@name' => $store->getName())),
        '#weight' => 2,
        '#suffix' => '</br>',
      );
    }

    if (!empty($current_store)) {
      $fallback_store_id = $current_store->id();
    }
    else {
      $default_store = $store_storage->loadDefault();
      $fallback_store_id = $default_store->id();
    }
    $form['store_id']['#default_value'] = !empty($store) ? $store->id() : $fallback_store_id;

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Switch!'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $store_id = $form_state->getValue('store_id');
    user_cookie_save(['store_id' => $store_id]);
  }

}
