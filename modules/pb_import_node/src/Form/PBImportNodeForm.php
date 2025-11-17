<?php

declare(strict_types=1);

namespace Drupal\pb_import_node\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Form for importing nodes from a CSV file.
 */
class PBImportNodeForm extends FormBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The CSV processor service.
   *
   * @var object
   */
  protected $csvProcessor;

  /**
   * The PB import utility service.
   *
   * @var object
   */
  protected $utility;

  /**
   * Constructs a new PBImportNodeForm.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param object $csv_processor
   *   The CSV processor service.
   * @param object $utility
   *   The PB import utility service.
   */
  public function __construct(
    FileSystemInterface $file_system,
    FileRepositoryInterface $file_repository,
    LoggerInterface $logger,
    $csv_processor,
    $utility,
  ) {
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->logger = $logger;
    $this->csvProcessor = $csv_processor;
    $this->utility = $utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('logger.channel.pb_import_node'),
      $container->get('pb_import_node.csv_processor.node'),
      $container->get('pb_import.utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_import_node_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $utility = $this->utility;

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload the CSV file. The CSV file must contain the columns: csv_title, csv_image_path, csv_image_alt, csv_image_title, csv_node_tag, and csv_node_body.'),
      '#required' => TRUE,
    ];

    $form['image_folder_relative_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Folder Relative Path'),
      '#description' => $this->t('Enter the relative path to the image folder inside files, e.g., gallery/austin (it will be @site_path/gallery/austin).', [
        '@site_path' => $utility->getSiteSpecificPath(),
      ]),
      '#required' => FALSE,
      '#maxlength' => 255,
    ];

    $form['content_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content Type'),
      '#description' => $this->t('pb_import_node is the content type provided by this module. If you use a different content type, ensure it has the same field names as pb_import_node.'),
      '#default_value' => 'pb_import_node',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['vocabulary_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vocabulary Name'),
      '#description' => $this->t('pb_import_node is the vocabulary provided by this module. You can override it by entering the machine name for a different vocabulary here.'),
      '#default_value' => 'pb_import_node',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Get the uploaded file from the request.
    $all_files = $this->getRequest()->files->get('files', []);

    if (!isset($all_files['csv_file']) || !$all_files['csv_file'] instanceof UploadedFile) {
      $form_state->setErrorByName('csv_file', $this->t('Please upload a valid CSV file.'));
      return;
    }

    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploaded_file */
    $uploaded_file = $all_files['csv_file'];

    // Validate file extension.
    $extension = strtolower((string) $uploaded_file->getClientOriginalExtension());
    if ($extension !== 'csv') {
      $form_state->setErrorByName('csv_file', $this->t('Only CSV files are allowed.'));
      return;
    }

    // Check for upload errors.
    if ($uploaded_file->getError() !== UPLOAD_ERR_OK) {
      $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
      ];
      $error_code = $uploaded_file->getError();
      $error_message = $error_messages[$error_code] ?? 'Unknown upload error.';
      $form_state->setErrorByName('csv_file', $this->t('File upload error: @message', [
        '@message' => $error_message,
      ]));
      return;
    }

    try {
      // Ensure temporary directory exists.
      $temp_directory = 'temporary://';
      $this->fileSystem->prepareDirectory($temp_directory, FileSystemInterface::CREATE_DIRECTORY);

      // Generate a unique temporary filename.
      $temp_file_uri = $this->fileSystem->createFilename(
        $uploaded_file->getClientOriginalName(),
        $temp_directory
      );

      // Copy the uploaded file to a temporary location.
      $temp_file_path = $this->fileSystem->copy(
        $uploaded_file->getPathname(),
        $temp_file_uri,
        FileSystemInterface::EXISTS_RENAME
      );

      if ($temp_file_path === FALSE) {
        $this->logger->error('Failed to copy uploaded file to temporary directory.');
        $form_state->setErrorByName('csv_file', $this->t('Failed to save the uploaded file.'));
        return;
      }

      // Create a file entity.
      $file = $this->fileRepository->writeData(
        (string) file_get_contents($temp_file_path),
        $temp_file_uri,
        FileSystemInterface::EXISTS_RENAME
      );

      if ($file instanceof FileInterface) {
        $this->logger->info('CSV file uploaded successfully: @uri', [
          '@uri' => $file->getFileUri(),
        ]);
        $form_state->setValue('csv_file', $file);
      }
      else {
        $this->logger->error('Failed to create file entity.');
        $form_state->setErrorByName('csv_file', $this->t('Failed to create file entity.'));
      }
    }
    catch (\Exception $e) {
      $this->logger->error('File upload failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      $form_state->setErrorByName('csv_file', $this->t('File upload failed: @message', [
        '@message' => $e->getMessage(),
      ]));
    }

    // Validate image folder path.
    $image_folder = $form_state->getValue('image_folder_relative_path');
    if (is_string($image_folder) && $image_folder !== '') {
      $image_folder = trim($image_folder);

      if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $image_folder)) {
        $form_state->setErrorByName('image_folder_relative_path', $this->t('The image folder path contains invalid characters.'));
      }

      if (str_contains($image_folder, '..')) {
        $form_state->setErrorByName('image_folder_relative_path', $this->t('The image folder path cannot contain ".." sequences.'));
      }
    }

    // Validate content type.
    $content_type = $form_state->getValue('content_type');
    if (is_string($content_type)) {
      $content_type = trim($content_type);
      if (!preg_match('/^[a-z_]+$/', $content_type)) {
        $form_state->setErrorByName('content_type', $this->t('Content type machine name must contain only lowercase letters and underscores.'));
      }
    }

    // Validate vocabulary name.
    $vocabulary = $form_state->getValue('vocabulary_name');
    if (is_string($vocabulary)) {
      $vocabulary = trim($vocabulary);
      if (!preg_match('/^[a-z_]+$/', $vocabulary)) {
        $form_state->setErrorByName('vocabulary_name', $this->t('Vocabulary machine name must contain only lowercase letters and underscores.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $form_state->getValue('csv_file');

    if (!$file instanceof FileInterface) {
      $this->messenger()->addError($this->t('There was an error loading the CSV file.'));
      return;
    }

    $folder_name = trim((string) $form_state->getValue('image_folder_relative_path'));
    $content_type = trim((string) $form_state->getValue('content_type'));
    $vocabulary_name = trim((string) $form_state->getValue('vocabulary_name'));

    $this->logger->info('Starting CSV processing for content type: @content_type and vocabulary: @vocabulary_name', [
      '@content_type' => $content_type,
      '@vocabulary_name' => $vocabulary_name,
    ]);

    $result = $this->csvProcessor->process(
      $file,
      $folder_name,
      $content_type,
      $vocabulary_name
    );

    if (($result['status'] ?? 'error') === 'error') {
      $this->logger->error('CSV processing failed with error: @message', [
        '@message' => $result['message'] ?? 'Unknown error.',
      ]);
      $this->messenger()->addError($result['message'] ?? $this->t('CSV processing failed due to an unknown error.'));
      return;
    }

    $processed = $result['processed'] ?? 0;
    $skipped = $result['skipped'] ?? 0;

    $this->logger->info('CSV file processed successfully. Nodes processed: @processed, Nodes skipped: @skipped', [
      '@processed' => $processed,
      '@skipped' => $skipped,
    ]);

    $this->messenger()->addStatus($this->t('Nodes processed: @processed, Nodes skipped: @skipped', [
      '@processed' => $processed,
      '@skipped' => $skipped,
    ]));
  }

}
