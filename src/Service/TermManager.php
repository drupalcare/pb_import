<?php

namespace Drupal\pb_import\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 *
 */
class TermManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected LoggerChannelInterface $logger;

  /**
   *
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('pb_import');
  }

  /**
   *
   */
  public function getOrCreateTerm($term_name, $vocabulary) {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $vocabularies = Vocabulary::loadMultiple();

    if (!isset($vocabularies[$vocabulary])) {
      $this->logger->error('Vocabulary @vocabulary does not exist.', ['@vocabulary' => $vocabulary]);
      return NULL;
    }

    $terms = $term_storage->loadByProperties(['vid' => $vocabulary]);

    foreach ($terms as $term) {
      if (strcasecmp($term->getName(), $term_name) === 0) {
        return $term;
      }
    }

    $term = Term::create([
      'vid' => $vocabulary,
      'name' => $term_name,
    ]);
    $term->save();
    $this->logger->info('New term created with ID: @term_id', ['@term_id' => $term->id()]);

    return $term;
  }

}
