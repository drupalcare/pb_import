<?php

namespace Drupal\pb_import_node\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;

/**
 * Service to process CSV files for importing nodes.
 */
class CSVProcessorNode {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The node creator service.
   *
   * @var \Drupal\pb_import_node\Service\NodeCreator
   */
  protected $creator;

  /**
   * CSVProcessorNode constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\pb_import_node\Service\NodeCreator $creator
   *   The node creator service.
   */
  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory, $creator) {
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('pb_import_node');
    $this->creator = $creator;
  }

  /**
   * Processes the CSV file.
   *
   * @param \Drupal\file\Entity\File $csv_file
   *   The CSV file entity.
   * @param string $folder_name
   *   The folder name for image files.
   * @param string $content_type
   *   The content type.
   * @param string $vocabulary_name
   *   The vocabulary name.
   *
   * @return array
   *   The result of the CSV processing.
   */
  public function process(File $csv_file, $folder_name, $content_type, $vocabulary_name) {
    $processed = 0;
    $skipped = 0;
    $skipped_rows = [];

    $required_headers = $this->getRequiredHeaders();

    $path = $csv_file->getFileUri();
    $real_path = $this->fileSystem->realpath($path);

    $this->logger->info('Processing CSV file at @path', ['@path' => $real_path]);

    if (($handle = fopen($real_path, 'r')) !== FALSE) {
      $header = fgetcsv($handle, 1000, ',');
      // Trim whitespace from headers.
      $header = array_map('trim', $header);

      // Validate headers.
      $validation_result = $this->validateHeaders($header, $required_headers);
      if ($validation_result['status'] === 'error') {
        return $validation_result;
      }

      // Initialize row number starting from the first data row.
      $row_number = 1;
      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row_number++;

        // Check for row length.
        if (count($data) != count($header)) {
          $this->logger->warning('Skipping malformed row @row_number: @row', [
            '@row_number' => $row_number,
            '@row' => print_r($data, TRUE),
          ]);
          $skipped++;
          $skipped_rows[] = $row_number;
          continue;
        }

        // Trim whitespace from values and decode HTML entities.
        $data = array_map(function ($item) {
          return html_entity_decode(trim($item));
        }, $data);
        // Combine header and data.
        $data = array_combine($header, $data);

        // If image_url is empty, set image_alt and image_title to empty.
        if (empty($data['csv_image_url'])) {
          $data['csv_image_alt'] = '';
          $data['csv_image_title'] = '';
        }
        else {
          // Add a check to see if the image URL is readable.
          if (!$this->isImageUrlValid($data['csv_image_url'], $folder_name)) {
            if (!empty($data['csv_node_body'])) {
              $this->logger->error('Skipping row @row_number due to invalid image URL: @url. The body content is present but the row is skipped.', [
                '@url' => $data['csv_image_url'],
                '@row_number' => $row_number,
              ]);
            }
            else {
              $this->logger->error('Failed to read image URL: @url at row @row_number', [
                '@url' => $data['csv_image_url'],
                '@row_number' => $row_number,
              ]);
            }
            $skipped++;
            $skipped_rows[] = $row_number;
            continue;
          }
        }

        // Simplified condition: process row if it has a title
        // and either image or body.
        if (!empty($data['csv_node_title']) && (!empty($data['csv_image_url']) || !empty($data['csv_node_body']))) {
          try {
            $result = $this->creator->create($data, $folder_name, $content_type, $vocabulary_name);
            if ($result) {
              $processed++;
            }
            else {
              if (!empty($data['csv_node_body'])) {
                $this->logger->warning('Skipping row @row_number due to failed creation, even though body content is present: @row', [
                  '@row_number' => $row_number,
                  '@row' => print_r($data, TRUE),
                ]);
              }
              else {
                $this->logger->warning('Skipping row @row_number due to failed creation: @row', [
                  '@row_number' => $row_number,
                  '@row' => print_r($data, TRUE),
                ]);
              }
              $skipped++;
              $skipped_rows[] = $row_number;
            }
          }
          catch (\Exception $e) {
            $this->logger->error('Error processing row @row_number: @row. Error: @error', [
              '@row_number' => $row_number,
              '@row' => implode(', ', $data),
              '@error' => $e->getMessage(),
            ]);
            $skipped++;
            $skipped_rows[] = $row_number;
          }
        }
        else {
          if (!empty($data['csv_image_url'])) {
            $this->logger->warning('Skipping row @row_number due to missing title or body field error, even though image URL is valid: @row', [
              '@row_number' => $row_number,
              '@row' => print_r($data, TRUE),
            ]);
          }
          else {
            $this->logger->warning('Skipping row @row_number due to missing title or both image and body: @row', [
              '@row_number' => $row_number,
              '@row' => print_r($data, TRUE),
            ]);
          }
          $skipped++;
          $skipped_rows[] = $row_number;
        }
      }
      fclose($handle);
    }
    else {
      $error_message = 'Failed to open CSV file at @path';
      $this->logger->error($error_message, ['@path' => $real_path]);
      return [
        'status' => 'error',
        'message' => $error_message,
        'processed' => $processed,
        'skipped' => $skipped,
        'skipped_rows' => $skipped_rows,
      ];
    }

    $status_message = "Nodes processed: $processed, Nodes skipped: $skipped";
    return [
      'status' => 'success',
      'message' => $status_message,
      'processed' => $processed,
      'skipped' => $skipped,
      'skipped_rows' => $skipped_rows,
    ];
  }

  /**
   * Returns the required headers for the CSV file.
   *
   * @return array
   *   The required headers.
   */
  protected function getRequiredHeaders() {
    return [
      'csv_image_url',
      'csv_node_title',
      'csv_image_alt',
      'csv_image_title',
      'csv_node_tag',
      'csv_node_body',
    ];
  }

  /**
   * Validates the CSV headers.
   *
   * @param array $header
   *   The CSV headers.
   * @param array $required_headers
   *   The required headers.
   *
   * @return array
   *   The validation result.
   */
  protected function validateHeaders(array $header, array $required_headers) {
    $missing_headers = array_diff($required_headers, $header);
    $extra_headers = array_diff($header, $required_headers);

    if (!empty($missing_headers) || !empty($extra_headers)) {
      $error_message = 'CSV header validation failed. ';
      if (!empty($missing_headers)) {
        $error_message .= 'Missing headers: ' . implode(', ', $missing_headers) . '. ';
      }
      if (!empty($extra_headers)) {
        $error_message .= 'Extra headers: ' . implode(', ', $extra_headers) . '.';
      }
      $this->logger->error($error_message);
      return ['status' => 'error', 'message' => $error_message];
    }

    return ['status' => 'success'];
  }

  /**
   * Checks if the image URL points to a valid file in the specified folder.
   *
   * @param string $url
   *   The image URL (file name) to check.
   * @param string $folder
   *   The folder where the image files are stored.
   *
   * @return bool
   *   TRUE if the image file exists in the folder, FALSE otherwise.
   */
  protected function isImageUrlValid(string $url, string $folder) : bool {
    // Ensure the folder path ends with a slash.
    $folder = rtrim($folder, '/') . '/';
    $file_path = $this->fileSystem->realpath('public://' . $folder . $url);

    if (file_exists($file_path)) {
      return TRUE;
    }
    else {
      $this->logger->warning('File does not exist: @file_path', ['@file_path' => $file_path]);
      return FALSE;
    }
  }

}
