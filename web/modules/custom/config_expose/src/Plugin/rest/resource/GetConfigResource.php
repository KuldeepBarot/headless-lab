<?php

namespace Drupal\config_expose\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a custom endpoint which can be used to expose any config.
 * @RestResource(
 *   id = "config_get",
 *   label = @Translation("Custom resource to get details of a particular config"),
 *   uri_paths = {
 *     "canonical" = "/api/config-get/{conf_name}"
 *   }
 * )
 */
class GetConfigResource extends ResourceBase {
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
   * Returns details for the specified configuration.
   *
   * @param string $conf_name
   *   The name of the configuration.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the configuration detail.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
   *   When specified configuration is not allowed to be exposed by admin.
   */
  public function get($conf_name = NULL) {
    $config = $this->configFactory->get('config_expose.settings')
    ->get('selected_configs') ?? [];
    // When specified configuration is not allowed to be exposed by admin.
    if (!in_array($conf_name, $config)) {
      throw new BadRequestHttpException($this->t('The configuration (@config_name) is not yet exposed by admin.', ['@config_name' => $conf_name]));
    }

    $data[$conf_name] = $this->configFactory->get($conf_name)->getRawData();
    $response = new ResourceResponse($data);
    $response->getCacheableMetadata()->addCacheTags(['config:config_expose.settings']);
    return $response;
  }
}
