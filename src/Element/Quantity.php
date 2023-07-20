<?php

namespace Drupal\farm_product\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Form element for quantities.
 *
 * @FormElement("quantity")
 */
class Quantity extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
//        [$class, 'processAutocomplete'],
        [$class, 'preRenderQuantity'],
      ],
      '#pre_render' => [
        //[$class, 'preRenderQuantity'],
      ],
      '#element_validate' => [
        [$class, 'validateQuantity'],
      ],
      '#border' => FALSE,
      '#type_value' => 'standard',
      'type' => [
        '#type' => 'hidden',
      ],
      'label' => [
        '#title' => $this->t('Label'),
        '#weight' => 0,
      ],
      'value' => [
        '#title' => $this->t('Value'),
        '#weight' => 5,
      ],
      '#units_autocreate' => FALSE,
      'units' => [
        '#title' => $this->t('Units'),
        '#weight' => 10,
      ],
      '#measure_options' => quantity_measure_options(),
      'measure' => [
        '#title' => $this->t('Measure'),
        '#weight' => 15,
      ],
    ];
  }

  /**
   * @param $element
   *
   * @return array
   */
  public static function preRenderQuantity($element) {

    // Start a render array with a fieldset.
    $element += [
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'id' => $element['#attributes']['id'] ?? NULL,
            'class' => ['inline-container'],
            'style' => ['display: flex; flex-wrap: wrap; column-gap: 2em;'],
          ],
        ],
      ],
      '#tree' => TRUE,
    ];

    // Auto-hide fields if #value is provided.
    $hidden_count = 0;
    foreach (['type', 'label', 'value', 'units', 'measure'] as $field_name) {

      // Configure options if provided.
      if (isset($element["#{$field_name}_options"])) {
        $element[$field_name]['#type'] = 'select';
        $element[$field_name]['#wrapper_attributes']['id'] = "edit-quantity-$field_name-wrapper";
        $element[$field_name]['#options'] = $element["#{$field_name}_options"];
      }

      // Or check if a hard-coded value is provided.
      if (
        isset($element["#{$field_name}_value"]) ||
        (isset($element["#{$field_name}_hidden"]) && $element["#{$field_name}_hidden"])
      ) {
        $hidden_count++;
        $element[$field_name]['#type'] = 'hidden';
        $element[$field_name]['#value'] = $element["#{$field_name}_value"] ?? NULL;
      }
    }

    // Render a fieldset unless we are only rendering the value field.
    if ($hidden_count !== 4) {
      #$element['#theme_wrappers'][] = 'fieldset';
    }

    // Label defaults to textfield type.
    if (!isset($element['label']['#type'])) {
      $element['label'] += [
        '#type' => 'textfield',
        '#title' => t('Label'),
        '#size' => 15,
      ];
    }

    // Units defaults to entity_autocomplete if nothing is configured.
    if (!isset($element['units']['#type'])) {
      $element['units'] += [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => ['unit'],
        ],
        '#tags' => FALSE,
        '#size' => 15,
      ];

      // Optionally add autocreate.
      if ($element['#units_autocreate'] ?? FALSE) {
        $element['units']['#autocreate'] = [
          'bundle' => 'unit',
        ];
      }
    }

    // Value defaults to number input.
    if (!isset($element['value']['#type'])) {
      $element['value'] += [
        '#type' => 'number',
        '#min' => 0,
        '#step' => 0.0001,
      ];

      // If only rendering a value, the quantity title and description.
      if ($hidden_count == 4) {
        $element['value']['#title'] = $element['#title'] ?? NULL;
        $element['value']['#description'] = $element['#description'] ?? NULL;
        unset($element['#title']);
        unset($element['##description']);
      }
    }

    // If the unit value is hard-coded add a field suffix to the value field
    // with the first option label.
    if (isset($element['units']['#value'])) {
      $element['value']['#field_suffix'] = $element['units']['#value'];
    }

    // Set required property.
    $element['value']['#required'] = $element['#required'] ?? FALSE;

    return $element;
  }

  public static function validateQuantity(&$element, FormStateInterface $form_state, &$complete_form) {

  }

}
