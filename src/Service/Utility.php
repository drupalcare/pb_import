<?php

namespace Drupal\pb_import\Service;

/**
 * Provides utility functions for the pb_import module.
 */
class Utility {

  /**
   * Gets the site-specific files directory path.
   *
   * @return string
   *   The site-specific files directory path.
   */
  public function getSiteSpecificPath() {
    $site_path = \Drupal::getContainer()->getParameter('site.path');
    $site_name = basename($site_path);
    return 'sites/' . $site_name . '/files';
  }

}
