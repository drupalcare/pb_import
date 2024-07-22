<?php

namespace Drupal\pb_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ParagraphsListController.
 *
 * Provides functionality to list paragraphs with pagination and filtering.
 */
class ParagraphsListController extends ControllerBase {

  /**
   * The pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a ParagraphsListController object.
   *
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(PagerManagerInterface $pager_manager, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->pagerManager = $pager_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pager.manager'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Displays a list of paragraphs with pagination and filtering.
   *
   * @return array
   *   A render array.
   */
  public function listParagraphs() {
    // Build the delete form, which now includes filtering functionality.
    $build = $this->formBuilder()->getForm('Drupal\pb_import\Form\ParagraphsFilterDeleteForm');

    return $build;
  }

}
