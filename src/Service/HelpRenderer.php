<?php

declare(strict_types=1);

namespace Drupal\pb_import\Service;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Renders a module's README.md as its help page.
 *
 * All three modules in this project had their own copy of this, differing only
 * in the module name — and the two sub-module copies pointed at a README that
 * does not exist, so those help pages rendered nothing at all. One
 * implementation, asked for a module name, cannot drift that way.
 */
final class HelpRenderer {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleExtensionList $moduleList,
    private readonly PluginManagerInterface $filterManager,
  ) {}

  /**
   * Renders the README of a module.
   *
   * @param string $module
   *   Module machine name.
   *
   * @return string|null
   *   Rendered help, or NULL when the module ships no README.
   */
  public function render(string $module): ?string {
    $path = $this->moduleList->getPath($module) . '/README.md';
    if (!is_file($path)) {
      return NULL;
    }

    $text = file_get_contents($path);
    if ($text === FALSE) {
      return NULL;
    }

    // Without the markdown filter the source is shown verbatim rather than
    // half-rendered. It is escaped: a README is a file on disk, but the help
    // page is served to a browser.
    if (!$this->moduleHandler->moduleExists('markdown')) {
      return '<pre>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    }

    $settings = $this->configFactory->get('markdown.settings')->getRawData();
    $filter = $this->filterManager->createInstance('markdown', ['settings' => $settings]);

    return (string) $filter->process($text, 'en');
  }

}
