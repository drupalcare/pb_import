<?php

declare(strict_types=1);

namespace Drupal\pb_import_para\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
// Adjust if your utility class is in a different namespace.
use Drupal\pb_import\Service\Utility;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Form for importing slideshow/accordion/tabs paragraphs from a CSV file.
 */
class PBImportParaForm extends FormBase {

  /**
   * Associative array to store configurable values for each paragraph type.
   *
   * @var array<string, array<string, string>>
   */
  private const PARAGRAPH_TYPE_CONFIG = [
    'slideshow' => [
      'parent_paragraph_type' => 'slideshow_bundle',
      'parent_entity_reference_field' => 'pb_content_slideshow_section',
      'section_paragraph_type' => 'slideshow_section_bundle',
      'section_entity_reference_field' => 'pb_content_slideshow_body',
      'section_title_field' => 'pb_content_slideshow_title',
      'target_bundle' => 'pb_target_bundle',
      'vocabulary_name' => 'pb_slideshow_tag',
    ],
    'accordion' => [
      'parent_paragraph_type' => 'accordion_bundle',
      'parent_entity_reference_field' => 'pb_content_accordion_section',
      'section_paragraph_type' => 'accordion_section_bundle',
      'section_entity_reference_field' => 'pb_content_accordion_body',
      'section_title_field' => 'pb_content_accordion_title',
      'target_bundle' => 'pb_target_bundle',
      'vocabulary_name' => 'pb_accordion_tag',
    ],
    'tabs' => [
      'parent_paragraph_type' => 'tabs_bundle',
      'parent_entity_reference_field' => 'pb_content_tab_section',
      'section_paragraph_type' => 'tab_section_bundle',
      'section_entity_reference_field' => 'pb_content_tab_body',
      'section_title_field' => 'pb_content_tab_name',
      'target_bundle' => 'pb_target_bundle',
      'vocabulary_name' => 'pb_tabs_tag',
    ],
  ];

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

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
   * PB import utility service.
   *
   * @var \Drupal\pb_import\Service\Utility
   */
  protected Utility $utility;

  /**
   * Constructs a new PBImportParaForm.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    FileSystemInterface $file_system,
    FileRepositoryInterface $file_repository,
    LoggerInterface $logger,
    $csv_processor,
    Utility $utility,
  ) {
    $this->moduleHandler = $module_handler;
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
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('logger.channel.pb_import_para'),
      $container->get('pb_import_para.csv_processor.paragraph'),
      $container->get('pb_import.utility'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pb_import_para_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    $missing_modules = [];

    // Paragraph type availability based on enabled modules.
    if ($this->moduleHandler->moduleExists('paragraph_bundle_accordion')) {
      $options['accordion'] = $this->t('Accordion');
    }
    else {
      $missing_modules[] = 'Accordion (paragraph_bundle_accordion)';
    }

    if ($this->moduleHandler->moduleExists('paragraph_bundle_tabs')) {
      $options['tabs'] = $this->t('Tabs');
    }
    else {
      $missing_modules[] = 'Tabs (paragraph_bundle_tabs)';
    }

    if ($this->moduleHandler->moduleExists('paragraph_bundle_slideshow')) {
      $options['slideshow'] = $this->t('Slideshow');
    }
    else {
      $missing_modules[] = 'Slideshow (paragraph_bundle_slideshow)';
    }

    if (!empty($missing_modules)) {
      $form['missing_modules'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('The following modules are missing: @modules', [
          '@modules' => implode(', ', $missing_modules),
        ]) . '</div>',
      ];
    }

    $form['paragraph_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Paragraph Type'),
      '#options' => $options,
      '#default_value' => 'slideshow',
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateFormFields',
        'wrapper' => 'form-fields-wrapper',
      ],
    ];

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload the CSV file containing the data.'),
      '#required' => TRUE,
    ];

    $form['image_folder_relative_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Folder Relative Path'),
      '#description' => $this->t('Enter the relative path to the image files, e.g., gallery/austin (it will be @site_path/gallery/austin)', [
        '@site_path' => $this->utility->getSiteSpecificPath(),
      ]),
      '#required' => FALSE,
      '#maxlength' => 255,
    ];

    $form['parent_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parent Title'),
      '#description' => $this->t('Enter the title for the parent paragraph.'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['form_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'form-fields-wrapper'],
    ];

    $this->addFormFields($form['form_fields'], $form_state);

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
   * AJAX callback to update the form fields for the selected paragraph type.
   */
  public function updateFormFields(array &$form, FormStateInterface $form_state): array {
    return $form['form_fields'];
  }

  /**
   * Adds hidden config fields based on the selected paragraph type.
   */
  private function addFormFields(array &$form, FormStateInterface $form_state): void {
    $paragraph_type = $form_state->getValue('paragraph_type');
    $paragraph_type = is_string($paragraph_type) ? $paragraph_type : 'slideshow';

    if (!isset(self::PARAGRAPH_TYPE_CONFIG[$paragraph_type])) {
      $paragraph_type = 'slideshow';
    }

    $config = self::PARAGRAPH_TYPE_CONFIG[$paragraph_type];

    $form['parent_paragraph_type'] = [
      '#type' => 'hidden',
      '#value' => $config['parent_paragraph_type'],
    ];
    $form['parent_entity_reference_field'] = [
      '#type' => 'hidden',
      '#value' => $config['parent_entity_reference_field'],
    ];
    $form['section_paragraph_type'] = [
      '#type' => 'hidden',
      '#value' => $config['section_paragraph_type'],
    ];
    $form['section_entity_reference_field'] = [
      '#type' => 'hidden',
      '#value' => $config['section_entity_reference_field'],
    ];
    $form['section_title_field'] = [
      '#type' => 'hidden',
      '#value' => $config['section_title_field'],
    ];
    $form['target_bundle'] = [
      '#type' => 'hidden',
      '#value' => $config['target_bundle'],
    ];
    $form['vocabulary_name'] = [
      '#type' => 'hidden',
      '#value' => $config['vocabulary_name'],
    ];
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

      // Copy the uploaded file to temporary location.
      $temp_file_path = $this->fileSystem->copy(
        $uploaded_file->getPathname(),
        $temp_file_uri,
        FileExists::Rename
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
        FileExists::Rename
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $form_state->getValue('csv_file');

    if (!$file instanceof FileInterface) {
      $this->messenger()->addError($this->t('CSV file is missing. Please re-upload.'));
      return;
    }

    $parent_title = trim((string) $form_state->getValue('parent_title'));
    $paragraph_type = (string) $form_state->getValue('paragraph_type');
    $image_folder_relative_path = trim((string) $form_state->getValue('image_folder_relative_path'));

    if (!isset(self::PARAGRAPH_TYPE_CONFIG[$paragraph_type])) {
      $this->messenger()->addError($this->t('Invalid paragraph type selected.'));
      return;
    }

    $config = self::PARAGRAPH_TYPE_CONFIG[$paragraph_type];

    $this->logger->info('Starting CSV processing for parent title: @parent_title', [
      '@parent_title' => $parent_title,
    ]);

    // IMPORTANT FIX: pass the FileInterface, not a string.
    $result = $this->csvProcessor->process(
      $file,
      $parent_title,
      $config['parent_paragraph_type'],
      $config['parent_entity_reference_field'],
      $config['section_paragraph_type'],
      $config['section_entity_reference_field'],
      $config['target_bundle'],
      $config['vocabulary_name'],
      $image_folder_relative_path,
      $config['section_title_field'],
    );

    if (($result['status'] ?? 'error') === 'error') {
      $this->logger->error('CSV processing failed with error: @message', [
        '@message' => $result['message'] ?? 'Unknown error',
      ]);
      $this->messenger()->addError($result['message'] ?? $this->t('CSV processing failed due to an unknown error.'));
      return;
    }

    $processed = $result['processed'] ?? 0;
    $skipped = $result['skipped'] ?? 0;

    if ($processed > 0) {
      $this->logger->info('CSV file processed successfully. Sections processed: @processed, Sections skipped: @skipped', [
        '@processed' => $processed,
        '@skipped' => $skipped,
      ]);
      $this->messenger()->addStatus($this->t('Sections processed: @processed, Sections skipped: @skipped', [
        '@processed' => $processed,
        '@skipped' => $skipped,
      ]));
    }
    else {
      $this->logger->error('No section paragraphs were created. Cannot create parent paragraph.');
      $this->messenger()->addError($this->t('No section paragraphs were created. Cannot create parent paragraph. Please check the CSV file and folder name.'));
    }
  }

}
