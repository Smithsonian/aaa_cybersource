<?php

namespace Drupal\aaa_cybersource\Form;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Cybersource settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Contains information about all the forms in a keyed array.
   *
   * @var array
   */
  private $forms = [];

  /**
   * Maximum number of days a receipt is available.
   *
   * @var int
   */
  private $receiptAvailibilityMax = 30;

  /**
   * Minimum number of days a receipt is available.
   *
   * @var int
   */
  private $receiptAvailibilityMin = 1;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function __construct(
    $config_factory,
    $typed_config_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
  ) {
    $this->forms = [];

    // Include all webforms tagged Cybersource.
    $webform_ids = $this->entityTypeManager->getStorage('webform')
      ->getQuery()
      ->condition('template', TRUE, '<>')
      ->accessCheck(FALSE)
      ->execute();

    foreach ($webform_ids as $webform_id) {
      $webform = $this->entityRepository->getActive('webform', $webform_id);
      if (in_array('Cybersource', $webform->get('categories')) && $webform->getHandler('donation_webform_handler')) {
        $this->forms[$webform->get('uuid')] = [
          'description' => $this->t(':description', [':description' => $webform->get('description')]),
          'link' => [
            '#title' => $this->t('Edit Form'),
            '#type' => 'link',
            '#url' => Url::fromRoute('entity.webform.edit_form', ['webform' => $webform_id]),
          ],
          'title' => $this->t(':title', [':title' => $webform->label()]),
          'webform' => TRUE,
        ];
      }
    }

    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aaa_cybersource_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['aaa_cybersource.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $forms_ids = $this->getFormsIds();
    $config = $this->config('aaa_cybersource.settings');
    $site_config = $this->configFactory->get('system.site');

    $form['#attached']['library'][] = 'aaa_cybersource/settingsForm';

    // Global settings for all forms and fallback.
    $form['global'] = [
      '#type' => 'container',
    ];

    $form['global']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'Global settings',
    ];

    $form['global']['fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global settings take effect if no value is found in the form settings.'),
    ];

    $form['global']['fieldset']['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Environment'),
      '#options' => [
        'development' => $this->t('Development'),
        'production' => $this->t('Production'),
      ],
      '#default_value' => $config->get('global')['environment'] ?? 'development',
    ];

    $form['global']['fieldset']['auth'] = [
      '#type' => 'select',
      '#title' => $this->t('Authentication type'),
      '#options' => [
        // 'HTTP_SIGNATURE' => $this->t('HTTP Signature'),
        'JWT' => $this->t('JWT Certificate'),
      ],
      '#default_value' => $config->get('global')['auth'] ?? '',
    ];

    $form['global']['fieldset']['receipt_sender'] = [
      '#type' => 'email',
      '#title' => $this->t('Receipt sender'),
      '#description' => $this->t('Email address that sends receipts.'),
      '#default_value' => $config->get('global')['receipt_sender'] ?? $site_config->get('mail'),
    ];

    $form['global']['fieldset']['receipt_availibility'] = [
      '#type' => 'number',
      '#title' => $this->t('Days of receipt availibility'),
      '#description' => $this->t(
        'Minimum :min. Maximum :max. After this number of days the receipt link shown to the payer will no longer be valid. This is to protect the server from robots and scrapers which could theoretically attempt to generate false tokens to try and scrape data.',
        [
          ':min' => $this->receiptAvailibilityMin,
          ':max' => $this->receiptAvailibilityMax,
        ]
      ),
      '#min' => $this->receiptAvailibilityMin,
      '#max' => $this->receiptAvailibilityMax,
      '#default_value' => $config->get('global')['receipt_availibility'] ?? 7,
    ];

    $form['global']['fieldset']['logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug logging'),
      '#description' => $this->t('Logs users events from the payment form to drupal logs in order to follow user pathways.'),
      '#default_value' => $config->get('global')['logging'] ?? FALSE,
    ];

    $form['global']['development'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Development account settings.'),
    ];

    $this->buildAccountElements($form, 'development');

    $form['global']['production'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Production account settings.'),
    ];

    $this->buildAccountElements($form, 'production');

    // Individual forms settings.
    $form['forms'] = [
      '#type' => 'container',
    ];

    $form['forms']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'Forms settings',
    ];

    if (count($forms_ids) > 0) {
      $form['forms']['tabs'] = [
        '#type' => 'vertical_tabs',
        '#default_tab' => 'edit-' . $forms_ids[0],
      ];
    }

    $this->buildFormsTabs($form);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $receipt_availability = $form_state->getValue('receipt_availibility');
    if ($receipt_availability < $this->receiptAvailibilityMin || $receipt_availability > $this->receiptAvailibilityMax) {
      $form_state->setErrorByName('receipt_availibility', $this->t('Invalid number.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('aaa_cybersource.settings');
    $forms = $this->getFormsIds();

    foreach ($forms as $form_id) {
      $config->set($form_id . '_environment', $form_state->getValue($form_id . '_environment', ''));

      if ($this->forms[$form_id]['webform'] === TRUE) {
        $config->set($form_id . '_code', $form_state->getValue($form_id . '_code', 'AAA'));
      }
    }

    $global = $config->get('global') ?? [];
    $devFile = $this->getJwtFile($form_state, $global, 'development');
    $prodFile = $this->getJwtFile($form_state, $global, 'production');

    $config->set('global', [
      'auth' => $form_state->getValue('auth', $global['auth'] ?? ''),
      'development' => [
        'merchant_id' => $form_state->getValue('development_merchant_id', $global['development']['merchant_id'] ?? ''),
        'merchant_key' => $form_state->getValue('development_merchant_key', $global['development']['merchant_key'] ?? ''),
        'merchant_secret' => $form_state->getValue('development_merchant_secret', $global['development']['merchant_secret'] ?? ''),
        'certificate' => [
          'fid' => isset($devFile) ? $devFile->id() : NULL,
          'key_pass' => $form_state->getValue('development_key_pass', $global['development']['key_pass'] ?? ''),
        ],
      ],
      'environment' => $form_state->getValue('environment', $global['environment'] ?? ''),
      'production' => [
        'merchant_id' => $form_state->getValue('production_merchant_id', $global['production']['merchant_id'] ?? ''),
        'merchant_key' => $form_state->getValue('production_merchant_key', $global['production']['merchant_key'] ?? ''),
        'merchant_secret' => $form_state->getValue('production_merchant_secret', $global['production']['merchant_secret'] ?? ''),
        'certificate' => [
          'fid' => isset($prodFile) ? $prodFile->id() : NULL,
          'key_pass' => $form_state->getValue('production_key_pass', $global['production']['key_pass'] ?? ''),
        ],
      ],
      'receipt_availibility' => $form_state->getValue('receipt_availibility', $global['receipt_availibility'] ?? 7),
      'receipt_sender' => $form_state->getValue('receipt_sender', $global['receipt_sender'] ?? ''),
      'logging' => $form_state->getValue('logging', $global['logging'] ?? FALSE),
    ]);

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Find and return the jwt cert file given the environment.
   *
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   Form State.
   * @param array $global
   *   The global settings array.
   * @param string $environment
   *   Name of the environment.
   */
  private function getJwtFile(FormStateInterface &$form_state, array &$global, string $environment) {
    $formFile = $form_state->getValue($environment . '_certificate', 0);
    if (is_array($formFile) && isset($formFile[0])) {
      $file = $this->entityTypeManager->getStorage('file')->load($formFile[0]);
      $file->setPermanent();
      $file->save();
    }
    elseif (isset($global[$environment]) && is_null($global[$environment]['certificate']['fid']) === FALSE) {
      $file = $this->entityTypeManager->getStorage('file')->load($global[$environment]['certificate']['fid']);
    }
    else {
      $file = NULL;
    }

    return $file;
  }

  /**
   * The Cybersource environments.
   *
   * @return array
   *   Environment.s
   */
  private function getEnvironments() {
    return ['production', 'development'];
  }

  /**
   * Keys which refer to Cybersource forms on the site.
   *
   * @return array
   *   An array of form ids.
   */
  private function getFormsIds() {
    return array_keys($this->forms);
  }

  /**
   * Titles of the Cybersource forms.
   *
   * @param string $key
   *   Form key (id).
   *
   * @return string
   *   Title.
   */
  private function getFormTitle($key) {
    return $this->forms[$key]['title'];
  }

  /**
   * Helpful descriptions of the Cybersource forms.
   *
   * @param string $key
   *   Form key (id).
   *
   * @return string
   *   Description.
   */
  private function getFormDescription($key) {
    return $this->forms[$key]['description'];
  }

  /**
   * Build account fieldset elements.
   *
   * @param array &$form
   *   The form array.
   * @param string $environment
   *   Name of the environment.
   */
  private function buildAccountElements(array &$form, string $environment) {
    $config = $this->config('aaa_cybersource.settings');

    $form['global'][$environment][$environment . '_merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant ID'),
      '#default_value' => $config->get('global')[$environment]['merchant_id'] ?? '',
    ];

    $form['global'][$environment][$environment . '_merchant_key'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Merchant Key'),
      '#default_value' => $config->get('global')[$environment]['merchant_key'] ?? '',
    ];

    $form['global'][$environment][$environment . '_merchant_secret'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Merchant Shared Secret'),
      '#default_value' => $config->get('global')[$environment]['merchant_secret'] ?? '',
    ];

    $fileExists = $config->get('global')[$environment]['certificate']['fid'] ?? FALSE;
    $form['global'][$environment][$environment . '_certificate'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'private://cybersource',
      '#upload_validators' => [
        'file_validate_extensions' => ['pem p12'],
      ],
      '#default_value' => $fileExists === TRUE ? [$config->get('global')[$environment]['certificate']['fid']] : [],
      '#description' => $fileExists ? $this->t('OK. Certificate previously uploaded.') : $this->t('Warning. No certificate stored'),
      '#title' => $this->t('JWT Certificate'),
    ];

    $form['global'][$environment][$environment . '_key_pass'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Certificate password'),
      '#description' => $this->t('Password for the private key. This can be left empty if the keyphrase is identical to the Merchant ID.'),
      '#default_value' => $config->get('global')[$environment]['certificate']['key_pass'] ?? '',
    ];
  }

  /**
   * Build the elements for each form.
   *
   * @param array &$form
   *   The form array.
   */
  private function buildFormsTabs(array &$form) {
    $forms = $this->getFormsIds();

    if (count($forms) === 0) {
      $form['forms']['tabs'] = [
        '#type' => 'item',
        '#markup' => 'No Cybersource forms found.',
      ];
    }

    foreach ($forms as $form_id) {
      $form['forms']['tabs'][$form_id] = [
        '#type' => 'details',
        '#title' => $this->getFormTitle($form_id),
        '#group' => 'forms',
      ];

      $form['forms']['tabs'][$form_id]['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->getFormDescription($form_id),
      ];

      if ($this->formHasLink($form_id)) {
        $form['forms']['tabs'][$form_id]['link'] = $this->getFormLink($form_id);
      }

      if ($this->forms[$form_id]['webform'] === TRUE) {
        $key = $form_id . '_code';
        $form['forms']['tabs'][$form_id][$key] = [
          '#title' => $this->t('Code prefix'),
          '#type' => 'textfield',
          '#description' => $this->t('The site generates its own unique code for each transaction. By default this is "AAA" but if you prefer to vary it by the type of form you may change it in this setting.'),
          '#default_value' => $this->config('aaa_cybersource.settings')->get($key) ?? '',
          '#placeholder' => $this->t('AAA'),
          '#maxlength' => 16,
          '#attributes' => [
            'class' => ['form-element--type-text--uppercase'],
            'style' => ['text-transform: uppercase;'],
          ],
        ];
      }

      $key = $form_id . '_environment';
      $form['forms']['tabs'][$form_id][$key] = [
        '#type' => 'select',
        '#title' => $this->t('Select the environment.'),
        '#description' => $this->t('This setting switches the form environment where ever it is rendered sitewide. Use Development for testing purposes only.'),
        '#default_value' => $this->config('aaa_cybersource.settings')->get($key) ?? '',
        '#empty_value' => '',
        '#empty_option' => ' - Not set - ',
        '#options' => [
          'production' => $this->t('Production'),
          'development' => $this->t('Development'),
        ],
      ];
    }

  }

  /**
   * Get the link element.
   *
   * @param string $form_id
   *   The machine id of the form.
   *
   * @return array
   *   Form information.
   */
  private function getFormLink($form_id) {
    return $this->forms[$form_id]['link'];
  }

  /**
   * Check if link exists.
   *
   * @param string $form_id
   *   The machine id of the form.
   *
   * @return bool
   *   Check if the form value is set.
   */
  private function formHasLink($form_id) {
    return isset($this->forms[$form_id]['link']);
  }

}
