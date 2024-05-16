<?php

namespace Drupal\config_expose\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a custom endpoint to fetch a list of allowed configs.
 * @RestResource(
 *   id = "config_list",
 *   label = @Translation("List of allowed configs"),
 *   uri_paths = {
 *     "canonical" = "/api/config-list"
 *   }
 * )
 */
class GetConfigListResource extends ResourceBase {
  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a GetConfigResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer_formats, LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('config.factory')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns the list of allowed configurations.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the list of allowed configurations.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   When admin has not selected any configs that can be exposed.
   */
  public function get() {
    $allowed_configs = $this->configFactory->get('config_expose.settings')
      ->get('selected_configs') ?? [];

    // When admin has not selected any configs that can be exposed.
    if (empty($allowed_configs)) {
      throw new BadRequestHttpException('Admin has not allowed any configs to be exposed.');
    }
    else {
      $response = new ResourceResponse($allowed_configs);
      $response->getCacheableMetadata()->addCacheTags(['config:config_expose.settings']);
      return $response;
    }
  }
}
