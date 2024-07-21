<?php

namespace Drupal\pb_import_node\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\file\Entity\File;
use Drupal\pb_import\Service\FileManager;
use Drupal\pb_import\Service\TermManager;
use Drupal\pb_import\Service\Utility;
use Drupal\pb_import_node\Service\CSVProcessorNode;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class PBImportNodeForm extends FormBase {

  protected CSVProcessorNode $csvProcessorNode;
  protected FileManager $fileManager;
  protected TermManager $termManager;
  protected Utility $utility;
  protected LoggerChannelInterface $logger;
  protected $messenger;

  /**
   *
   */
  public function __construct(CSVProcessorNode $csvProcessorNode, FileManager $fileManager, TermManager $termManager, Utility $utility, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger) {
    $this->csvProcessorNode = $csvProcessorNode;
    $this->fileManager = $fileManager;
    $this->termManager = $termManager;
    $this->utility = $utility;
    $this->logger = $logger_factory->get('pb_import_node');
    $this->messenger = $messenger;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pb_import_node.csv_processor.node'),
      $container->get('pb_import.file_manager'),
      $container->get('pb_import.term_manager'),
      $container->get('pb_import.utility'),
      $container->get('logger.factory'),
      $container->get('messenger')
    );
  }

  /**
   *
   */
  public function getFormId() {
    return 'pb_import_node_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload the CSV file. The CSV file must be constructed in this way and in this order: csv_image_url, csv_node_title, csv_image_alt, csv_image_title, csv_node_tag, csv_node_body.'),
      '#required' => TRUE,
    ];

    $form['image_folder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Folder'),
      '#description' => $this->t('Enter the relative path to the image folder inside files, e.g., gallery/austin (it will be @site_path/gallery/austin)', ['@site_path' => $this->utility->getSiteSpecificPath()]),
      '#required' => TRUE,
    ];

    $form['content_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Type'),
      '#description' => $this->t('Enter the content type to override the default.'),
      '#default_value' => 'pb_import_node',
      '#required' => TRUE,
    ];

    $form['vocabulary_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vocabulary Name'),
      '#description' => $this->t('Enter the vocabulary name to override the default.'),
      '#default_value' => 'pb_import_node',
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['csv']];
    if ($file = file_save_upload('csv_file', $validators, FALSE, 0, FileSystemInterface::EXISTS_RENAME)) {
      $form_state->setValue('csv_file', $file);
    }
    else {
      $form_state->setErrorByName('csv_file', $this->t('The uploaded file is not a valid CSV file.'));
    }
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $csv_file = $form_state->getValue('csv_file');
    $folder_name = $form_state->getValue('image_folder');
    $content_type = $form_state->getValue('content_type');
    $vocabulary_name = $form_state->getValue('vocabulary_name');

    if ($csv_file instanceof File) {
      $this->logger->info('Starting CSV processing for folder: @folder_name with content type: @content_type and vocabulary name: @vocabulary_name', [
        '@folder_name' => $folder_name,
        '@content_type' => $content_type,
        '@vocabulary_name' => $vocabulary_name,
      ]);

      // Pass the additional arguments.
      $result = $this->csvProcessorNode->process($csv_file, $folder_name, $content_type, $vocabulary_name);

      if ($result['status'] == 'error') {
        $this->logger->error('CSV processing failed with error: @message', ['@message' => $result['message']]);
        $this->messenger->addError($result['message']);
      }
      else {
        $processed = $result['processed'] ?? 0;
        $skipped = $result['skipped'] ?? 0;

        $this->logger->info('CSV file processed successfully. Nodes processed: @processed, Nodes skipped: @skipped', [
          '@processed' => $processed,
          '@skipped' => $skipped,
        ]);
        $this->messenger->addMessage($this->t('Nodes processed: @processed, Nodes skipped: @skipped', [
          '@processed' => $processed,
          '@skipped' => $skipped,
        ]));
      }
    }
    else {
      $this->logger->error('The CSV file could not be loaded as a file entity.');
      $this->messenger->addError($this->t('There was an error loading the CSV file.'));
    }
  }

}
