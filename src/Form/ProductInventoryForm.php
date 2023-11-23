<?php

namespace Drupal\farm_product\Form;

use Drupal\asset\Entity\Asset;
use Drupal\asset\Entity\AssetInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\log\Entity\Log;
use Drupal\quantity\Entity\Quantity;
use Drupal\taxonomy\TermInterface;

/**
 * Product inventory form.
 */
class ProductInventoryForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'farm_product_product_inventory_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TermInterface $product_type = NULL, AssetInterface $product = NULL) {

    // Default form title.
    $form['#title'] = $this->t('Manage inventory');

    // Prepopulate the product and unit options if possible.
    $unit_options = [];
    $product_options = [];
    $disable_product_options = FALSE;
    if ($product) {
      $disable_product_options = TRUE;
      $product_options[$product->id()] = $product;
      $product_type = $product->get('product_type')->entity;
      $form['#title'] = $this->t('Manage %label inventory', ['%label' => $product->label()]);
    }

    // Build unit options.
    if ($product_type) {

      // Load product assets of the product type.
      if (empty($product)) {
        $product_options = \Drupal::entityTypeManager()->getStorage('asset')->loadByProperties([
          'type' => 'product',
          'status' => 'active',
          'product_type' => $product_type->id(),
        ]);

        $form['#title'] = $this->t('Manage %label inventory', ['%label' => $product_type->label()]);
      }

      // Build unit options.
      foreach ($product_type->get('packages')->referencedEntities() as $unit) {
        $unit_options[$unit->id()] = $unit->label();
      }
    }

    // @todo Build unit options via ajax and product type selector.
    // Build text search if no product options.
    if (count($product_options) === 0) {
      $form['wrapper']['product_search'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Product asset'),
        '#required' => TRUE,
        '#target_type' => 'asset',
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => ['product'],
        ],
        '#tags' => FALSE,
        '#size' => 15,
        '#ajax' => [
          'callback' => '::unitsCallback',
          'event' => 'change autocompleteclose',
          'wrapper' => 'edit-quantity',
          'progress' => [
            'type' => 'hidden',
          ],
        ],
      ];
    }
    else {
      $options = array_map(function (AssetInterface $product) {
        return $product->label();
      }, $product_options);
      $form['wrapper']['product_select'] = [
        '#type' => 'select',
        '#title' => $this->t('Product asset'),
        '#options' => $options,
        '#default_value' => array_key_first($options),
        '#disabled' => $disable_product_options,
        '#required' => TRUE,
      ];
    }

    $form['wrapper']['timestamp'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date'),
      '#description' => $this->t('The date and time of the inventory adjustment.'),
      '#default_value' => new DrupalDateTime('midnight'),
      '#required' => TRUE,
    ];

    $form['wrapper']['adjustment'] = [
      '#type' => 'select',
      '#title' => $this->t('Inventory adjustment'),
      '#required' => TRUE,
      '#options' => [
        'increment' => $this->t('Increment'),
        'decrement' => $this->t('Decrement'),
        'reset' => $this->t('Reset'),
      ],
      '#default_value' => 'increment',
    ];

    if (empty($unit_options) && $selected_product_id = $form_state->getValue('product_search')) {
      $product = Asset::load($selected_product_id);
      foreach ($product->get('product_type')->entity->get('packages')->referencedEntities() as $unit) {
        $unit_options[$unit->id()] = $unit->label();
      }
    }

    $form['wrapper']['quantity'] = [
      '#type' => 'quantity',
      '#title' => $this->t('Value'),
      '#required' => TRUE,
      '#type_value' => 'standard',
      '#label_hidden' => TRUE,
      '#units_options' => $unit_options,
      '#measure_hidden' => TRUE,
      '#attributes' => [
        'id' => 'edit-quantity',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];

    return $form;
  }

  /**
   * Units ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The new quantity render array.
   */
  public function unitsCallback(array $form, FormStateInterface $form_state) {
    return $form['wrapper']['quantity'];
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Load the product asset.
    if ($form_state->hasValue('product_search')) {
      $product_id = $form_state->getValue('product_search');
    }
    if ($form_state->hasValue('product_select')) {
      $product_id = $form_state->getValue('product_select');
    }
    if (empty($product_id)) {
      return;
    }
    $product = Asset::load($product_id);

    // Create the quantity inventory adjustment.
    $adjustment = $form_state->getValue('adjustment');
    $quantity = $form_state->getValue('quantity');
    $quantity->set('inventory_asset', $product);
    $quantity->set('inventory_adjustment', $form_state->getValue('adjustment'));
//    $quantity = Quantity::create(
//      array_filter($form_state->getValue('quantity')) + [
//        'inventory_asset' => $product,
//        'inventory_adjustment' => $form_state->getValue('adjustment'),
//      ]
//    );

    // Create the activity log.
    $log = Log::create([
      'type' => 'activity',
      'status' => 'done',
      'name' => "$adjustment {$product->label()} inventory",
      'timestamp' => $form_state->getValue('timestamp')->getTimestamp(),
      'quantity' => $quantity,
    ]);
    $log->save();

    // Display a message with a link to the log.
    $message = $this->t('Log created: <a href=":url">@name</a>', [':url' => $log->toUrl()->toString(), '@name' => $log->label()]);
    $this->messenger()->addStatus($message);
  }

}
