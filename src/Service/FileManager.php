<?php

namespace Drupal\pb_import\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;

/**
 *
 */
class FileManager {

  protected FileSystemInterface $fileSystem;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected LoggerChannelInterface $logger;
  protected Utility $utility;
  protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   *
   */
  public function __construct(FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, Utility $utility) {
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import');
    $this->utility = $utility;
  }

  /**
   *
   */
  public function getFileId($folder_name, $image_name) {
    $site_specific_path = $this->utility->getSiteSpecificPath();
    $file_path = $site_specific_path . '/' . trim($folder_name, '/') . '/' . $image_name;
    $real_path = $this->fileSystem->realpath($file_path);

    $this->logger->info('Attempting to locate file at: @file_path', ['@file_path' => $file_path]);
    $this->logger->info('Resolved real path: @real_path', ['@real_path' => $real_path]);

    if (file_exists($real_path)) {
      $file_uri = 'public://' . trim($folder_name, '/') . '/' . $image_name;
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
   *
   */
  public function validateFileExtension($file) {
    $extension = pathinfo($file->filename, PATHINFO_EXTENSION);
    return in_array(strtolower($extension), $this->allowedExtensions, TRUE);
  }

  /**
   *
   */
  public function processFile($file) {
    if (!$this->validateFileExtension($file)) {
      $this->logger->error('Invalid file extension: ' . $file->filename);
      return FALSE;
    }

    // Process the file if it has a valid extension
    // Your processing logic here...
    return TRUE;
  }

}
