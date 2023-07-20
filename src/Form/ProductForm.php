<?php

namespace Drupal\farm_product\Form;

use Drupal\asset\Entity\Asset;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Form for creating product assets.
 */
class ProductForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'farm_product_product_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TermInterface $product_type = NULL) {

    $form['product_type'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Product type'),
      '#default_value' => $product_type,
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['product_type'],
      ],
      '#tags' => FALSE,
      '#size' => 15,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The name of the product.'),
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Add any notes relevant to this specific product.'),
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
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $product = Asset::create([
      'type' => 'product',
      'name' => $form_state->getValue('name'),
      'product_type' => $form_state->getValue('product_type'),
      'notes' => $form_state->getValue('notes'),
    ]);
    $product->save();

    // Display a message with a link to the product.
    $message = $this->t('Product created: <a href=":url">@name</a>', [':url' => $product->toUrl()->toString(), '@name' => $product->label()]);
    $this->messenger()->addStatus($message);

    $form_state->setRedirect('view.products.page');
  }

}
