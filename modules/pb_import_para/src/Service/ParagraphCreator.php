<?php

namespace Drupal\pb_import_para\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\pb_import\Service\FileManager;
use Drupal\pb_import\Service\TermManager;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Service to create paragraphs from CSV data.
 */
class ParagraphCreator {
  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The file manager service.
   *
   * @var \Drupal\pb_import\Service\FileManager
   */
  protected FileManager $fileManager;

  /**
   * The term manager service.
   *
   * @var \Drupal\pb_import\Service\TermManager
   */
  protected TermManager $termManager;

  /**
   * Constructs a ParagraphCreator object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\pb_import\Service\FileManager $fileManager
   *   The file manager service.
   * @param \Drupal\pb_import\Service\TermManager $termManager
   *   The term manager service.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, FileManager $fileManager, TermManager $termManager) {
    $this->logger = $logger_factory->get('pb_import_para');
    $this->fileManager = $fileManager;
    $this->termManager = $termManager;
  }

  /**
   * Creates a paragraph entity.
   *
   * @param array $data
   *   The data for the paragraph.
   * @param string $image_folder_relative_path
   *   The folder name for image files.
   * @param string $paragraph_type
   *   The paragraph type.
   * @param string $vocabulary_name
   *   The vocabulary name.
   * @param bool $is_parent
   *   (optional) Whether the paragraph is a parent paragraph Defaults to FALSE.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|bool
   *   The created paragraph entity, or FALSE on failure.
   */
  public function createParagraph(array $data, string $image_folder_relative_path, string $paragraph_type, string $vocabulary_name, bool $is_parent = FALSE) {
    $paragraph_data = ['type' => $paragraph_type];

    foreach ($data as $field_name => $value) {
      if (is_array($value)) {
        $paragraph_data[$field_name] = $value;
      }
      else {
        $paragraph_data[$field_name] = $this->sanitizeData($value);
      }
    }

    if (isset($data['pb_target_body'])) {
      $paragraph_data['pb_target_body'] = [
        'value' => $data['pb_target_body'],
        'format' => 'full_html',
      ];
    }

    if (!$is_parent && !empty($data['pb_target_image'])) {
      $this->logger->info('Attempting to get file ID for image: @image', ['@image' => $data['pb_target_image']]);
      $file_id = $this->fileManager->getFileId($image_folder_relative_path, $data['pb_target_image']);
      if ($file_id) {
        $paragraph_data['pb_target_image'] = [
          'target_id' => $file_id,
          'alt' => $this->sanitizeData($data['csv_image_alt'] ?? ''),
          'title' => $this->sanitizeData($data['csv_image_title'] ?? ''),
        ];
        $this->logger->info('File ID for image @image: @file_id',
          ['@image' => $data['pb_target_image'], '@file_id' => $file_id]);
      }
      else {
        $this->logger->warning('File not found: @file', ['@file' => $image_folder_relative_path . '/' . $data['pb_target_image']]);
      }
    }
    elseif (!$is_parent) {
      $this->logger->warning('Image field pb_target_image is empty or not set.');
    }

    if (!empty($data['pb_target_tag'])) {
      $terms = [];
      $term_names = explode('|', $this->sanitizeData($data['pb_target_tag']));
      foreach ($term_names as $term_name) {
        $term = $this->termManager->getOrCreateTerm($term_name, $vocabulary_name);
        if ($term) {
          $terms[] = ['target_id' => $term->id()];
        }
        else {
          $this->logger->warning('Failed to create or load term: @term', ['@term' => $term_name]);
        }
      }
      $paragraph_data['pb_target_tag'] = $terms;
    }

    try {
      $this->logger->info('Creating paragraph with data: @data', ['@data' => print_r($paragraph_data, TRUE)]);
      $paragraph = Paragraph::create($paragraph_data);
      $paragraph->save();
      $this->logger->info('Paragraph created with ID: @id', ['@id' => $paragraph->id()]);
      return $paragraph;
    }
    catch (\Exception $e) {
      $this->logger->error('Error creating paragraph: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Sanitizes input data.
   *
   * @param string|null $data
   *   The data to sanitize.
   *
   * @return string
   *   The sanitized data.
   */
  private function sanitizeData(?string $data): string {
    return $data !== NULL ? Html::escape(Xss::filter($data)) : '';
  }

  /**
   * Checks if a vocabulary exists.
   *
   * @param string $vocabulary_name
   *   The vocabulary name.
   *
   * @return bool
   *   TRUE if the vocabulary exists, FALSE otherwise.
   */
  private function vocabularyExists(string $vocabulary_name): bool {
    return Vocabulary::load($vocabulary_name) !== NULL;
  }

}
