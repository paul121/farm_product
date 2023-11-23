<?php

namespace Drupal\farm_product\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\quantity\Entity\Quantity as QuantityEntity;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

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
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return NULL;
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

  /**
   * Validation for quantity.
   *
   * @param array $element
   *   The quantity element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateQuantity(array &$element, FormStateInterface $form_state, array &$complete_form) {

    // Do not use $element['#value'] because it is raw input and has not
    // been validated by child elements like entity autocomplete.
    // Get validated values from form state.
    $input = $form_state->getValue($element['#parents']);

    // Only create the quantity if a value is provided.
    if (!isset($input['value']) || !is_numeric($input['value'])) {
      $form_state->setValueForElement($element, NULL);
      return;
    }

    // If a type isn't set, get the default type.
    if (empty($input['type'])) {
      $input['type'] = farm_log_quantity_default_type();
    }

    // If the units are a term name, create or load the unit taxonomy term.
    if (!empty($input['units'])) {

      // If units is a numeric value, assume that it is already a term ID.
      // Otherwise, assume it is a string and load or create a new term.
      if (!is_array($input['units'])) {
        if (!is_numeric($input['units'])) {
          $input['units'] = self::createOrLoadTerm($input['units'], 'unit');
        }
      }

      // Or, if units is an array, and it has either a target_id or entity,
      // translate it to units_id. This will be the case when a term is selected
      // via the UI, when referencing an existing term or creating a new one,
      // respectively.
      elseif (is_array($input['units'])) {

        // If an existing term is selected, target_id will be set.
        if (!empty($input['units']['target_id'])) {
          $input['units'] = $input['units']['target_id'];
        }

        // Or, if a new term is being created, the full entity is available.
        elseif (!empty($input['units']['entity']) && $input['units']['entity'] instanceof TermInterface) {
          $input['units'] = $input['units']['entity'];
        }
      }

    }

    // Create a new quantity entity.
    // @todo Validate that the quantity is valid.
    /** @var \Drupal\quantity\Entity\QuantityInterface $quantity */
    $quantity = QuantityEntity::create($input);
    $form_state->setValueForElement($element, $quantity);
  }

  /**
   * Given a term name, create or load a matching term entity.
   *
   * @param string $name
   *   The term name.
   * @param string $vocabulary
   *   The vocabulary to search or create in.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The term entity that was created or loaded.
   */
  protected static function createOrLoadTerm(string $name, string $vocabulary) {

    // First try to load an existing term.
    $search = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $name, 'vid' => $vocabulary]);
    if (!empty($search)) {
      return reset($search);
    }

    // Start a new term entity with the provided values.
    /** @var \Drupal\taxonomy\TermInterface $term */
    return Term::create([
      'name' => $name,
      'vid' => $vocabulary,
    ]);
  }

}
