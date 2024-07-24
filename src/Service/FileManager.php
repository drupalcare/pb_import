<?php

namespace Drupal\pb_import\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;

/**
 * Service to manage file operations.
 */
class FileManager {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

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
   * The utility service.
   *
   * @var \Drupal\pb_import\Service\Utility
   */
  protected Utility $utility;

  /**
   * Allowed file extensions.
   *
   * @var array
   */
  protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   * FileManager constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\pb_import\Service\Utility $utility
   *   The utility service.
   */
  public function __construct(FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, Utility $utility) {
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import');
    $this->utility = $utility;
  }

  /**
   * Retrieves the file ID by file path and name.
   *
   * @param string $image_folder_relative_path
   *   The folder name.
   * @param string $image_name
   *   The image name.
   *
   * @return int|null
   *   The file ID or NULL if the file does not exist.
   */
  public function getFileId($image_folder_relative_path, $image_name) {
    $site_specific_path = $this->utility->getSiteSpecificPath();
    $file_path = $site_specific_path . '/' . trim($image_folder_relative_path, '/') . '/' . $image_name;
    $real_path = $this->fileSystem->realpath($file_path);

    $this->logger->info('Attempting to locate file at: @file_path', ['@file_path' => $file_path]);
    $this->logger->info('Resolved real path: @real_path', ['@real_path' => $real_path]);

    if (file_exists($real_path)) {
      $file_uri = 'public://' . trim($image_folder_relative_path, '/') . '/' . $image_name;
      $this->logger->info('File URI: @file_uri', ['@file_uri' => $file_uri]);

      $existing_files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $file_uri]);
      if ($existing_files) {
        $existing_file = reset($existing_files);
        $this->logger->info('Existing file found with ID: @id', ['@id' => $existing_file->id()]);
        return $existing_file->id();
      }

      $file = File::create([
        'uri' => $file_uri,
        'status' => File::STATUS_PERMANENT,
      ]);
      $file->save();
      $this->logger->info('New file entity created with ID: @id', ['@id' => $file->id()]);
      return $file->id();
    }
    else {
      $this->logger->warning('File not found in Drupal file system: @file', ['@file' => $file_path]);
      return NULL;
    }
  }

  /**
   * Validates the file extension.
   *
   * @param object $file
   *   The file object.
   *
   * @return bool
   *   TRUE if the file extension is valid, FALSE otherwise.
   */
  public function validateFileExtension($file) {
    $extension = pathinfo($file->filename, PATHINFO_EXTENSION);
    return in_array(strtolower($extension), $this->allowedExtensions, TRUE);
  }

  /**
   * Processes the file.
   *
   * @param object $file
   *   The file object.
   *
   * @return bool
   *   TRUE if the file was processed successfully, FALSE otherwise.
   */
  public function processFile($file) {
    if (!$this->validateFileExtension($file)) {
      $this->logger->error('Invalid file extension: ' . $file->filename);
      return FALSE;
    }

    // Process the file if it has a valid extension.
    // Your processing logic here...
    return TRUE;
  }

}
