<?php

namespace Drupal\Core\Field;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataManagerInterface;

/**
 * Plugin manager for 'field type' plugins.
 *
 * @ingroup field_types
 */
class FieldTypePluginManager extends DefaultPluginManager implements FieldTypePluginManagerInterface {

  /**
   * Default category for field types.
   */
  const DEFAULT_CATEGORY = 'general';

  use CategorizingPluginManagerTrait {
    getGroupedDefinitions as protected getGroupedDefinitionsTrait;
  }

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Constructs the FieldTypePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, TypedDataManagerInterface $typed_data_manager) {
    parent::__construct('Plugin/Field/FieldType', $namespaces, $module_handler, 'Drupal\Core\Field\FieldItemInterface', 'Drupal\Core\Field\Annotation\FieldType');
    $this->alterInfo('field_info');
    $this->setCacheBackend($cache_backend, 'field_types_plugins');
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   *
   * Creates a field item, which is not part of an entity or field item list.
   *
   * @param string $field_type
   *   The field type, for which a field item should be created.
   * @param array $configuration
   *   The plugin configuration array, i.e. an array with the following keys:
   *   - field_definition: The field definition object, i.e. an instance of
   *     Drupal\Core\Field\FieldDefinitionInterface.
   *
   * @return \Drupal\Core\Field\FieldItemInterface
   *   The instantiated object.
   */
  public function createInstance($field_type, array $configuration = []) {
    $configuration['data_definition'] = $configuration['field_definition']->getItemDefinition();
    return $this->typedDataManager->createInstance("field_item:$field_type", $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldItemList(FieldableEntityInterface $entity, $field_name, $values = NULL) {
    // Leverage prototyping of the Typed Data API for fast instantiation.
    return $this->typedDataManager->getPropertyInstance($entity->getTypedData(), $field_name, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldItem(FieldItemListInterface $items, $index, $values = NULL) {
    // Leverage prototyping of the Typed Data API for fast instantiation.
    return $this->typedDataManager->getPropertyInstance($items, $index, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);
    if (!isset($definition['list_class'])) {
      $definition['list_class'] = '\Drupal\Core\Field\FieldItemList';
    }

    if ($definition['category'] instanceof TranslatableMarkup) {
      @trigger_error('Using a translatable string as a category for field type is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. See https://www.drupal.org/node/3364271', E_USER_DEPRECATED);
      $definition['category'] = static::DEFAULT_CATEGORY;
    }
    elseif (empty($definition['category'])) {
      // Ensure that every field type has a category.
      $definition['category'] = static::DEFAULT_CATEGORY;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultStorageSettings($type) {
    $plugin_definition = $this->getDefinition($type, FALSE);
    if (!empty($plugin_definition['class'])) {
      $plugin_class = DefaultFactory::getPluginClass($type, $plugin_definition);
      return $plugin_class::defaultStorageSettings();
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFieldSettings($type) {
    $plugin_definition = $this->getDefinition($type, FALSE);
    if (!empty($plugin_definition['class'])) {
      $plugin_class = DefaultFactory::getPluginClass($type, $plugin_definition);
      return $plugin_class::defaultFieldSettings();
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageSettingsSummary(FieldStorageDefinitionInterface $storage_definition): array {
    $plugin_definition = $this->getDefinition($storage_definition->getType(), FALSE);
    if (!empty($plugin_definition['class'])) {
      $plugin_class = DefaultFactory::getPluginClass($storage_definition->getType(), $plugin_definition);
      return $plugin_class::storageSettingsSummary($storage_definition);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSettingsSummary(FieldDefinitionInterface $field_definition): array {
    $plugin_definition = $this->getDefinition($field_definition->getType(), FALSE);
    if (!empty($plugin_definition['class'])) {
      $plugin_class = DefaultFactory::getPluginClass($field_definition->getType(), $plugin_definition);
      return $plugin_class::fieldSettingsSummary($field_definition);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedDefinitions(array $definitions = NULL, $label_key = 'label') {
    $grouped_categories = $this->getGroupedDefinitionsTrait($definitions, $label_key);
    $category_info = \Drupal::moduleHandler()->invokeAll('field_type_category_info');
    foreach ($grouped_categories as $group => $definitions) {
      if (!isset($category_info[$group]) && $group !== static::DEFAULT_CATEGORY) {
        assert(FALSE, "\"$group\" must be defined in hook_field_type_category_info().");
        $grouped_categories[static::DEFAULT_CATEGORY] += $definitions;
        unset($grouped_categories[$group]);
      }
    }

    return $grouped_categories;
  }

  /**
   * {@inheritdoc}
   */
  public function getUiDefinitions() {
    $definitions = $this->getDefinitions();

    // Filter out definitions that can not be configured in Field UI.
    $definitions = array_filter($definitions, function ($definition) {
      return empty($definition['no_ui']);
    });

    // Add preconfigured definitions.
    foreach ($definitions as $id => $definition) {
      if (is_subclass_of($definition['class'], '\Drupal\Core\Field\PreconfiguredFieldUiOptionsInterface')) {
        foreach ($this->getPreconfiguredOptions($definition['id']) as $key => $option) {
          $definitions["field_ui:$id:$key"] = array_intersect_key(
            $option,
            ['label' => 0, 'category' => 1, 'weight' => 1, 'description' => 0]
          ) + $definition;
        }
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreconfiguredOptions($field_type) {
    $options = [];
    $class = $this->getPluginClass($field_type);
    if (is_subclass_of($class, '\Drupal\Core\Field\PreconfiguredFieldUiOptionsInterface')) {
      $options = $class::getPreconfiguredOptions();
      $this->moduleHandler->alter('field_ui_preconfigured_options', $options, $field_type);
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginClass($type) {
    return $this->getDefinition($type)['class'];
  }

}
