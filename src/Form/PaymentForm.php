<?php

namespace Drupal\aaa_cybersource\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the payment entity edit forms.
 */
class PaymentForm extends ContentEntityForm {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, RendererInterface $renderer) {
    $this->entityRepository = $entity_repository;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->time = $time;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => $this->renderer->render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New payment %label has been created.', $message_arguments));
      $this->logger('aaa_cybersource')->info('Created new payment %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The payment %label has been updated.', $message_arguments));
      $this->logger('aaa_cybersource')->info('Updated new payment %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.payment.canonical', ['payment' => $entity->id()]);
  }

}
