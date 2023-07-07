<?php

namespace Drupal\field_ui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_ui\FieldUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for the "field storage" add page.
 *
 * @internal
 */
class FieldStorageAddForm extends FormBase {

  use FieldStorageCreationTrait;

  /**
   * The name of the entity type.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The entity bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new FieldStorageAddForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_plugin_manager
   *   The field type plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface|null $entity_field_manager
   *   (optional) The entity field manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   (optional) The entity display repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FieldTypePluginManagerInterface $field_type_plugin_manager, ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager = NULL, EntityDisplayRepositoryInterface $entity_display_repository = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_ui_field_storage_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $bundle = NULL) {
    if (!$form_state->get('entity_type_id')) {
      $form_state->set('entity_type_id', $entity_type_id);
    }
    if (!$form_state->get('bundle')) {
      $form_state->set('bundle', $bundle);
    }
    $this->entityTypeId = $form_state->get('entity_type_id');
    $this->bundle = $form_state->get('bundle');

    // Field label and field_name.
    $form['new_storage_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['field-ui-new-storage-wrapper'],
      ],
      '#states' => [
        '!visible' => [
          ':input[name="new_storage_type"]' => ['value' => ''],
        ],
      ],
    ];
    $form['new_storage_wrapper']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#size' => 30,
    ];

    $field_type_options = $unique_definitions = [];
    $grouped_definitions = $this->fieldTypePluginManager->getGroupedDefinitions($this->fieldTypePluginManager->getUiDefinitions());
    // Invoke a hook to get category properties.
    $category_info = \Drupal::moduleHandler()->invokeAll('field_type_category_info');
    foreach ($grouped_definitions as $category => $field_types) {
      foreach ($field_types as $name => $field_type) {
        $unique_definitions[$category][$name] = ['unique_identifier' => $name] + $field_type;
        if (isset($category_info[$category])) {
          // Get the category label from the hook if it is defined in the hook.
          $field_type_options[$category_info[$category]['label']->render()] = ['unique_identifier' => $name] + $field_type;
        }
        else {
          $field_type_options[$field_type['label']->render()] = ['unique_identifier' => $name] + $field_type;
        }
      }
    }
    $form['add-label'] = [
      '#type' => 'label',
      '#title' => t('Choose a type of field'),
      '#required' => TRUE,
    ];

    $form['add'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'add-field-container',
      ],
    ];
    $field_type_options_radios = [];
    foreach ($field_type_options as $field_option => $val) {
      // Boolean flag for whether a field option is to be displayed as a group.
      // When an option should be displayed as a group, its category value
      // is a string id instead of TranslatableMarkup. The string id maps
      // to the values returned by the field_type_category_info hook.
      $display_as_group = is_string($val['category']) && $val['category'] !== FieldTypePluginManager::DEFAULT_CATEGORY;
      $option_info = $display_as_group ? $category_info[$val['category']] : $val;
      $cleaned_class_name = Html::getClass($val['unique_identifier']);
      $field_type_options_radios[$field_option] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['field-option', 'js-click-to-select'],
          'checked' => $this->getRequest()->request->get('new_storage_type') !== NULL && $this->getRequest()->request->get('new_storage_type') == ($display_as_group ? $val['category'] : $val['unique_identifier']),
        ],
        '#weight' => $option_info['weight'] ?? 1,
        'thumb' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field-option__thumb'],
          ],
          'icon' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['field-option__icon', $display_as_group ?
                "field-icon-$val[category]" : "field-icon-$cleaned_class_name",
              ],
            ],
          ],
        ],
        // Store some data we later need.
        '#data' => [
          '#group_display' => $display_as_group,
        ],
        'radio' => [
          '#type' => 'radio',
          '#title' => $field_option,
          '#parents' => ['new_storage_type'],
          '#title_display' => 'before',
          '#description_display' => 'before',
          // If it is a category, set return value as the category label,
          // otherwise, set it as the field type id.
          '#return_value' => $display_as_group ? $val['category'] : $val['unique_identifier'],
          '#attributes' => [
            'class' => ['field-option-radio'],
          ],
          '#ajax' => [
            'callback' => [$this, 'showFieldsCallback'],
            'event' => 'updateOptions',
            'wrapper' => 'group-field-options-wrapper',
            'progress' => 'none',
            'disable-refocus' => TRUE,
          ],
          '#description' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['field-option__description'],
            ],
            '#markup' => $option_info['description'] ?? NULL,
          ],
          '#variant' => 'field-option',
        ],
      ];
    }
    uasort($field_type_options_radios, [SortArray::class, 'sortByWeightProperty']);
    $form['add']['new_storage_type'] = $field_type_options_radios;
    $form['group_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change field group'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[static::class, 'showFieldsHandler']],
    ];
    $form['group_field_options_wrapper'] = [
      '#prefix' => '<div id="group-field-options-wrapper" class="group-field-options-wrapper">',
      '#suffix' => '</div>',
    ];

    // Set the selected field to the form state by checking
    // the checked attribute.
    $selected_field_type = NULL;
    foreach ($field_type_options_radios as $field_type_options_radio) {
      if ($field_type_options_radio['#attributes']['checked']) {
        $selected_field_label = $field_type_options_radio['radio']['#title'];
        $selected_field_type = $field_type_options_radio['radio']['#return_value'];
        $form_state->setValue('selected_field_type', $selected_field_type);
        break;
      }
    }
    if (isset($selected_field_label)) {
      $group_display = $field_type_options_radios[$selected_field_label]['#data']['#group_display'];
      if ($group_display) {
        $form['group_field_options_wrapper']['label'] = [
          '#type' => 'label',
          '#title' => t('Choose an option below'),
          '#required' => TRUE,
        ];
        $form['group_field_options_wrapper']['fields'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['group-field-options'],
          ],
        ];

        foreach ($unique_definitions[$selected_field_type] as $option_key => $option) {
          $radio_element = [
            '#type' => 'radio',
            '#title' => $option['label']->render(),
            '#description' => [
              '#theme' => 'item_list',
              '#items' => $unique_definitions[$selected_field_type][$option_key]['description'],
            ],
            '#id' => $option['unique_identifier'],
            '#parents' => ['group_field_options_wrapper'],
            '#attributes' => [
              'class' => ['field-option-radio'],
              'data-once' => 'field-click-to-select',
            ],
            '#wrapper_attributes' => [
              'class' => ['js-click-to-select', 'subfield-option'],
            ],
            '#variant' => 'field-suboption',
          ];
          $radio_element['#return_value'] = $option['unique_identifier'];
          if ((string) $option['unique_identifier'] === 'entity_reference') {
            $radio_element['#title'] = 'Other';
            $radio_element['#weight'] = 10;
          }
          $form['group_field_options_wrapper']['fields'][$option['unique_identifier']] = $radio_element;
        }
      }
    }
    $field_prefix = $this->config('field_ui.settings')->get('field_prefix');
    $form['new_storage_wrapper']['field_name'] = [
      '#type' => 'machine_name',
      '#field_prefix' => $field_prefix,
      '#size' => 15,
      '#description' => $this->t('A unique machine-readable name containing letters, numbers, and underscores.'),
      // Calculate characters depending on the length of the field prefix
      // setting. Maximum length is 32.
      '#maxlength' => FieldStorageConfig::NAME_MAX_LENGTH - strlen($field_prefix),
      '#machine_name' => [
        'source' => ['new_storage_wrapper', 'label'],
        'exists' => [$this, 'fieldNameExists'],
      ],
      '#required' => FALSE,
    ];
    // Place the 'translatable' property as an explicit value so that contrib
    // modules can form_alter() the value for newly created fields. By default
    // we create field storage as translatable so it will be possible to enable
    // translation at field level.
    $form['translatable'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'] = [
      'field_ui/drupal.field_ui',
      'field_ui/drupal.field_ui.manage_fields',
      'core/drupal.ajax',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('new_storage_type')) {
      $form_state->setErrorByName('new_storage_type', $this->t('You need to select a field type.'));
    }
    elseif (isset($form['group_field_options_wrapper']['fields']) && !$form_state->getValue('group_field_options_wrapper')) {
      $form_state->setErrorByName('group_field_options_wrapper', $this->t('You need to select a field type.'));
    }

    $this->validateAddNew($form, $form_state);
  }

  /**
   * Validates the 'add new field' case.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\field_ui\Form\FieldStorageAddForm::validateForm()
   */
  protected function validateAddNew(array $form, FormStateInterface $form_state) {
    // Validate if any information was provided in the 'add new field' case.
    if ($form_state->getValue('new_storage_type')) {
      // Missing label.
      if (!$form_state->getValue('label')) {
        $form_state->setErrorByName('label', $this->t('Add new field: you need to provide a label.'));
      }

      // Missing field name.
      if (!$form_state->getValue('field_name')) {
        $form_state->setErrorByName('field_name', $this->t('Add new field: you need to provide a machine name for the field.'));
      }
      // Field name validation.
      else {
        $field_name = $form_state->getValue('field_name');

        // Add the field prefix.
        $field_name = $this->configFactory->get('field_ui.settings')->get('field_prefix') . $field_name;
        $form_state->setValueForElement($form['new_storage_wrapper']['field_name'], $field_name);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);

    $field_storage_type = $values['group_field_options_wrapper'] ?? $values['new_storage_type'];
    $field_name = $values['field_name'];
    $field_values = [
      'entity_type' => $this->entityTypeId,
      'bundle' => $this->bundle,
    ];
    // Check if we're dealing with a preconfigured field.
    if (strpos($field_storage_type, 'field_ui:') === 0) {
      [, $field_type, $preset_key] = explode(':', $field_storage_type, 3);
      $default_options = $this->getNewFieldDefaults($field_type, $preset_key);
    }
    else {
      $field_type = $field_storage_type;
    }
    $field_values += [
      ...$default_options['field_config'] ?? [],
      'field_name' => $field_name,
      'label' => $values['label'],
      // Field translatability should be explicitly enabled by the users.
      'translatable' => FALSE,
    ];

    $field_storage_values = [
      ...$default_options['field_storage_config'] ?? [],
      'field_name' => $field_name,
      'type' => $field_type,
      'entity_type' => $this->entityTypeId,
      'translatable' => $values['translatable'],
    ];

    try {
      // Create the field storage.
      $this->entityTypeManager->getStorage('field_storage_config')
        ->create($field_storage_values)->save();

      // Create the field.
      $field = $this->entityTypeManager->getStorage('field_config')
        ->create($field_values);
      $field->save();

      // Configure the display modes.
      $this->configureEntityFormDisplay($field_name, $default_options['entity_form_display'] ?? []);
      $this->configureEntityViewDisplay($field_name, $default_options['entity_view_display'] ?? []);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t(
        'There was a problem creating field %label: @message',
        ['%label' => $values['label'], '@message' => $e->getMessage()]));
      return;
    }

    // Configure next steps in the multi-part form.
    $destinations = [];
    $route_parameters = [
      'field_config' => $field->id(),
    ] + FieldUI::getRouteBundleParameter($entity_type, $this->bundle);
    // Always show the field settings step, as the cardinality needs to be
    // configured for new fields.
    $destinations[] = [
      'route_name' => "entity.field_config.{$this->entityTypeId}_storage_edit_form",
      'route_parameters' => $route_parameters,
    ];

    $destinations[] = [
      'route_name' => "entity.field_config.{$this->entityTypeId}_field_edit_form",
      'route_parameters' => $route_parameters,
    ];
    $destinations[] = [
      'route_name' => "entity.{$this->entityTypeId}.field_ui_fields",
      'route_parameters' => $route_parameters,
    ];
    $destination = $this->getDestinationArray();
    $destinations[] = $destination['destination'];
    $form_state->setRedirectUrl(
      FieldUI::getNextDestination($destinations)
    );

    // Store new field information for any additional submit handlers.
    $form_state->set(['fields_added', '_add_new_field'], $field_name);

    $this->messenger()->addMessage($this->t('Your settings have been saved.'));
  }

  /**
   * Get default options from preconfigured options for a new field.
   *
   * @param string $field_name
   *   The machine name of the field.
   * @param string $preset_key
   *   A key in the preconfigured options array for the field.
   *
   * @return array
   *   An array of settings with keys 'field_storage_config', 'field_config',
   *   'entity_form_display', and 'entity_view_display'.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see \Drupal\Core\Field\PreconfiguredFieldUiOptionsInterface::getPreconfiguredOptions()
   */
  protected function getNewFieldDefaults(string $field_name, string $preset_key): array {
    $field_type_definition = $this->fieldTypePluginManager->getDefinition($field_name);
    $options = $this->fieldTypePluginManager->getPreconfiguredOptions($field_type_definition['id']);
    $field_options = $options[$preset_key] ?? [];

    $default_options = [];
    // Merge in preconfigured field storage options.
    if (isset($field_options['field_storage_config'])) {
      foreach (['cardinality', 'settings'] as $key) {
        if (isset($field_options['field_storage_config'][$key])) {
          $default_options['field_storage_config'][$key] = $field_options['field_storage_config'][$key];
        }
      }
    }

    // Merge in preconfigured field options.
    if (isset($field_options['field_config'])) {
      foreach (['required', 'settings'] as $key) {
        if (isset($field_options['field_config'][$key])) {
          $default_options['field_config'][$key] = $field_options['field_config'][$key];
        }
      }
    }

    // Preconfigured options only apply to the default display modes.
    foreach (['entity_form_display', 'entity_view_display'] as $key) {
      if (isset($field_options[$key])) {
        $default_options[$key] = [
          'default' => array_intersect_key($field_options[$key], ['type' => '', 'settings' => []]),
        ];
      }
      else {
        $default_options[$key] = ['default' => []];
      }
    }

    return $default_options;
  }

  /**
   * Checks if a field machine name is taken.
   *
   * @param string $value
   *   The machine name, not prefixed.
   * @param array $element
   *   An array containing the structure of the 'field_name' element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether or not the field machine name is taken.
   */
  public function fieldNameExists($value, $element, FormStateInterface $form_state) {
    // Add the field prefix.
    $field_name = $this->configFactory->get('field_ui.settings')->get('field_prefix') . $value;

    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId);
    return isset($field_storage_definitions[$field_name]);
  }

  /**
   * Callback for displaying fields after a group has been selected.
   */
  public function showFieldsCallback($form, FormStateInterface &$form_state) {
    return $form['group_field_options_wrapper'];
  }

  /**
   * Submit handler for displaying fields after a group is selected.
   */
  public static function showFieldsHandler($form, FormStateInterface &$form_state) {
    $form_state->setRebuild();
  }

}
