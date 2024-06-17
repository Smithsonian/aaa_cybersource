<?php

namespace Drupal\aaa_cybersource\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;


/**
 * Returns responses for Cybersource routes.
 */
class CybersourceLogging extends ControllerBase {

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, RequestStack $request_stack) {
    $this->logger = $logger;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('request_stack')
    );
  }

  /**
   * Logs a message.
   */
  public function logMessage() {
    $request = $this->requestStack->getCurrentRequest();
    $requestContent = $request->getContent();

    if (strlen($requestContent) > 0 && strlen($requestContent) < 255) {
      $body = json_decode($requestContent, TRUE);

      $message = $body['message'];

      if (isset($_SESSION['aaa_cybersource']['id']) !== TRUE) {
        $_SESSION['aaa_cybersource']['id'] = bin2hex(random_bytes(16));
      }

      $this->logger->get('aaa_cybersource')->debug(t('":message" from session id :session', [':message' => $message, ':session' => $_SESSION['aaa_cybersource']['id']]),);
    }

    return new JsonResponse(NULL, 204);
  }

}
