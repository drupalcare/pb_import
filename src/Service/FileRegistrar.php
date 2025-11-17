<?php

declare(strict_types=1);

namespace Drupal\pb_import\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileStorageInterface;

/**
 * Service to register files in the Drupal file system.
 */
class FileRegistrar {

  use StringTranslationTrait;

  /**
   * Allowed file extensions.
   */
  private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   * Constructs a FileRegistrar object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\pb_import\Service\Utility $utility
   *   The utility service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
    private readonly MessengerInterface $messenger,
    private readonly Utility $utility,
    private readonly FileSystemInterface $fileSystem,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Registers files in the specified directory.
   *
   * @param string $relativeDirectory
   *   The relative directory path.
   */
  public function registerFiles(string $relativeDirectory): void {
    $site_specific_path = $this->utility->getSiteSpecificPath();
    $this->logger->info('Site-specific path: @path', ['@path' => $site_specific_path]);

    $source_directory = rtrim($site_specific_path, '/') . '/' . trim($relativeDirectory, '/');
    $real_source_directory = $this->fileSystem->realpath('public://' . trim($relativeDirectory, '/'));

    $this->logger->info('Constructed source directory: @dir', ['@dir' => $source_directory]);

    if ($real_source_directory === FALSE || !is_dir($real_source_directory)) {
      $this->logger->error('Directory does not exist: @dir', ['@dir' => $source_directory]);
      $this->messenger->addError($this->t('The specified directory does not exist.'));
      return;
    }

    $processed = 0;
    $skipped = 0;

    try {
      $file_iterator = new \FilesystemIterator(
        $real_source_directory,
        \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
      );

      /** @var \Drupal\file\FileStorageInterface $file_storage */
      $file_storage = $this->entityTypeManager->getStorage('file');
      assert($file_storage instanceof FileStorageInterface);

      foreach ($file_iterator as $file_info) {
        if (!$file_info->isFile()) {
          continue;
        }

        $file_name = $file_info->getFilename();
        $extension = strtolower((string) $file_info->getExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, TRUE)) {
          $this->logger->warning('Invalid file type: @file', ['@file' => $file_name]);
          $this->messenger->addWarning($this->t('Invalid file type: @file', [
            '@file' => $file_name,
          ]));
          $skipped++;
          continue;
        }

        $sanitized_file_name = $this->sanitizeFileName($file_name);
        $file_uri = 'public://' . trim($relativeDirectory, '/') . '/' . $sanitized_file_name;

        $existing_files = $file_storage->loadByProperties(['uri' => $file_uri]);

        if (empty($existing_files)) {
          try {
            /** @var \Drupal\file\FileInterface $file */
            $file = $file_storage->create([
              'uri' => $file_uri,
              'status' => FileInterface::STATUS_PERMANENT,
            ]);
            $file->save();

            $this->logger->info('Registered file: @file', ['@file' => $file_uri]);
            $processed++;
          }
          catch (\Exception $e) {
            $this->logger->error('Failed to register file: @file. Error: @error', [
              '@file' => $file_uri,
              '@error' => $e->getMessage(),
            ]);
            $this->messenger->addError($this->t('Failed to register file: @file', [
              '@file' => $sanitized_file_name,
            ]));
            $skipped++;
          }
        }
        else {
          $this->logger->info('File already exists: @file', ['@file' => $file_uri]);
          $skipped++;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error scanning directory: @error', [
        '@error' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('Error scanning directory.'));
      return;
    }

    $this->messenger->addMessage($this->t('Files processed: @processed, Files skipped: @skipped', [
      '@processed' => $processed,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Sanitizes the file name.
   *
   * @param string $fileName
   *   The file name to sanitize.
   *
   * @return string
   *   The sanitized file name.
   */
  private function sanitizeFileName(string $fileName): string {
    return Html::escape($fileName);
  }

}
