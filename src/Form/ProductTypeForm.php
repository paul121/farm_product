<?php

namespace Drupal\farm_product\Form;

use Drupal\asset\Entity\Asset;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\taxonomy\Entity\Term;

/**
 * Product type form.
 */
class ProductTypeForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'farm_product_product_type_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Product type form.
    $form['product_type'] = [
      '#type' => 'fieldset',
      '#parents' => ['product_type'],
    ];
    $product_type_subform = SubformState::createForSubform($form['product_type'], $form, $form_state);
    $product_type = Term::create([
      'vid' => 'product_type',
    ]);
    $product_type_display = EntityFormDisplay::collectRenderDisplay($product_type, NULL, FALSE);
    $form_state->set('product_type_display', $product_type_display);
    $fields = [
      'name' => [],
      'packages' => [
        'required' => TRUE,
        'type' => 'inline_entity_form_complex',
        'settings' => [
          'form_mode' => 'default',
          'override_labels' => TRUE,
          'label_singular' => $this->t('package unit'),
          'label_plural' => $this->t('package units'),
          'allow_new' => TRUE,
          'allow_existing' => TRUE,
          'match_operator' => 'CONTAINS',
        ],
      ],
    ];
    foreach ($fields as $field_name => $options) {
      $product_type_display->setComponent($field_name, $options);
    }
    $product_type_display->buildForm($product_type, $form['product_type'], $product_type_subform);

    $form['create_product'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create Product asset'),
      '#description' => $this->t('Manage the inventory of this product type by creating a product asset.'),
      '#default_value' => TRUE,
    ];

    $default_name = '';
    $form['product'] = [
      '#type' => 'fieldset',
      '#parents' => ['product'],
      '#states' => [
        'visible' => [
          ':input[name="create_product"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $product = Asset::create([
      'type' => 'product',
      'name' => $default_name,
      'product_type' => $product_type,
    ]);

    $product_subform = SubformState::createForSubform($form['product'], $form, $form_state);
    $product_display = EntityFormDisplay::collectRenderDisplay($product, NULL, FALSE);
    $form_state->set('product_display', $product_display);
    $fields = [
      'name' => [],
      'notes' => [],
    ];
    foreach ($fields as $field_name => $options) {
      $product_display->setComponent($field_name, $options);
    }
    $product_display->buildForm($product, $form['product'], $product_subform);

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
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Create the product type.
    $product_type = Term::create([
      'vid' => 'product_type',
    ]);
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $product_type_display */
    $product_type_display = $form_state->get('product_type_display');
    $product_type_display->extractFormValues($product_type, $form['product_type'], $form_state);
    $product_type->save();

    // Display a message with a link to the product type.
    $message = $this->t('Product type created: <a href=":url">@name</a>', [':url' => $product_type->toUrl()->toString(), '@name' => $product_type->label()]);
    $this->messenger()->addStatus($message);

    // Create the product asset if specified.
    if ($form_state->getValue('create_product')) {
      $product = Asset::create([
        'type' => 'product',
        'product_type' => $product_type,
      ]);
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $product_display */
      $product_display = $form_state->get('product_display');
      $product_display->extractFormValues($product, $form['product'], $form_state);
      $product->save();

      // Display a message with a link to the product type.
      $message = $this->t('Product created: <a href=":url">@name</a>', [':url' => $product->toUrl()->toString(), '@name' => $product->label()]);
      $this->messenger()->addStatus($message);
    }

    $form_state->setRedirect('view.product_types.page');
  }

}
