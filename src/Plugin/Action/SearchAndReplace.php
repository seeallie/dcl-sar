<?php

namespace Drupal\dcl_sar\Plugin\Action;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;
use Drupal\views_bulk_operations\Service\ViewsbulkOperationsViewData;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor;

use Drupal\dcl_sar\SearchAndReplaceTrait;

/**
 * Search and replace (sar) strings in entity fields.
 *
 * @Action(
 *   id = "views_bulk_sar",
 *   label = @Translation("Search and replace text"),
 *   type = "node",
 *   requirements = {
 *     "_permission" = "access content",
 *   },
 * )
 */
class SearchAndReplace extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, ViewsBulkOperationsPreconfigurationInterface {

  use SearchAndReplaceTrait;

  /**
   * Database conection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Array of text fields of a bundle.
   *
   * @var array
   */

  protected $textFieldsArray = [];

  /**
   * Object constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin Id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\views_bulk_operations\Service\ViewsbulkOperationsViewData $viewDataService
   *   The VBO view data service.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor $actionProcessor
   *   The VBO action processor.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info object.
   * @param \Drupal\Core\Database\Connection $database
   *   Database conection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsbulkOperationsViewData $viewDataService, ViewsBulkOperationsActionProcessor $actionProcessor, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->viewDataService = $viewDataService;
    $this->actionProcessor = $actionProcessor;
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('views_bulk_operations.data'),
      $container->get('views_bulk_operations.processor'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) {
    $form['get_bundles_from_results'] = [
      '#title' => $this->t('Get entity bundles from results'),
      '#type' => 'checkbox',
      '#default_value' => isset($values['get_bundles_from_results']) ? $values['get_bundles_from_results'] : TRUE,
      '#description' => $this->t('NOTE: If performance issues are observed when using "All results in this view" selector in case of large result sets, uncheck this and use a bundle filter (node type, taxonomy vocabulary etc.) on the view.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Get search and replacement string values from the session.
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('dcl_sar_collection');
    $replacementString = $store->get('replacement_string');
    $searchString = $store->get('search_string');

    // Can't clean up because the methond will be executed per content item;
    // by design, the temp store data will expire in a week
    // Clean up the key/value pair from the database
    // $store->delete('replacement_string');
    // $store->delete('search_string');.
    $type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $matchedTextArray = $this->getMatchedTextFields($entity, $searchString);

    foreach ($matchedTextArray as $field => $value) {
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storageDefinition */
      $storageDefinition = $entity->$field->getFieldDefinition()->getFieldStorageDefinition();
      $cardinality = $storageDefinition->getCardinality();
      // @todo Add logic for checking cardinality and ways to handle it when cardinality is not 1
      // For now, only deal with cardinality = 1
      if ($cardinality == 1) {
        $current_value = $entity->$field->getValue()['0']['value'];
      }
      $new_value = str_replace($searchString, $replacementString, $current_value);

      // Update the field value only, not to touch field format. 
      $entity->$field->value = $new_value;
    }

    // Update the database.
    $entity->save();

    \Drupal::logger('dcl_sar')->debug("Replace '@search' with '@replacement' for @bundle, @entity, @entityTitle", [
      '@search' => $searchString,
      '@replacement' => $replacementString,
      '@bundle' => $entity->bundle(),
      '@entity' => $entity->id(),
      '@entityTitle' => $entity->getTitle(),
    ]);

    $result = $this->t('Replaced a string in text fields');

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->access('update', $account, TRUE);
    return $return_as_object ? $access : $access->isAllowed();
  }

}
