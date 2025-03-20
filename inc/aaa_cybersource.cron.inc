<?php

/**
 * @file
 * Module cron and related functions.
 */

use Drupal\aaa_cybersource\Entity\Payment;

/**
 * Implements hook_cron().
 */
function aaa_cybersource_cron() {
  // Check for authorized pending review.
  $pending_review = \Drupal::entityQuery('payment')
    ->condition('status', 'AUTHORIZED_PENDING_REVIEW')
    ->accessCheck(FALSE)
    ->execute();

  $pending_review_payments = Payment::loadMultiple($pending_review);

  $cybersourceClient = \Drupal::service('aaa_cybersource.cybersource_client');

  foreach ($pending_review_payments as $payment) {
    $paymentId = $payment->get('payment_id')->value;
    $environment = $payment->get('environment')->value;

    $cybersourceClient->setEnvironment($environment);

    $transaction = $cybersourceClient->getTransaction($paymentId);

    if (get_class($transaction) !== 'CyberSource\ApiException' && isset($transaction[0])) {
      $links = $transaction[0]->getLinks()->getRelatedTransactions();

      if (!is_null($links)) {
        $href = $links[0]->getHref();

        preg_match('/\d+$/', $href, $related_transactions);

        if (count($related_transactions) > 0) {
          $secure = $cybersourceClient->getTransaction($related_transactions[0]);

          if (isset($secure[0])) {
            $status = $secure[0]->getApplicationInformation()->getStatus();

            if ($status === 'PENDING') {
              $payment->set('secure_payment_id', $secure[0]->getId());
              $payment->set('transaction_id', $secure[0]->getReconciliationId());
              $payment->set('status', $status);

              $payment->save();

              // Payment settled, send receipt.
              $to = $secure[0]->getOrderInformation()->getBillTo()->getEmail();
              $receiptService =\Drupal::service('aaa_cybersource.receipts');
              $receiptService->trySendReceipt($cybersourceClient, $payment, 'pending_review_settled_' . $payment->id(), $to);
            }
          }
        }
      }
    }
  }

  // Check for transmitted payments.
  $pending_transmission = \Drupal::entityQuery('payment')
    ->condition('status', ['AUTHORIZED', 'PENDING'], 'IN')
    ->accessCheck(FALSE)
    ->execute();

  $pending_transmission_payments = Payment::loadMultiple($pending_transmission);

  foreach ($pending_transmission_payments as $payment) {
    $paymentId = $payment->get('secure_payment_id')->value;

    if (is_null($paymentId)) {
      $paymentId = $payment->get('payment_id')->value;
    }

    $currentStatus = $payment->get('status')->value;

    $environment = $payment->get('environment')->value;

    $cybersourceClient->setEnvironment($environment);

    $transaction = $cybersourceClient->getTransaction($paymentId);

    if (!is_array($transaction) && get_class($transaction) === 'CyberSource\ApiException') {
      $message = $transaction->getResponseBody()->message;
      $response_message = $transaction->getMessage();
      \Drupal::logger('aaa_cybersource')->error($message . ' on ' . $response_message . ' ');

      continue;
    }
    elseif (!is_array($transaction)) {
      continue;
    }

    $status = $transaction[0]->getApplicationInformation()->getStatus();

    if (is_null($status) !== TRUE && $status !== $currentStatus) {
      $payment->set('status', $status);
      $payment->save();
    }
  }

  /**
   * Recurring payments
   */
  $recurring = \Drupal::service('aaa_cybersource.recurring_payment');

  // Check for missing PaymentID
  $payment_ids_missing_payment_id = $recurring->queryMissingPaymentId();

  $payments = Payment::loadMultiple($payment_ids_missing_payment_id);

  foreach ($payments as $payment) {
    $recurring->checkForPaymentId($payment);
  }

  // Check for payments which may be missing customer data (D8BUGS-412).
  $payment_ids_missing_customer = $recurring->queryMissingCustomer();

  $payments = Payment::loadMultiple($payment_ids_missing_customer);

  foreach ($payments as $payment) {
    $recurring->checkForCustomerId($payment);
  }

  // Now get all payments.
  $payment_ids = $recurring->query();

  $payments = Payment::loadMultiple($payment_ids);

  $count = 0;

  foreach ($payments as $payment) {
    $status = $recurring->buildRecurringPayment($payment);

    if ($status === TRUE) {
      $count++;
    }
  }

  if ($count > 0) {
    \Drupal::service('logger.factory')->get('aaa_cybersource')->info('@count recurring payments created', [
      '@count' => $count,
    ]);
  }
}
