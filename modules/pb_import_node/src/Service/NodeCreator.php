<?php

namespace Drupal\pb_import_node\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\Entity\Node;
use Drupal\pb_import\Service\FileManager;
use Drupal\pb_import\Service\TermManager;

/**
 * Service to create nodes from CSV data.
 */
class NodeCreator {

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
   * The term manager service.
   *
   * @var \Drupal\pb_import\Service\TermManager
   */
  protected TermManager $termManager;

  /**
   * The file manager service.
   *
   * @var \Drupal\pb_import\Service\FileManager
   */
  protected FileManager $fileManager;

  /**
   * NodeCreator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\pb_import\Service\TermManager $term_manager
   *   The term manager service.
   * @param \Drupal\pb_import\Service\FileManager $file_manager
   *   The file manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, TermManager $term_manager, FileManager $file_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import_node');
    $this->termManager = $term_manager;
    $this->fileManager = $file_manager;
  }

  /**
   * Creates a node.
   *
   * @param array $data
   *   The data for the node.
   * @param string $folder_name
   *   The folder name for image files.
   * @param string $content_type
   *   The content type.
   * @param string $vocabulary_name
   *   The vocabulary name.
   *
   * @return bool
   *   TRUE if the node was created successfully, FALSE otherwise.
   */
  public function create(array $data, $folder_name, $content_type, $vocabulary_name) {
    return $this->createNode($data, $folder_name, $content_type, $vocabulary_name);
  }

  /**
   * Creates a node entity.
   *
   * @param array $data
   *   The data for the node.
   * @param string $folder_name
   *   The folder name for image files.
   * @param string $content_type
   *   The content type.
   * @param string $vocabulary_name
   *   The vocabulary name.
   *
   * @return bool
   *   TRUE if the node was created successfully, FALSE otherwise.
   */
  public function createNode(array $data, $folder_name, $content_type, $vocabulary_name) {
    $image_name = $this->sanitizeData($data['csv_image_url']);
    $node_title = $this->sanitizeData($data['csv_node_title']);
    $csv_node_body = !empty($data['csv_node_body']) ? $this->sanitizeData($data['csv_node_body'], FALSE) : NULL;

    // Skip rows without a title or without either image or body.
    if (empty($node_title) || (empty($image_name) && empty($csv_node_body))) {
      $this->logger->warning('Skipped row due to missing title and either image or body: @row', ['@row' => implode(', ', $data)]);
      return FALSE;
    }

    $image_title = $this->sanitizeData($data['csv_image_title']);
    $image_alt = $this->sanitizeData($data['csv_image_alt']);
    $taxonomy_term_names = explode('|', $this->sanitizeData($data['csv_node_tag']));

    $terms = [];
    foreach ($taxonomy_term_names as $taxonomy_term_name) {
      $term = $this->termManager->getOrCreateTerm($taxonomy_term_name, $vocabulary_name);
      if ($term) {
        $terms[] = ['target_id' => $term->id()];
      }
      else {
        $this->logger->error('Failed to process term: @term', ['@term' => $taxonomy_term_name]);
        return FALSE;
      }
    }

    $file_id = NULL;
    if (!empty($image_name)) {
      $file_id = $this->fileManager->getFileId($folder_name, $image_name);
      if (!$file_id) {
        $this->logger->warning('File not found: @file', ['@file' => $folder_name . '/' . $image_name]);
        return FALSE;
      }
    }

    $node = Node::create([
      'type' => $content_type,
      'title' => $node_title,
      'pb_import_node_tag' => $terms,
    ]);

    if ($file_id) {
      $node->set('pb_import_node_image', [
        'target_id' => $file_id,
        'alt' => $image_alt,
        'title' => $image_title,
      ]);
    }

    if ($csv_node_body) {
      $node->set('pb_import_node_body', [
        'value' => $csv_node_body,
        'format' => 'full_html',
      ]);
    }

    $node->save();
    $this->logger->info('Node created with ID: @node_id', ['@node_id' => $node->id()]);

    return TRUE;
  }

  /**
   * Sanitizes input data.
   *
   * @param string $data
   *   The data to sanitize.
   * @param bool $escape_html
   *   (optional) Whether to escape HTML. Defaults to TRUE.
   *
   * @return string
   *   The sanitized data.
   */
  private function sanitizeData($data, $escape_html = TRUE) {
    return $escape_html ? Html::escape(Xss::filter($data)) : Xss::filter($data);
  }

}
