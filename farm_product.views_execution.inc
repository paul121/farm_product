<?php

/**
 * @file
 * Provides Views runtime hooks for farm_ui_map.module.
 */

use Drupal\asset\Entity\Asset;
use Drupal\farm_product\Form\ProductInventoryForm;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_pre_render().
 */
function farm_product_views_post_render(ViewExecutable $view, array &$output) {

  if ($view->id() != 'product_inventory') {
    return;
  }

  $exposed = $view->exposed_data;
  $product_type = NULL;
  if (isset($exposed['product_type_target_id'])) {
    $product_type = Term::load(reset($exposed['product_type_target_id'])['target_id']);
  }
  $product = NULL;
  if (isset($exposed['inventory_asset'])) {
    $product = Asset::load(reset($exposed['inventory_asset'])['target_id']);
  }

  // Render inventory form if product asset is provided.
  if ($view->current_display == 'page') {
    $output['#pre_render'][] = function (array $element) use ($product_type, $product) {

      $form = \Drupal::formBuilder()->getForm(ProductInventoryForm::class, $product_type, $product);
      $form['#attributes']['style'] = ['display: flex; flex-wrap: wrap; column-gap: 2em;'];
      $inventory_form = [
        '#type' => 'details',
        '#open' => $product_type || $product,
        '#title' => $form['#title'],
        'form' => $form,
      ];
      $element['#header'][] = $inventory_form;
      return $element;
    };
  }

  // Render inventory form if product asset is provided.
  if ($view->current_display == 'page_asset') {
    if (($product_id = reset($view->args)) && $product = Asset::load($product_id)) {
      $output['#pre_render'][] = function (array $element) use ($product_type, $product) {
        $form = \Drupal::formBuilder()->getForm(ProductInventoryForm::class, $product_type, $product);
        $form['#attributes']['style'] = ['display: flex; flex-wrap: wrap; column-gap: 2em;'];
        $inventory_form = [
          '#type' => 'details',
          '#title' => $form['#title'],
          '#open' => TRUE,
          'form' => $form,
        ];
        $element['#header'][] = $inventory_form;
        return $element;
      };
    }
  }
}
