<?php

namespace Drupal\aaa_cybersource\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\aaa_cybersource\PaymentInterface;

/**
 * Defines the payment entity class.
 *
 * Use this entity to track payment requests made through Cybersource. This
 * should hold tokenized data, payment metadata, and any significant information
 * regarding the type of transaction, all which are useful to the Drupal users.
 * It's possible that some of this information will be necessary to use to
 * generate reports and lookup historic transactions.
 *
 * @ContentEntityType(
 *   id = "payment",
 *   label = @Translation("Payment"),
 *   label_collection = @Translation("Payments"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\aaa_cybersource\PaymentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\aaa_cybersource\PaymentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\aaa_cybersource\Form\PaymentForm",
 *       "edit" = "Drupal\aaa_cybersource\Form\PaymentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "payment",
 *   admin_permission = "administer payment",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "code",
 *     "uuid" = "uuid",
 *     "code" = "code",
 *   },
 *   links = {
 *     "add-form" = "/admin/content/aaa/payment/add",
 *     "canonical" = "/payment/{payment}",
 *     "edit-form" = "/admin/content/aaa/payment/{payment}/edit",
 *     "delete-form" = "/admin/content/aaa/payment/{payment}/delete"
 *   },
 *   field_ui_base_route = "entity.payment.settings"
 * )
 */
class Payment extends ContentEntityBase implements PaymentInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecurring(): bool {
    return $this->get('recurring')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isRecurring(): bool {
    return $this->get('recurring')->value == 1;
  }

  /**
   * {@inheritdoc}
   */
  public function isActiveRecurring(): bool {
    if ($this->isRecurring() === FALSE) {
      return FALSE;
    }

    return $this->get('recurring_active')->value == 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the payment was created.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the payment was last edited.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Code'))
      ->setDescription(t('Merchant-generated order reference or tracking number. It is recommended that you send a unique value for each transaction so that you can perform meaningful searches for the transaction.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'region' => 'content',
        'weight' => -1,
        'settings' => [
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'title',
        'label' => 'above',
        'weight' => -1,
        'settings' => [
          'linked' => TRUE,
          'tag' => 'h1',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['submitted'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Transaction Submitted'))
      ->setDescription(t('The time that payment process was submitted to the processor.'))
      ->setDisplayOptions('view', [
        'type' => 'datetime_default',
        'label' => 'above',
        'weight' => 1,
        'settings' => [
          'timezone_override' => '',
          'format_type' => 'medium',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['payment_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment ID'))
      ->setDescription(t('The ID returned by Cybersource after a payment is processed.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 2,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['customer_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Customer ID Token'))
      ->setDescription(t('The tokenized customer idenfifier.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 5,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['authorized_amount'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Authorized Amount'))
      ->setDescription(t('The numerical amount of the payment transaction.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'number_decimal',
        'label' => 'above',
        'weight' => 3,
        'settings' => [
          'thousands_separator' => '',
          'decimal_separator' => '.',
          'scale' => 2,
          'prefix_suffix' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['currency'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Currency'))
      ->setDescription(t('Record of the currency used.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 4,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment Status'))
      ->setDescription(t('Status of the payment transaction.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 6,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recurring'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is recurring payment?'))
      ->setDescription(t('Recurring payments must be flagged and checked regularly for new charges.'))
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 7,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['environment'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Environment'))
      ->setDescription(t('The environment used by the form for the transaction. "Development" environment means that the form was sandboxed for testing. "Production" is a real transaction.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 13,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['submission'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Form Submission'))
      ->setDescription(t('Submission data provided by the user via donation webform.'))
      ->setSetting('target_type', 'webform_submission')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'label' => 'above',
        'weight' => 11,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(FALSE);

    $fields['recurring_active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active recurring payment.'))
      ->setDescription(t('Should this payment be billed again? If you wish to cancel additional recurring payments then set this value to "no."'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 1,
        'region' => 'content',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 8,
        'settings' => [
          'format' => 'yes-no',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['recurring_max'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Maximum number of recurring transactions.'))
      ->setDescription(t('A recurring payment will be processed until this number is met. 12 is the default value meaning 12 transactions including the first.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 1,
        'region' => 'content',
        'settings' => [
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'number_integer',
        'label' => 'above',
        'settings' => [
          'thousands_separator' => '',
          'prefix_suffix' => TRUE,
        ],
        'weight' => 9,
        'region' => 'content',
      ])
      ->setDefaultValue(12)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['recurring_next'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Next recurring payment date'))
      ->setDescription(t('The date time after which the next recurring payment will be charged.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 2,
        'region' => 'content',
        'settings' => [],
      ])
      ->setDisplayOptions('view', [
        'type' => 'datetime_default',
        'label' => 'above',
        'weight' => 10,
        'settings' => [
          'timezone_override' => '',
          'format_type' => 'medium',
        ],
      ])
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recurring_payments'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recurring Payments'))
      ->setDescription(t('The associated transactions with this recurring payment. This is a list of subsequent transactions after the first.'))
      ->setSetting('target_type', 'payment')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'label' => 'above',
        'weight' => 12,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setCardinality(-1)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(FALSE);

    $fields['secure_payment_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Secure Payment ID'))
      ->setDescription(t('The ID returned by Cybersource after a payment is secured.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 2,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['transaction_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Transaction Reference Number'))
      ->setDescription(t('The reference ID of the credit card settlement. Also refered to as the reconciliation ID.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 2,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['order_details'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Order details'))
      ->setDescription(t('Specific details regarding the order. Important for Gala tickets.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 2,
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['order_details_long'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Order details'))
      ->setDescription(t('Specific details regarding the order.'))
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'above',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);

    if (isset($values['uuid']) === FALSE || empty($values['uuid']) === TRUE) {
      $uuidFactory = \Drupal::service('uuid');
      $values['uuid'] = $uuidFactory->generate();
    }
  }

}
