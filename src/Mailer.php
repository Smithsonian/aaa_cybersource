<?php

namespace Drupal\aaa_cybersource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Defines a mailer service.
 *
 * @package Drupal\aaa_cybersource
 */
class Mailer {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Logger Factory Interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new Mailer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\webform\WebformTokenManagerInterface $token_manager
   *   The webform token manager.
   * @param \Drupal\webform\LanguageManagerInterface $language_manager
   *   The Drupal language manager.
   * @param \Drupal\webform\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MailManagerInterface $mail_manager, WebformTokenManagerInterface $token_manager, LanguageManagerInterface $language_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->mailManager = $mail_manager;
    $this->tokenManager = $token_manager;
    $this->languageManager = $language_manager;
    $this->loggerFactory = $logger_factory->get('aaa_cybersource');
  }

  /**
   * Sends a mail message.
   *
   * @param string $key
   *   Email unique key.
   * @param string $to
   *   The email address to send the message to.
   * @param string $subject
   *   The subject of the message.
   * @param string $body
   *   The body of the message.
   */
  public function sendMail($key, $to, $subject, $body) {
    $global = $this->configFactory->get('aaa_cybersource.settings')->get('global');
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    if (isset($global['receipt_sender']) === TRUE) {
      $site_mail = $global['receipt_sender'];
    }
    else {
      $site_mail = $this->tokenManager->replace('[site:mail]', NULL, [], []);
    }

    $site_name = $this->tokenManager->replace('[site:name]', NULL, [], []);

    $result = $this->mailManager->mail(
      'aaa_cybersource',
      $key,
      $to,
      $current_langcode,
      [
        'from_mail' => $site_mail,
        'from_name' => $site_name,
        'subject' => $subject,
        'body' => $body,
        'bcc_mail' => 'AAAGiving@si.edu',
      ],
      $site_mail,
    );

    return $result;
  }

}
