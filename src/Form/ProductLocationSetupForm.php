<?php

namespace Drupal\farm_product\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Product location setup form.
 */
class ProductLocationSetupForm extends FormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The term storage handler.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $storageController;

  /**
   * The term list builder.
   *
   * @var \Drupal\Core\Entity\EntityListBuilderInterface
   */
  protected $termListBuilder;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Constructs an OverviewTerms object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, EntityRepositoryInterface $entity_repository, PagerManagerInterface $pager_manager) {
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->storageController = $entity_type_manager->getStorage('taxonomy_term');
    $this->termListBuilder = $entity_type_manager->getListBuilder('taxonomy_term');
    $this->renderer = $renderer;
    $this->entityRepository = $entity_repository;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('entity.repository'),
      $container->get('pager.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_product_type_setup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo Implement buildForm() method.

    $form['filters']['text'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter'),
      '#title_display' => 'invisible',
      '#size' => 60,
      '#placeholder' => $this->t('Filter by location, product type or product'),
      '#attributes' => [
//        'class' => ['views-filter-text'],
//        'data-table' => '.views-listing-table',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the view name, machine name, description, or display path to filter by.'),
      ],
    ];

    $form['products'] = [
      '#type' => 'table',
      '#empty' => 'No products.',
      '#header' => [
        'location' => $this->t('Location'),
        'products' => $this->t('Products'),
        'operations' => $this->t('Operations'),
      ],
      '#attributes' => [
//        'id' => 'taxonomy',
      ],
    ];

    $product_locations = $this->entityTypeManager->getStorage('asset')->getAggregateQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'product')
      ->condition('parent.entity:asset.is_location', TRUE)
      ->groupBy('parent')
      ->groupBy('id')
      ->sortAggregate('id', 'COUNT', 'DESC')
      ->execute();
    $product_location_map = [];

    $location_ids = array_column($product_locations, 'parent_target_id');
    $locations = $this->entityTypeManager->getStorage('asset')->loadMultiple($location_ids);

    $product_ids = array_column($product_locations, 'id');
    $products = $this->entityTypeManager->getStorage('asset')->loadMultiple($product_ids);

    foreach ($product_locations as $pair) {
      $product_location_map[$pair['parent_target_id']][] = $products[$pair['id']];
    }

    foreach ($locations as $key => $location) {
      $form['products'][$key] = [
        'location' => [],
        'products' => [],
        'operations' => [],
      ];

      $form['products'][$key]['location'] = [
        '#type' => 'link',
        '#title' => $location->label(),
        '#url' => $location->toUrl(),
      ];

      $product_items = array_map(function ($product) {
        return [
          '#type' => 'link',
          '#title' => $product->label(),
          '#url' => $product->toUrl(),
        ];
      }, $product_location_map[$location->id()]);

      $form['products'][$key]['products'] = [
        '#type' => 'details',
        '#title' => count($product_items) . ' products',
        'list' => [
          '#theme' => 'item_list',
          '#items' => $product_items,
        ],
      ];

      $form['products'][$key]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => $location->toUrl('edit-form'),
            'weight' => 0,
          ],
        ],
      ];
    }

    if (1) {
      $form['actions'] = ['#type' => 'actions', '#tree' => FALSE];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }

}
