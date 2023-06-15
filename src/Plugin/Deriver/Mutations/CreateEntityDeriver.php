<?php

namespace Drupal\graphql_mutation\Plugin\Deriver\Mutations;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\graphql\Utility\StringHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CreateEntityDeriver extends DeriverBase implements ContainerDeriverInterface {
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $basePluginId) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
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
        $inputType = $originalInputType = StringHelper::camelCase($entityTypeId, $bundleName, 'create', 'input');
        $inputType = StringHelper::nonNullType($inputType);

        $this->derivatives["$entityTypeId:$bundleName"] = [
          'name' => StringHelper::propCase('create', $entityTypeId, $bundleName),
          'arguments' => [
            'input' => $inputType,
          ],
          'input_type' => $originalInputType,
          'entity_type' => $entityTypeId,
          'entity_bundle' => $bundleName,
        ] + $basePluginDefinition;
      }
    }

    return parent::getDerivativeDefinitions($basePluginDefinition);
  }

}
