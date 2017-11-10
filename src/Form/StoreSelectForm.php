<?php

namespace Drupal\commerce_store_selector\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_store\Entity\Store;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The StoreSelectForm form.
 */
class StoreSelectForm extends FormBase {

  /**
   * The SessionManager interface.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * The Account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The PrivateTempStore.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  /**
   * An array of all stores known to the system.
   *
   * @var array
   */
  protected $stores;

  /**
   * Dependency injection through the constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session_manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current_user.
   */
  public function __construct(RequestStack $request_stack, SessionManagerInterface $session_manager, AccountInterface $current_user) {
    $this->setRequestStack($request_stack);
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;

    $query = \Drupal::entityQuery('commerce_store');
    $store_ids = $query->execute();

    $this->stores = [];
    foreach ($store_ids as $key => $id) {
      $store = Store::load($id);
      $this->stores[$store->id()] = $store;
    }
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
      $container->get('request_stack'),
      $container->get('session_manager'),
      $container->get('current_user')
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
    // Disable cache on the form.
    $form['#cache'] = [
      'max-age' => 0,
    ];

    // If the user is anonymous, and no session has been started yet, start a
    // session for the current request.
    if ($this->currentUser->isAnonymous() && !isset($_SESSION['session_started'])) {
      $_SESSION['session_started'] = TRUE;
      $this->sessionManager->start();
    }

    if (!$this->stores || count($this->stores) < 2) {
      $form['store_markup_default'] = [
        '#markup' => $this->t('At least 2 stores are required for this form to function.'),
        '#weight' => 1,
        '#suffix' => '</br>',
      ];
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::entityTypeManager();

    // Get current request.
    $current_request = $this->requestStack->getCurrentRequest();

    // Get the cookie variable from the current request.
    $cookie_store_id = (int) $current_request->cookies->get('Drupal_visitor_store_id');

    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $entity_type_manager->getStorage('commerce_store');

    $stores = [];
    foreach ($this->stores as $id_key => $store) {
      $stores[$id_key] = $store->getName();
    }

    $form['store_id'] = [
      '#type' => 'select',
      '#title' => t('Select store'),
      '#options' => $stores,
      '#weight' => 10,
    ];
    if ($cookie_store_id) {
      $store_from_cookie = Store::load($cookie_store_id);
    }

    // Call the current store resolver service.
    $current_store = \Drupal::service('commerce_store.current_store')->getStore();
    // If no current store is set.
    if (!$current_store) {
      // Fall back to the default store.
      $current_store = $store_storage->loadDefault();
      // If a default store has not been configured.
      if (!$current_store) {
        // Use the first in the row.
        $current_store = reset($this->stores);
      }
    }

    // Set default value.
    $form['store_id']['#default_value'] = $current_store->id();

    $form['store_markup_default'] = [
      '#markup' => $this->t('Current store: @name', [
        '@name' => $current_store->getName(),
      ]),
      '#weight' => 1,
      '#suffix' => '</br>',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Switch!'),
      '#button_type' => 'primary',
    ];

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
