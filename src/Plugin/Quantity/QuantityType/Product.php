<?php

namespace Drupal\farm_product\Plugin\Quantity\QuantityType;

use Drupal\farm_entity\Plugin\Quantity\QuantityType\FarmQuantityType;

/**
 * Provides the product quantity type.
 *
 * @QuantityType(
 *   id = "product",
 *   label = @Translation("Product"),
 * )
 */
class Product extends FarmQuantityType {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {

    // Inherit default quantity fields.
    $fields = parent::buildFieldDefinitions();

    // Product type.
    $options = [
      'type' => 'entity_reference',
      'label' => $this->t('Product type'),
      'target_type' => 'taxonomy_term',
      'target_bundle' => 'product_type',
      'auto_create' => TRUE,
      'weight' => [
        'form' => -50,
        'view' => -50,
      ],
    ];
    $fields['product_type'] = $this->farmFieldFactory->bundleFieldDefinition($options);

    return $fields;
  }

}
