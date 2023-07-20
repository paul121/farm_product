<?php

namespace Drupal\farm_product\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity\Revision\RevisionableContentEntityBase;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Package entity.
 *
 * @ContentEntityType(
 *   id = "package",
 *   label = @Translation("Package"),
 *   label_collection = @Translation("Packages"),
 *   label_singular = @Translation("package"),
 *   label_plural = @Translation("packages"),
 *   label_count = @PluralTranslation(
 *     singular = "@count package",
 *     plural = "@count packages",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "access" = "\Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "permission_provider" = "\Drupal\entity\UncacheableEntityPermissionProvider",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\AdminHtmlRouteProvider",
 *       "revision" = "\Drupal\entity\Routing\RevisionRouteProvider",
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *   },
 *   base_table = "package",
 *   revision_table = "package_revision",
 *   translatable = FALSE,
 *   revisionable = TRUE,
 *   show_revision_ui = FALSE,
 *   admin_permission = "administer package",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "label" = "label",
 *     "owner" = "uid",
 *     "uuid" = "uuid",
 *   },
 *   common_reference_target = TRUE,
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 * )
 */
class Package extends RevisionableContentEntityBase implements PackageInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use RevisionLogEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $package = $this->get('package_units')->referencedEntities()[0];
    $units = $this->get('base_units')->referencedEntities()[0];
    $value = $this->get('value')->decimal;
    $package->label();
    return "{$package->label()} ($value {$units->label()})";
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public static function getRequestTime() {
    return \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields += static::revisionLogBaseFieldDefinitions($entity_type);

    $fields['package_units'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Package Units'))
      ->setDescription(t('Units of the package.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'unit' => 'unit',
        ],
        'auto_create' => TRUE,
        'auto_create_bundle' => 'unit',
      ])
      ->setSetting('handler', 'default')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => FALSE,
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['base_units'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Base units'))
      ->setDescription(t('Base units per package.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'unit' => 'unit',
        ],
        'auto_create' => TRUE,
        'auto_create_bundle' => 'unit',
      ])
      ->setSetting('handler', 'default')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => FALSE,
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['value'] = BaseFieldDefinition::create('fraction')
      ->setLabel(t('Value'))
      ->setDescription(t('The number of base units per package.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'fraction_decimal',
        'settings' => [
          'precision' => 0,
          'auto_precision' => TRUE,
          'separator' => '/',
          'prefix_suffix' => FALSE,
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'fraction_decimal',
        'settings' => [
          'precision' => 0,
          'auto_precision' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the package.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\quantity\Entity\Quantity::getCurrentUserId')
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the package was created.'))
      ->setRevisionable(TRUE)
      ->setDefaultValueCallback(static::class . '::getRequestTime')
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the package was last edited.'))
      ->setRevisionable(TRUE);

    return $fields;
  }

}
