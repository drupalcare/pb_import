<?php

namespace Drupal\pb_import\Service;

/**
 *
 */
class Utility {

  /**
   * Get the site-specific files directory path.
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
