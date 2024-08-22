<?php

namespace Drupal\aaa_cybersource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\aaa_cybersource\Entity\Payment;

/**
 * Defines a receipts service with helpful methods to build receipts.
 *
 * @package Drupal\aaa_cybersource
 */
class Receipts {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Logger Factory Interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerFactory;

  /**
   * Queue.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * Mailer.
   *
   * @var \Drupal\aaa_cybersource\Mailer
   */
  protected $mailer;

  /**
   * The cybersource client.
   *
   * @var \Drupal\aaa_cybersource\CybersourceClient
   */
  protected $cybersourceClient;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection;
   */
  protected $connection;

  /**
   * Constructs a new Receipt object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter functions.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   Logging in drupal.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DateFormatterInterface $date_formatter, LoggerChannelFactory $logger_factory, Connection $connection, QueueFactory $queue, Mailer $mailer, CybersourceClient $client) {
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->loggerFactory = $logger_factory->get('aaa_cybersource');
    $this->connection = $connection;
    $this->queue = $queue;
    $this->mailer = $mailer;
    $this->cybersourceClient = $client;
  }

  /**
   * Return receipt email subject line.
   *
   * @return string
   *   subject line.
   */
  public function getSubject() {
    return 'Thank you for your support of the Archives of American Art';
  }

  /**
   * Build receipt element.
   *
   * @param Drupal\aaa_cybersource\Entity\Payment $payment
   *   Payment entity.
   * @param array $transaction
   *   Transaction array from Cybersource payment processor.
   *
   * @return array
   *   Build array.
   */
  public function buildReceiptElements(Payment $payment, array $transaction) {
    $billTo = $transaction[0]->getOrderInformation()->getBillTo();
    $paymentInformation = $transaction[0]->getPaymentInformation();
    $card = $paymentInformation->getCard();
    $amountDetails = $transaction[0]->getOrderInformation()->getAmountDetails();
    $datetime = $transaction[0]->getSubmitTimeUTC();
    $donationType = strpos($payment->get('code')->value, 'GALA') > -1 ? 'GALA' : 'DONATION';

    // Build receipt.
    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => t('Receipt'),
    ];

    $build['meta'] = [
      '#type' => 'container',
    ];

    $build['meta']['date'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => 'Date: ' . $this->dateFormatter->format(strtotime($datetime), 'long'),
    ];

    $build['meta']['order_number'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t('Order Number: :number', [':number' => $payment->get('code')->value]),
      '#attributes' => [
        'style' => ['margin-bottom: 25px'],
      ],
    ];

    $build['break_1'] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
    ];

    $build['billing_information'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['billing-information'],
      ],
    ];

    $build['billing_information']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => t('Billing Information'),
    ];

    $build['billing_information']['address'] = [
      '#type' => 'html_tag',
      '#tag' => 'address',
    ];

    $build['billing_information']['address']['name'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t(':firstName :lastName', [
        ':firstName' => $billTo->getFirstName(),
        ':lastName' => $billTo->getLastName(),
      ]),
      '#attributes' => [
        'class' => ['name'],
      ],
    ];

    if (!empty($billTo->getCompany())) {
      $build['billing_information']['address']['company'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t(':value', [':value' => $billTo->getCompany()]),
        '#attributes' => [
          'class' => ['company'],
        ],
      ];
    }

    $build['billing_information']['address']['address'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t(':value', [':value' => $billTo->getAddress1()]),
      '#attributes' => [
        'class' => ['address'],
      ],
    ];

    if (!empty($billTo->getAddress2())) {
      $build['billing_information']['address']['address_2'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t(':value', [':value' => $billTo->getAddress2()]),
        '#attributes' => [
          'class' => ['address-2'],
        ],
      ];
    }

    $build['billing_information']['address']['locality'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t(':value', [':value' => $billTo->getLocality()]),
      '#attributes' => [
        'class' => ['locality'],
      ],
    ];

    $build['billing_information']['address']['area'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t(':value', [':value' => $billTo->getAdministrativeArea()]),
      '#attributes' => [
        'class' => ['area'],
      ],
    ];

    $build['billing_information']['address']['postal_code'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t(':value', [':value' => $billTo->getPostalCode()]),
      '#attributes' => [
        'class' => ['postal-code'],
      ],
    ];

    $build['billing_information']['address']['email'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t(':value', [':value' => $billTo->getEmail()]),
      '#attributes' => [
        'class' => ['email'],
      ],
    ];

    $build['billing_information']['address']['phone'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t(':value', [':value' => $billTo->getPhoneNumber()]),
      '#attributes' => [
        'class' => ['phone'],
      ],
    ];

    $build['payment_details'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['billing-information'],
      ],
    ];

    $build['payment_details']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => t('Payment Details'),
    ];

    $build['payment_details']['list'] = [
      '#type' => 'html_tag',
      '#tag' => 'dl',
    ];

    $build['payment_details']['list']['card_type_term'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => 'Card Type',
    ];

    $build['payment_details']['list']['card_type_value'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => t(':value', [':value' => aaa_cybersource_card_type_number_to_string($card->getType())]),
    ];

    $build['payment_details']['list']['card_number_term'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => 'Card Number',
    ];

    $build['payment_details']['list']['card_number_value'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => t('xxxxxxxxxxxx:value', [':value' => $card->getSuffix()]),
    ];

    $build['payment_details']['list']['card_expiration_term'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => 'Expiration',
    ];

    $build['payment_details']['list']['card_expiration_value'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => t(':month-:year', [
        ':month' => $card->getExpirationMonth(),
        ':year' => $card->getExpirationYear(),
      ]),
    ];

    if ($donationType === 'GALA' || !is_null($payment->get('order_details_long')->value)) {
      $build['order_details'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['order-details'],
        ],
      ];

      $build['order_details']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => t('Order Details'),
      ];

      $build['order_details']['content'] = [
        '#type' => 'container',
      ];

      $details = explode('; ', $payment->get('order_details_long')->value);

      foreach ($details as $i => $detail) {
        $build['order_details']['content'][$i] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $detail,
        ];

        if ($i === count($details) - 1) {
          $build['order_details']['content'][$i]['#attributes'] = [
            'style' => ['margin-bottom: 25px'],
          ];
        }
      }
    }

    $build['total'] = [
      '#type' => 'container',
    ];

    $build['total']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => t('Total Amount'),
    ];

    $amount = number_format($amountDetails->getTotalAmount(), 2);
    $build['total']['amount'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t('$:amount', [':amount' => $amount]),
      '#attributes' => [
        'style' => ['margin-bottom: 25px'],
      ],
    ];

    $build['break_2'] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
    ];

    $build['message'] = [
      '#type' => 'container',
    ];

    if ($donationType === 'GALA') {
      $markup = "<p>Thank you for your support of the 2024 Archives of American Art Gala. The estimated fair-market value of goods and services for table purchases is $14,755.00 for Gala Host, $4,895.00 for Campaign Champion, $4,535 for Benefactor, $3,785 for Patron, and $3,035 for Partner. Fair-market value for all ticket purchases is $410. If you have any questions about your gift, please contact us at <a href='mailto:AAAGala@si.edu'>AAAGala@si.edu</a> or (202) 633-7989.  We look forward to seeing you in New York City on Tuesday, October 29.</p>";
    }
    else {
      $markup = "<p>Thank you for supporting the Archives of American Art. By giving to the Archives, you are helping to ensure that significant records and untold stories documenting the history of art in America are collected, preserved, and shared with the world. Unless you opted out of receiving it, donors of at least $250 will receive the Archives of American Art Journal, with goods and services valued at $35. Gifts less than $250 or greater than $1,750 are fully tax deductible. Should you have any questions about your donation, you can reach us at <a>AAAGiving@si.edu</a> or (202) 633-7989.</p>";
    }

    $build['message']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => t('Thank You'),
    ];

    $build['message']['message'] = [
      '#markup' => $markup,
    ];

    return $build;
  }

  /**
   * Build receipt body for email.
   *
   * @param Drupal\aaa_cybersource\Entity\Payment $payment
   *   Payment entity.
   *
   * @return string
   *   Body text of the receipt.
   */
  /**
   * Build an email body. This is the email template.
   *
   * @param Drupal\aaa_cybersource\Entity\Payment $payment
   *   Payment entity.
   * @param [type]  $billTo
   * @param [type]  $paymentInformation
   * @param [type]  $amountDetails
   * @param [type]  $datetime
   *
   * @return string $body
   */
  public function buildReceiptEmailBody(Payment $payment, $billTo, $paymentInformation, $amountDetails, $datetime) {
    $card = $paymentInformation->getCard();
    $amount = number_format($amountDetails->getAuthorizedAmount(), 2);
    $donationType = strpos($payment->get('code')->value, 'GALA') > -1 ? 'GALA' : 'DONATION';

    $body = '';

    if ($donationType === 'DONATION') {
$body .= "
Thank you for supporting the Archives of American Art. By giving to the Archives, you are helping to ensure that significant records and untold stories documenting the history of art in America are collected, preserved, and shared with the world. Unless you opted out of receiving it, donors of at least $250 will receive the Archives of American Art Journal, with goods and services valued at $35. Gifts less than $250 or greater than $1,750 are fully tax deductible. Should you have any questions about your donation, you can reach us at AAAGiving@si.edu or (202) 633-7989.
";
    } else if ($donationType === 'GALA') {
$body .= "
Thank you for your support of the 2024 Archives of American Art Gala. The estimated fair-market value of goods and services for table purchases is $14,755.00 for Gala Host, $4,895.00 for Campaign Champion, $4,535 for Benefactor, $3,785 for Patron, and $3,035 for Partner. Fair-market value for all ticket purchases is $410. If you have any questions about your gift, please contact us at <a href='mailto:AAAGala@si.edu'>AAAGala@si.edu</a> or (202) 633-7989.  We look forward to seeing you in New York City on Tuesday, October 29.
";
    }

$body .= "
RECEIPT

Date: {$this->dateFormatter->format(strtotime($datetime), 'long')}
Order Number: {$payment->get('code')->value}

------------------------------------

BILLING INFORMATION

{$billTo->getFirstName()} {$billTo->getLastName()}";

    if (!empty($billTo->getCompany())) {
$body .= "
{$billTo->getCompany()}";
    }

$body .= "
{$billTo->getAddress1()}";

    if (!empty($billTo->getAddress2())) {
$body .= "
{$billTo->getAddress2()}";
    }

$body .= "
{$billTo->getLocality()}
{$billTo->getAdministrativeArea()}
{$billTo->getPostalCode()}
{$billTo->getEmail()}
{$billTo->getPhoneNumber()}

------------------------------------

PAYMENT DETAILS

Card Type {$this->cardTypeNumberToString($card->getType())}
Card Number xxxxxxxxxxxxx{$card->getSuffix()}
Expiration {$card->getExpirationMonth()}-{$card->getExpirationYear()}

------------------------------------
";

    if ($donationType === 'GALA' || !is_null($payment->get('order_details_long')->value)) {
      $details = explode('; ', $payment->get('order_details_long')->value);

$body .= "
ORDER DETAILS
";

      foreach ($details as $detail) {
$body .= "
{$detail}
";
      }
    }

$body .= "
TOTAL AMOUNT

$ {$amount}
";

    return $body;
  }

  /**
   * Given payment information and the receipient, send a receipt.
   *
   * @param CybersourceClient $client
   * @param string            $key
   * @param string            $to
   * @param Payment           $payment
   *
   * @return bool
   */
  protected function sendReceipt(Payment $payment, $transaction, $key = 'receipt', $to = NULL) {
    $billTo = $transaction[0]->getOrderInformation()->getBillTo();
    $paymentInformation = $transaction[0]->getPaymentInformation();
    $amountDetails = $transaction[0]->getOrderInformation()->getAmountDetails();
    $datetime = $transaction[0]->getSubmitTimeUTC();
    $body = $this->buildReceiptEmailBody($payment, $billTo, $paymentInformation, $amountDetails, $datetime);
    $subject = $this->getSubject();

    if (is_null($to) === TRUE) {
      $to = $billTo->getEmail();
    }

    $result = $this->mailer->sendMail($key, $to, $subject, $body);

    if ($result['send'] === TRUE) {
      $context = [
        '@code' => $payment->get('code')->value ?? 'unknown code',
      ];

      // Attempt to link to the payment.
      try {
        $link = $payment->toLink('View', 'canonical')->toString();

        $context['link'] = $link;
      }
      catch (\Exception $e) {
        $this->loggerFactory->error('Error generating link: @error', ['@error' => $e->getMessage()]);
      }

      $this->loggerFactory->info('Payment code @code receipt emailed.', $context);
    }

    return $result['send'];
  }

  /**
   * Attempt to send a receipt.
   *
   * If the transaction isn't available send a copy of it to the queue.
   *
   * @param CybersourceClient $client
   * @param Payment $payment
   * @param string $key
   * @param string|null $to
   *
   * @return bool
   *   Returns send status.
   */
  public function trySendReceipt(CybersourceClient $client, Payment $payment, $key = 'receipt', $to = NULL) {
    $paymentId = $payment->get('payment_id')->value;
    $transaction = $client->getTransaction($paymentId);

    // If there is an exception, queue this to send next cron.
    if (is_array($transaction) === FALSE && get_class($transaction) === 'CyberSource\ApiException') {
      $environment = $client->getEnvironment();
      $pid = $payment->id();

      if ($this->isPaymentInQueue($pid) !== TRUE) {
        $this->sendToQueue($environment, $pid, $key, $to);
      }

      return FALSE;
    }

    $sent = $this->sendReceipt($payment, $transaction, $key, $to);

    // If sending failed, queue this for later.
    if ($sent !== TRUE) {
      $environment = $client->getEnvironment();
      $pid = $payment->id();

      if ($this->isPaymentInQueue($pid) !== TRUE) {
        $this->sendToQueue($environment, $pid, $key, $to);
      }
    }

    return $sent;
  }

  /**
   * Send receipt email data to the queue.
   *
   * @param string $environment
   * @param int|string $payment_entity_id
   * @param string $to
   * @param string $key
   */
  protected function sendToQueue($environment, $payment_entity_id, $key, $to) {
    $queue = $this->queue->get('receipt_queue');

    $queueItem = new \stdClass();
    $queueItem->environment = $environment;
    $queueItem->pid = (int) $payment_entity_id;
    $queueItem->key = $key;
    $queueItem->to = $to;

    $queue->createItem($queueItem);
  }

  /**
   * Check queue for existing record.
   *
   * @param integer $pid
   *
   * @return bool
   */
  protected function isPaymentInQueue(int $pid) {
    $queued = $this->connection->select('queue', 'q', [])
      ->condition('q.name', 'receipt_queue', '=')
      ->fields('q', ['name', 'data', 'item_id'])
      ->execute();

    foreach ($queued as $item) {
      $data = unserialize($item->data);

      if (is_numeric($data->pid) === TRUE && $pid == $data->pid) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Takes code and returns human readable name.
   *
   * @param string $code
   *   The card type symbol.
   *
   * @return string
   *   human readable card type.
   */
  protected function cardTypeNumberToString($code) {
    return aaa_cybersource_card_type_number_to_string($code);
  }

}
