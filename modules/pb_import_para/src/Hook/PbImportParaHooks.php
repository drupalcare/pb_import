<?php

declare(strict_types=1);

namespace Drupal\pb_import_para\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\pb_import\Service\HelpRenderer;

/**
 * Hook implementations for PB Import Paragraph.
 */
final class PbImportParaHooks {

  public function __construct(
    private readonly HelpRenderer $help,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    return $route_name === 'help.page.pb_import_para'
      ? $this->help->render('pb_import_para')
      : NULL;
  }

}
