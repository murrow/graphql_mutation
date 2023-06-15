<?php

namespace Drupal\graphql_mutation\Plugin\Deriver\InputTypes;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\graphql\Utility\StringHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityInputDeriver extends DeriverBase implements ContainerDeriverInterface {
  /**
   * The entity type manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity manager service.
   *
   * @var EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The entity field manager service.
   *
   * @var EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $basePluginId) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager
  ) {
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $type) {
      if (!($type instanceof ContentEntityTypeInterface)) {
        continue;
      }

      foreach ($this->entityTypeBundleInfo->getBundleInfo($entityTypeId) as $bundleName => $bundle) {

        $createFields = [];
        $updateFields = [];
        foreach ($this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundleName) as $fieldName => $field) {
          if ($field->isReadOnly() || $field->isComputed()) {
            continue;
          }

          $typeName = StringHelper::camelCase($entityTypeId, $fieldName, 'field', 'input');
          $fieldStorage = $field->getFieldStorageDefinition();
          $propertyDefinitions = $fieldStorage->getPropertyDefinitions();

          // Skip this field input type if it's a single value field.
          if (count($propertyDefinitions) == 1 && array_keys($propertyDefinitions)[0] === $fieldStorage->getMainPropertyName()) {
            $typeName = 'String';
          }

          $fieldKey = StringHelper::propCase($fieldName);
          $typeName = $field->getFieldStorageDefinition()->isMultiple() ? StringHelper::listType($typeName) : $typeName;
          $fieldDefinition = [
            'field_name' => $fieldName,
          ];

          $createFields[$fieldKey] = $fieldDefinition + [
            'type' => $field->isRequired() ? StringHelper::nonNullType($typeName) : $typeName,
          ];

          $updateFields[$fieldKey] = $fieldDefinition + [
            'type' => $typeName,
          ];
        }

        $this->derivatives["$entityTypeId:$bundleName:create"] = [
          'name' => StringHelper::camelCase($entityTypeId, $bundleName, 'create', 'input'),
          'fields' => $createFields,
          'entity_type' => $entityTypeId,
          'entity_bundle' => $bundleName,
          'data_type' => implode(':', ['entity', $entityTypeId, $bundleName]),
        ] + $basePluginDefinition;

        $this->derivatives["$entityTypeId:$bundleName:update"] = [
          'name' => StringHelper::camelCase($entityTypeId, $bundleName, 'update', 'input'),
          'fields' => $updateFields,
          'entity_type' => $entityTypeId,
          'entity_bundle' => $bundleName,
          'data_type' => implode(':', ['entity', $entityTypeId, $bundleName]),
        ] + $basePluginDefinition;
      }
    }

    return parent::getDerivativeDefinitions($basePluginDefinition);
  }

}
