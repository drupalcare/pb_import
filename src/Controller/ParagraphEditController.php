<?php

namespace Drupal\pb_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ParagraphEditController.
 *
 * Provides functionality to edit paragraphs.
 */
class ParagraphEditController extends ControllerBase {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new ParagraphEditController object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  public function __construct(Request $request) {
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Edit a paragraph entity.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity to edit.
   *
   * @return array
   *   A render array.
   */
  public function edit(Paragraph $paragraph) {
    $form = $this->entityFormBuilder()->getForm($paragraph, 'default');
    // Add a custom submit handler to redirect after saving.
    $form['actions']['submit']['#submit'][] = '::customSubmitHandler';
    return $form;
  }

  /**
   * Custom submit handler to redirect after saving.
   */
  public function customSubmitHandler(array &$form, FormStateInterface $form_state) {
    $paragraph = $form_state->getFormObject()->getEntity();
    $this->messenger()->addMessage($this->t('The paragraph %label has been saved.', ['%label' => $paragraph->label()]));
    $form_state->setRedirect('pb_import.paragraphs_list');
  }

}
