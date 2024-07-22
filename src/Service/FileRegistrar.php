<?php

namespace Drupal\pb_import\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;

/**
 * Service to register files in the Drupal file system.
 */
class FileRegistrar {
  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The utility service.
   *
   * @var \Drupal\pb_import\Service\Utility
   */
  protected Utility $utility;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Allowed file extensions.
   *
   * @var array
   */
  protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   * FileRegistrar constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\pb_import\Service\Utility $utility
   *   The utility service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger, Utility $utility, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import');
    $this->messenger = $messenger;
    $this->utility = $utility;
    $this->fileSystem = $file_system;
  }

  /**
   * Registers files in the specified directory.
   *
   * @param string $relative_directory
   *   The relative directory path.
   */
  public function registerFiles($relative_directory) {
    // Get the base path specific to the site.
    $site_specific_path = $this->utility->getSiteSpecificPath();
    $this->logger->info('Site-specific path: @path', ['@path' => $site_specific_path]);

    // Construct the full path by appending the user-provided relative
    // directory directly.
    $source_directory = rtrim($site_specific_path, '/') . '/' . trim($relative_directory, '/');
    $this->logger->info('Constructed source directory: @dir', ['@dir' => $source_directory]);

    // Check if the directory exists.
    if (!is_dir($source_directory)) {
      $this->logger->error('Directory does not exist: @dir', ['@dir' => $source_directory]);
      $this->messenger->addError($this->t('The specified directory does not exist.'));
      return;
    }

    // Scan the directory and process files.
    $file_paths = scandir($source_directory);
    $processed = 0;
    $skipped = 0;

    foreach ($file_paths as $file_name) {
      if ($file_name !== '.' && $file_name !== '..') {
        $full_path = $source_directory . '/' . $file_name;
        if (is_file($full_path)) {
          $extension = pathinfo($full_path, PATHINFO_EXTENSION);
          if (!in_array(strtolower($extension), $this->allowedExtensions)) {
            $this->logger->warning('Invalid file type: @file', ['@file' => $file_name]);
            $this->messenger->addWarning($this->t('Invalid file type: @file', ['@file' => $file_name]));
            $skipped++;
            continue;
          }

          // Sanitize the file name.
          $sanitized_file_name = $this->sanitizeFileName($file_name);
          $file_uri = 'public://' . trim($relative_directory, '/') . '/' . $sanitized_file_name;

          // Check if the file already exists in the Drupal file system.
          $files = $this->entityTypeManager
            ->getStorage('file')
            ->loadByProperties(['uri' => $file_uri]);

          if (empty($files)) {
            try {
              // Create a file entity.
              $file = File::create([
                'uri' => $file_uri,
                'status' => File::STATUS_PERMANENT,
              ]);
              $file->save();

              // Log the upload.
              $this->logger->info('Registered file: @file', ['@file' => $file_uri]);
              $processed++;
            }
            catch (\Exception $e) {
              $this->logger->error('Failed to register file: @file. Error: @error',
                ['@file' => $file_uri, '@error' => $e->getMessage()]);
              $this->messenger->addError($this->t('Failed to register file: @file',
                ['@file' => $sanitized_file_name]));
            }
          }
          else {
            // Check if the file actually exists in the directory.
            $existing_file = reset($files);
            if (!file_exists($full_path)) {
              // If the file does not exist, update the status or log a warning.
              $this->logger->warning('File @file was registered but does not exist on the filesystem.', ['@file' => $file_uri]);
            }
            else {
              $this->logger->info('File already exists: @file', ['@file' => $file_uri]);
            }
            $skipped++;
          }
        }
      }
    }

    $this->messenger->addMessage($this->t('Files processed: @processed, Files skipped: @skipped',
      ['@processed' => $processed, '@skipped' => $skipped]));
  }

  /**
   * Sanitizes the file name.
   *
   * @param string $file_name
   *   The file name to sanitize.
   *
   * @return string
   *   The sanitized file name.
   */
  private function sanitizeFileName($file_name) {
    // Use Drupal's HTML utility to escape and sanitize the file name.
    return Html::escape($file_name);
  }

}
