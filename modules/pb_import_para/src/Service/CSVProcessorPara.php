<?php

namespace Drupal\pb_import_para\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\Entity\File;

/**
 * Service to process CSV files for importing paragraphs.
 */
class CSVProcessorPara {

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
   * The paragraph creator service.
   *
   * @var \Drupal\pb_import_para\Service\ParagraphCreator
   */
  protected ParagraphCreator $paragraphCreator;

  /**
   * Allowed file extensions.
   *
   * @var array
   */
  protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   * CSVProcessorPara constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\pb_import_para\Service\ParagraphCreator $paragraph_creator
   *   The paragraph creator service.
   */
  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory, ParagraphCreator $paragraph_creator) {
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('pb_import_para');
    $this->paragraphCreator = $paragraph_creator;
  }

  /**
   * Processes the CSV file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity.
   * @param string $parent_title
   *   The title for the parent paragraph.
   * @param string $parent_paragraph_type
   *   The type of the parent paragraph.
   * @param string $parent_entity_reference_field
   *   The entity reference field of the parent paragraph.
   * @param string $section_paragraph_type
   *   The type of the section paragraph.
   * @param string $section_entity_reference_field
   *   The entity reference field of the section paragraph.
   * @param string $target_bundle
   *   The target bundle.
   * @param string $vocabulary_name
   *   The vocabulary name.
   * @param string $image_folder_relative_path
   *   The folder name for image files.
   * @param string $section_title_field
   *   The title field of the section paragraph.
   *
   * @return array
   *   The result of the CSV processing.
   */
  public function process(File $file, string $parent_title, string $parent_paragraph_type, string $parent_entity_reference_field, string $section_paragraph_type, string $section_entity_reference_field, string $target_bundle, string $vocabulary_name, string $image_folder_relative_path, string $section_title_field) {
    $processed = 0;
    $skipped = 0;
    $skipped_rows = [];

    $path = $file->getFileUri();
    $real_path = $this->fileSystem->realpath($path);

    $this->logger->info('Processing CSV file at @path', ['@path' => $real_path]);

    $required_headers = $this->getRequiredHeaders();

    $rows = [];
    if (($handle = fopen($real_path, 'r')) !== FALSE) {
      $header = fgetcsv($handle, 1000, ',');
      // Trim whitespace from headers.
      $header = array_map('trim', $header);
      $this->logger->debug('CSV Header: @header', ['@header' => print_r($header, TRUE)]);

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
        $this->logger->debug('CSV Row @row_number: @data', [
          '@row_number' => $row_number,
          '@data' => print_r($data, TRUE),
        ]);
        $rows[] = array_combine($header, $data);
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

    $this->logger->info('Processed CSV rows: @rows', ['@rows' => print_r($rows, TRUE)]);

    $section_paragraphs = [];
    foreach ($rows as $row_number => $row) {
      $image_file = $row['csv_image_url'] ?? '';
      $body = $row['csv_target_body'] ?? '';

      // Check if at least one of the fields (Image, Body) is not empty.
      if (empty($image_file) && empty($body)) {
        $this->logger->warning('Skipping row @row_number due to empty Image and Body.', [
          '@row_number' => $row_number,
        ]);
        $skipped++;
        $skipped_rows[] = $row_number;
        continue;
      }

      // If image_url is empty, set image_alt and image_title to empty.
      if (empty($image_file)) {
        $image_alt = '';
        $image_title = '';
      }
      else {
        // Add a check to see if the image URL is readable.
        if (!$this->isImageUrlValid($image_file, $image_folder_relative_path)) {
          $this->logger->warning('Skipping row @row_number due to invalid image URL: @url. Body content is present but skipped.', [
            '@url' => $image_file,
            '@row_number' => $row_number,
          ]);
          $skipped++;
          $skipped_rows[] = $row_number;
          continue;
        }
        $image_alt = $row['csv_image_alt'] ?? '';
        $image_title = $row['csv_image_title'] ?? '';
      }

      $child_data = [
        'pb_target_image' => $image_file,
        'csv_image_alt' => $image_alt,
        'csv_image_title' => $image_title,
        'pb_target_tag' => $row['csv_target_tag'] ?? '',
        'pb_target_body' => $body,
      ];
      $child_paragraph = $this->paragraphCreator->createParagraph($child_data, $image_folder_relative_path, $target_bundle, $vocabulary_name);
      if ($child_paragraph) {
        $section_data = [
          // Always use the parent title from the form.
          'pb_content_title' => $parent_title ?? '',
          // Dynamically set the section title field based on
          // the paragraph type.
          $section_title_field => $row['csv_target_title'] ?? '',
          $section_entity_reference_field => [
            ['target_id' => $child_paragraph->id(), 'target_revision_id' => $child_paragraph->getRevisionId()],
          ],
        ];
        $section_paragraph = $this->paragraphCreator->createParagraph($section_data, $image_folder_relative_path, $section_paragraph_type, $vocabulary_name);
        if ($section_paragraph) {
          $section_paragraphs[] = [
            'target_id' => $section_paragraph->id(),
            'target_revision_id' => $section_paragraph->getRevisionId(),
          ];
          $processed++;
        }
        else {
          if (!empty($body)) {
            $this->logger->error('Failed to create section paragraph for row @row_number. Body content is present but the row is skipped.', [
              '@row_number' => $row_number,
            ]);
          }
          else {
            $this->logger->error('Failed to create section paragraph for row @row_number.', [
              '@row_number' => $row_number,
            ]);
          }
          $skipped++;
          $skipped_rows[] = $row_number;
        }
      }
      else {
        if (!empty($body)) {
          $this->logger->error('Failed to create child paragraph for row @row_number. Body content is present but the row is skipped.', [
            '@row_number' => $row_number,
          ]);
        }
        else {
          $this->logger->error('Failed to create child paragraph for row @row_number.', [
            '@row_number' => $row_number,
          ]);
        }
        $skipped++;
        $skipped_rows[] = $row_number;
      }
    }

    if (empty($section_paragraphs)) {
      $error_message = 'No section paragraphs were created. Cannot create parent paragraph. Make sure at least one row in the CSV has a valid image URL or body content.';
      $this->logger->error($error_message);
      return [
        'status' => 'error',
        'message' => $error_message,
        'processed' => $processed,
        'skipped' => $skipped,
        'skipped_rows' => $skipped_rows,
      ];
    }

    $parent_data = [
      'pb_content_title' => $parent_title ?? '',
      $parent_entity_reference_field => $section_paragraphs,
    ];

    $this->logger->info('Creating parent paragraph with data: @data', ['@data' => print_r($parent_data, TRUE)]);
    $parent_paragraph = $this->paragraphCreator->createParagraph($parent_data, $image_folder_relative_path, $parent_paragraph_type, $vocabulary_name, TRUE);

    if ($parent_paragraph) {
      $this->logger->info('Parent paragraph created successfully with ID: @parent_paragraph_id', ['@parent_paragraph_id' => $parent_paragraph->id()]);
    }
    else {
      $error_message = 'Failed to create the parent paragraph. Check if the section paragraphs were correctly created.';
      $this->logger->error($error_message);
      return [
        'status' => 'error',
        'message' => $error_message,
        'processed' => $processed,
        'skipped' => $skipped,
        'skipped_rows' => $skipped_rows,
      ];
    }

    $this->logger->info('Created parent paragraph "@parent_title" with @count sections.', [
      '@parent_title' => $parent_title,
      '@count' => count($section_paragraphs),
    ]);

    return [
      'status' => 'success',
      'message' => 'CSV processed successfully',
      'processed' => $processed,
      'skipped' => $skipped,
      'skipped_rows' => $skipped_rows,
    ];
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

  /**
   * Validates the file extension.
   *
   * @param string $file
   *   The file name.
   *
   * @return bool
   *   TRUE if the file extension is allowed, FALSE otherwise.
   */
  protected function validateFileExtension(string $file) {
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    return in_array(strtolower($extension), $this->allowedExtensions);
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
      'csv_image_alt',
      'csv_image_title',
      'csv_target_title',
      'csv_target_tag',
      'csv_target_body',
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

}
