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
 *
 */
class NodeCreator {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected LoggerChannelInterface $logger;
  protected TermManager $termManager;
  protected FileManager $fileManager;

  /**
   *
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, TermManager $term_manager, FileManager $file_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import_node');
    $this->termManager = $term_manager;
    $this->fileManager = $file_manager;
  }

  /**
   *
   */
  public function create(array $data, $folder_name, $content_type, $vocabulary_name) {
    return $this->createNode($data, $folder_name, $content_type, $vocabulary_name);
  }

  /**
   *
   */
  public function createNode(array $data, $folder_name, $content_type, $vocabulary_name) {
    $image_name = $this->sanitizeData($data['csv_image_url']);
    $node_title = $this->sanitizeData($data['csv_node_title']);

    if (empty($image_name) || empty($node_title)) {
      $this->logger->warning('Skipped row due to empty csv_image_url or csv_node_title: @row', ['@row' => implode(', ', $data)]);
      return FALSE;
    }

    $image_title = $this->sanitizeData($data['csv_image_title']);
    $image_alt = $this->sanitizeData($data['csv_image_alt']);
    $taxonomy_term_names = explode('|', $this->sanitizeData($data['csv_node_tag']));
    $csv_node_body = !empty($data['csv_node_body']) ? $this->sanitizeData($data['csv_node_body'], FALSE) : NULL;

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

    $file_id = $this->fileManager->getFileId($folder_name, $image_name);
    if (!$file_id) {
      $this->logger->warning('File not found: @file', ['@file' => $folder_name . '/' . $image_name]);
      return FALSE;
    }

    $node = Node::create([
      'type' => $content_type,
      'title' => $node_title,
      'pb_import_node_image' => [
        'target_id' => $file_id,
        'alt' => $image_alt,
        'title' => $image_title,
      ],
      'pb_import_node_tag' => $terms,
    ]);

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
   *
   */
  private function sanitizeData($data, $escape_html = TRUE) {
    return $escape_html ? Html::escape(Xss::filter($data)) : Xss::filter($data);
  }

}
