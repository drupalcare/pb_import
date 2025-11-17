<?php

declare(strict_types=1);

namespace Drupal\pb_import\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileStorageInterface;

/**
 * Service to manage file operations.
 */
class FileManager {

  /**
   * Allowed file extensions.
   */
  private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   * Constructs a FileManager object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Retrieves the file ID by file path and name.
   *
   * @param string $imageFolderRelativePath
   *   The folder name.
   * @param string $imageName
   *   The image name.
   *
   * @return int|null
   *   The file ID or NULL if the file does not exist.
   */
  public function getFileId(string $imageFolderRelativePath, string $imageName): ?int {
    $file_uri = 'public://' . trim($imageFolderRelativePath, '/') . '/' . $imageName;
    $real_path = $this->fileSystem->realpath($file_uri);

    $this->logger->info('Attempting to locate file at URI: @file_uri', [
      '@file_uri' => $file_uri,
    ]);

    if ($real_path === FALSE || !file_exists($real_path)) {
      $this->logger->warning('File not found in Drupal file system: @file_uri', [
        '@file_uri' => $file_uri,
      ]);
      return NULL;
    }

    /** @var \Drupal\file\FileStorageInterface $file_storage */
    $file_storage = $this->entityTypeManager->getStorage('file');
    assert($file_storage instanceof FileStorageInterface);

    $existing_files = $file_storage->loadByProperties(['uri' => $file_uri]);

    if (!empty($existing_files)) {
      /** @var \Drupal\file\FileInterface $existing_file */
      $existing_file = reset($existing_files);
      $this->logger->info('Existing file found with ID: @id', [
        '@id' => $existing_file->id(),
      ]);
      return (int) $existing_file->id();
    }

    try {
      /** @var \Drupal\file\FileInterface $file */
      $file = $file_storage->create([
        'uri' => $file_uri,
        'status' => FileInterface::STATUS_PERMANENT,
      ]);
      $file->save();
      $this->logger->info('New file entity created with ID: @id', [
        '@id' => $file->id(),
      ]);
      return (int) $file->id();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create file entity for @uri: @message', [
        '@uri' => $file_uri,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Validates the file extension.
   *
   * @param string $filename
   *   The file name.
   *
   * @return bool
   *   TRUE if the file extension is valid, FALSE otherwise.
   */
  public function validateFileExtension(string $filename): bool {
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, self::ALLOWED_EXTENSIONS, TRUE);
  }

}
