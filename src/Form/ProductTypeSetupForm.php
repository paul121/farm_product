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
 * Product type setup form.
 */
class ProductTypeSetupForm extends FormBase {

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
      '#placeholder' => $this->t('Filter by product type or package name (todo)'),
      '#attributes' => [
//        'class' => ['views-filter-text'],
//        'data-table' => '.views-listing-table',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the view name, machine name, description, or display path to filter by.'),
      ],
    ];

    // Get the product type vocabulary.
    $taxonomy_vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('product_type');

    // Only allow access to change parents and reorder the tree if there are no
    // pending revisions and there are no terms with multiple parents.
    $vocabulary_hierarchy = $this->storageController->getVocabularyHierarchyType($taxonomy_vocabulary->id());
    $update_tree_access = AccessResult::allowedIf(empty($pending_term_ids) && $vocabulary_hierarchy !== VocabularyInterface::HIERARCHY_MULTIPLE);

    $parent_fields = FALSE;
    $form['terms'] = [
      '#type' => 'table',
      '#empty' => 'No product types.',
      '#header' => [
        'term' => $this->t('Name'),
        'packages' => $this->t('Packages'),
        'operations' => $this->t('Operations'),
        'weight' => $update_tree_access->isAllowed() ? $this->t('Weight') : NULL,
      ],
      '#attributes' => [
//        'id' => 'taxonomy',
      ],
    ];

    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('taxonomy_term');
    $create_access = $access_control_handler->createAccess($taxonomy_vocabulary->id(), NULL, [], TRUE);
    $this->renderer->addCacheableDependency($form['terms'], $create_access);

    $tree = $this->storageController->loadTree($taxonomy_vocabulary->id(), 0, NULL, TRUE);

    foreach ($tree as $key => $term) {
      $form['terms'][$key] = [
        'term' => [],
        'packages' => ['test'],
        'operations' => [],
        'weight' => $update_tree_access->isAllowed() ? [] : NULL,
      ];
      /** @var \Drupal\Core\Entity\EntityInterface $term */
      $term = $this->entityRepository->getTranslationFromContext($term);
      $form['terms'][$key]['#term'] = $term;
      $indentation = [];
      if (isset($term->depth) && $term->depth > 0) {
        $indentation = [
          '#theme' => 'indentation',
          '#size' => $term->depth,
        ];
      }
      $form['terms'][$key]['term'] = [
        '#prefix' => !empty($indentation) ? $this->renderer->render($indentation) : '',
        '#type' => 'link',
        '#title' => $term->getName(),
        '#url' => $term->toUrl(),
      ];

      // Add a special class for terms with pending revision so we can highlight
      // them in the form.
      $form['terms'][$key]['#attributes']['class'] = [];

      if ($update_tree_access->isAllowed() && count($tree) > 1) {
        $parent_fields = TRUE;
        $form['terms'][$key]['term']['tid'] = [
          '#type' => 'hidden',
          '#value' => $term->id(),
          '#attributes' => [
            'class' => ['term-id'],
          ],
        ];
        $form['terms'][$key]['term']['parent'] = [
          '#type' => 'hidden',
          // Yes, default_value on a hidden. It needs to be changeable by the
          // javascript.
          '#default_value' => $term->parents[0],
          '#attributes' => [
            'class' => ['term-parent'],
          ],
        ];
        $form['terms'][$key]['term']['depth'] = [
          '#type' => 'hidden',
          // Same as above, the depth is modified by javascript, so it's a
          // default_value.
          '#default_value' => $term->depth,
          '#attributes' => [
            'class' => ['term-depth'],
          ],
        ];
      }
      $update_access = $term->access('update', NULL, TRUE);
      $update_tree_access = $update_tree_access->andIf($update_access);

      $package_units = $term->get('packages')->referencedEntities();
      $package_items = array_map(function (Term $unit) {
        $package_label = NULL;
        if ($package_unit = $unit->get('package_unit')->entity) {
          if (!$unit->get('package_quantity')->isEmpty()) {
            $package_label = $unit->get('package_quantity')->first()->get('decimal')->getValue(). ' '.  $package_unit->label();
          }
        }
        return [
          '#type' => 'link',
          '#title' => $unit->label() . " ($package_label)",
          '#url' => $unit->toUrl(),
        ];
      }, $package_units);

      $list = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $package_items,
      ];

      $form['terms'][$key]['packages'] = $list;

      if ($update_tree_access->isAllowed()) {
        $form['terms'][$key]['weight'] = [
          '#type' => 'weight',
          #'#delta' => $delta,
          '#title' => $this->t('Weight for added term'),
          '#title_display' => 'invisible',
          '#default_value' => $term->getWeight(),
          '#attributes' => ['class' => ['term-weight']],
        ];
      }

      if ($operations = $this->termListBuilder->getOperations($term)) {
        $form['terms'][$key]['operations'] = [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $term->toUrl('edit-form')
            ],
          ],
        ];
      }

      if ($parent_fields) {
        $form['terms'][$key]['#attributes']['class'][] = 'draggable';
      }

    }

    $this->renderer->addCacheableDependency($form['terms'], $update_tree_access);
    if ($update_tree_access->isAllowed()) {
      if ($parent_fields) {
        $form['terms']['#tabledrag'][] = [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'term-parent',
          'subgroup' => 'term-parent',
          'source' => 'term-id',
          'hidden' => FALSE,
        ];
        $form['terms']['#tabledrag'][] = [
          'action' => 'depth',
          'relationship' => 'group',
          'group' => 'term-depth',
          'hidden' => FALSE,
        ];

      }
      $form['terms']['#tabledrag'][] = [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'term-weight',
      ];
    }

    if ($update_tree_access->isAllowed() && count($tree) > 1) {
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

    $changed_terms = [];
    $tree = $this->storageController->loadTree('product_type', 0, NULL, TRUE);

    if (empty($tree)) {
      return;
    }

    // Renumber the current page weights and assign any new parents.
    $weight = 0;
    $level_weights = [];
    foreach ($form_state->getValue('terms') as $tid => $values) {
      if (isset($form['terms'][$tid]['#term'])) {
        $term = $form['terms'][$tid]['#term'];
        // Give terms at the root level a weight in sequence with terms on previous pages.
        if ($values['term']['parent'] == 0 && $term->getWeight() != $weight) {
          $term->setWeight($weight);
          $changed_terms[$term->id()] = $term;
        }
        // Terms not at the root level can safely start from 0 because they're all on this page.
        elseif ($values['term']['parent'] > 0) {
          $level_weights[$values['term']['parent']] = isset($level_weights[$values['term']['parent']]) ? $level_weights[$values['term']['parent']] + 1 : 0;
          if ($level_weights[$values['term']['parent']] != $term->getWeight()) {
            $term->setWeight($level_weights[$values['term']['parent']]);
            $changed_terms[$term->id()] = $term;
          }
        }
        // Update any changed parents.
        if ($values['term']['parent'] != $term->parents[0]) {
          $term->parent->target_id = $values['term']['parent'];
          $changed_terms[$term->id()] = $term;
        }
        $weight++;
      }
    }

    // Save all updated terms.
    foreach ($changed_terms as $term) {
      $term->save();
    }

    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }

}
