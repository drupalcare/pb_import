<?php

declare(strict_types=1);

namespace Drupal\pb_import\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides functionality to list paragraphs with pagination and filtering.
 */
class ParagraphsListController extends ControllerBase {

  /**
   * Displays a list of paragraphs with pagination and filtering.
   *
   * @return array
   *   A render array.
   */
  public function listParagraphs(): array {
    return $this->formBuilder()->getForm('Drupal\pb_import\Form\ParagraphsFilterDeleteForm');
  }

}
