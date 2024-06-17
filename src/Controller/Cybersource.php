<?php

namespace Drupal\aaa_cybersource\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\aaa_cybersource\CybersourceClient;

use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Cybersource routes.
 */
class Cybersource extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HTTP client.
   *
   * @var ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The logger channel factory.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * Drupal filesystem.
   *
   * @var FileSystem
   */
  protected FileSystem $fileSystem;

  protected $auth;
  protected $requestHost;
  protected $merchantId;
  protected $merchantKey;
  protected $merchantSecretKey;
  protected $certificateDirectory;
  protected $certificateFile;
  protected $payload;
  protected $merchantConfiguration;
  protected $settings;
  protected $apiClient;
  protected $cybersourceClient;
  protected $entityRepository;
  protected $requestStack;

  /**
   * Cybersource controller constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param ClientInterface $http_client
   *   The HTTP client.
   * @param LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param FileSystem $file_system
   *   The Filesystem factory.
   * @param CybersourceClient $cybersource_client
   *   The Cybersource Client service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerChannelFactoryInterface $logger, FileSystem $file_system, CybersourceClient $cybersource_client, EntityRepository $entity_repository, RequestStack $request_stack) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
    $this->cybersourceClient = $cybersource_client;
    $this->entityRepository = $entity_repository;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Cybersource|static
  {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('file_system'),
      $container->get('aaa_cybersource.cybersource_client'),
      $container->get('entity.repository'),
      $container->get('request_stack'),
    );
  }

  /**
   * Return a flex token for front-end operations.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   The Flex Token.
   */
  public function getFlexToken($webform): JsonResponse {
    $settings = $this->configFactory->get('aaa_cybersource.settings');
    $request = $this->requestStack->getCurrentRequest();
    $host = 'https://' . $request->headers->get('host');

    if (isset($webform) === TRUE) {
      $environment = $settings->get($webform->get('uuid') . '_environment');
    }

    if (empty($environment) === TRUE) {
      $global = $settings->get('global');
      $environment = $global['environment'];
    }

    $this->cybersourceClient->setEnvironment($environment);
    $flexToken = $this->cybersourceClient->getFlexToken($host);

    return new JsonResponse($flexToken);
  }

}
