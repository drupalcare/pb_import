<?php

declare(strict_types=1);

namespace Drupal\pb_import\Service;

/**
 * Provides utility functions for the pb_import module.
 */
class Utility {

  /**
   * Constructs a Utility object.
   *
   * @param string $sitePath
   *   The site path from container parameter.
   */
  public function __construct(
    private readonly string $sitePath,
  ) {}

  /**
   * Gets the site-specific files directory path.
   *
   * @return string
   *   The site-specific files directory path.
   */
  public function getSiteSpecificPath(): string {
    $site_name = basename($this->sitePath);
    return 'sites/' . $site_name . '/files';
  }

}
