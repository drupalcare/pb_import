<?php

declare(strict_types=1);

namespace Drupal\pb_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Provides functionality to edit paragraphs.
 */
class ParagraphEditController extends ControllerBase {

  /**
   * Edit a paragraph entity.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity to edit.
   *
   * @return array
   *   A render array.
   */
  public function edit(ParagraphInterface $paragraph): array {
    return $this->entityFormBuilder()->getForm($paragraph, 'default');
  }

}
