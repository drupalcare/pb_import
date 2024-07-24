<?php

namespace Drupal\pb_import_para\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
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
use Drupal\pb_import_para\Service\CSVProcessorPara;
use Drupal\pb_import_para\Service\ParagraphCreator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing slideshow paragraphs from a CSV file.
 */
class PBImportParaForm extends FormBase {

  /**
   * The CSV processor service.
   *
   * @var \Drupal\pb_import_para\Service\CSVProcessorPara
   */
  protected CSVProcessorPara $csvProcessorPara;

  /**
   * The file manager service.
   *
   * @var \Drupal\pb_import\Service\FileManager
   */
  protected FileManager $fileManager;

  /**
   * The paragraph creator service.
   *
   * @var \Drupal\pb_import_para\Service\ParagraphCreator
   */
  protected ParagraphCreator $paragraphCreator;

  /**
   * The term manager service.
   *
   * @var \Drupal\pb_import\Service\TermManager
   */
  protected TermManager $termManager;

  /**
   * The utility service.
   *
   * @var \Drupal\pb_import\Service\Utility
   */
  protected Utility $utility;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Associative array to store configurable values for each paragraph type.
   *
   * @var array
   */
  protected array $paragraphTypeConfig = [
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
   * PBImportParaForm constructor.
   */
  public function __construct(CSVProcessorPara $csvProcessorPara, FileManager $fileManager, ParagraphCreator $paragraphCreator, TermManager $termManager, Utility $utility, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger, ModuleHandlerInterface $module_handler) {
    $this->csvProcessorPara = $csvProcessorPara;
    $this->fileManager = $fileManager;
    $this->paragraphCreator = $paragraphCreator;
    $this->termManager = $termManager;
    $this->utility = $utility;
    $this->logger = $logger_factory->get('pb_import_para');
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pb_import_para.csv_processor.paragraph'),
      $container->get('pb_import.file_manager'),
      $container->get('pb_import_para.paragraph_creator'),
      $container->get('pb_import.term_manager'),
      $container->get('pb_import.utility'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pb_import_para_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $missing_modules = [];

    // Check if paragraph_bundle_accordion is installed.
    if ($this->moduleHandler->moduleExists('paragraph_bundle_accordion')) {
      $options['accordion'] = $this->t('Accordion');
    }
    else {
      $missing_modules[] = 'Accordion';
    }

    // Check if paragraph_bundle_tabs is installed.
    if ($this->moduleHandler->moduleExists('paragraph_bundle_tabs')) {
      $options['tabs'] = $this->t('Tabs');
    }
    else {
      $missing_modules[] = 'Tabs';
    }

    // Check if paragraph_bundle_slideshow is installed.
    if ($this->moduleHandler->moduleExists('paragraph_bundle_slideshow')) {
      $options['slideshow'] = $this->t('Slideshow');
    }
    else {
      $missing_modules[] = 'Slideshow';
    }

    // Display the dropdown with available options.
    $form['paragraph_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Paragraph Type'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateFormFields',
        'event' => 'change',
        'wrapper' => 'form-fields-wrapper',
      ],
    ];

    // Display a friendly message for missing modules.
    if (!empty($missing_modules)) {
      $message = $this->t('To import @modules, you must enable the corresponding modules to see them in the select list.', ['@modules' => implode(', ', $missing_modules)]);
      $form['messages'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $message . '</p>',
        '#prefix' => '<div class="messages messages--warning">',
        '#suffix' => '</div>',
      ];
    }

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload the CSV file containing the data.'),
      '#required' => TRUE,
    ];

    $form['image_folder_relative_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image Folder Relative Path'),
      '#description' => $this->t('Enter the relative path to the image folder inside files, e.g., gallery/austin (it will be @site_path/gallery/austin)', ['@site_path' => $this->utility->getSiteSpecificPath()]),
      '#required' => FALSE,
    ];

    $form['parent_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parent Title'),
      '#description' => $this->t('Enter the title for the parent paragraph.'),
      '#required' => TRUE,
    ];

    // Wrapper for form fields that will be dynamically updated.
    $form['form_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'form-fields-wrapper'],
    ];

    // Call a helper function to add form fields based on the selected
    // paragraph type.
    $this->addFormFields($form['form_fields'], $form_state);

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * AJAX callback to update the form fields.
   */
  public function updateFormFields(array &$form, FormStateInterface $form_state) {
    return $form['form_fields'];
  }

  /**
   * Adds form fields based on the selected paragraph type.
   */
  private function addFormFields(array &$form, FormStateInterface $form_state) {
    $paragraph_type = $form_state->getValue('paragraph_type', 'slideshow');

    if (isset($this->paragraphTypeConfig[$paragraph_type])) {
      $config = $this->paragraphTypeConfig[$paragraph_type];

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
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['csv']];
    if ($file = file_save_upload('csv_file', $validators, FALSE, 0, FileSystemInterface::EXISTS_RENAME)) {
      $form_state->setValue('csv_file', $file);
    }
    else {
      $form_state->setErrorByName('csv_file', $this->t('Please upload a valid CSV file.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $csv_file = $form_state->getValue('csv_file');
    $parent_title = $form_state->getValue('parent_title');
    $paragraph_type = $form_state->getValue('paragraph_type');
    $image_folder_relative_path = $form_state->getValue('image_folder_relative_path');

    if (isset($this->paragraphTypeConfig[$paragraph_type])) {
      $config = $this->paragraphTypeConfig[$paragraph_type];

      $parent_paragraph_type = $config['parent_paragraph_type'];
      $parent_entity_reference_field = $config['parent_entity_reference_field'];
      $section_paragraph_type = $config['section_paragraph_type'];
      $section_entity_reference_field = $config['section_entity_reference_field'];
      $section_title_field = $config['section_title_field'];
      $target_bundle = $config['target_bundle'];
      $vocabulary_name = $config['vocabulary_name'];
    }
    else {
      // Default to slideshow values if not set.
      $parent_paragraph_type = 'slideshow_bundle';
      $parent_entity_reference_field = 'pb_content_slideshow_section';
      $section_paragraph_type = 'slideshow_section_bundle';
      $section_entity_reference_field = 'pb_content_slideshow_body';
      $section_title_field = 'pb_content_slideshow_title';
      $target_bundle = 'pb_target_bundle';
      $vocabulary_name = 'pb_slideshow_tag';
    }

    if ($csv_file instanceof File) {
      $this->logger->info('Starting CSV processing for parent title: @parent_title', ['@parent_title' => $parent_title]);

      $result = $this->csvProcessorPara->process(
        $csv_file,
        $parent_title,
        $parent_paragraph_type,
        $parent_entity_reference_field,
        $section_paragraph_type,
        $section_entity_reference_field,
        $target_bundle,
        $vocabulary_name,
        $image_folder_relative_path,
        $section_title_field
      );

      if ($result['status'] == 'error') {
        $this->logger->error('CSV processing failed with error: @message', ['@message' => $result['message']]);
        $this->messenger->addError($result['message']);
      }
      else {
        $processed = $result['processed'] ?? 0;
        $skipped = $result['skipped'] ?? 0;

        if ($processed > 0) {
          $this->logger->info('CSV file processed successfully. Sections processed: @processed, Sections skipped: @skipped', [
            '@processed' => $processed,
            '@skipped' => $skipped,
          ]);
          $this->messenger->addMessage($this->t('Sections processed: @processed, Sections skipped: @skipped', [
            '@processed' => $processed,
            '@skipped' => $skipped,
          ]));
        }
        else {
          $this->logger->error('No section paragraphs were created. Cannot create parent paragraph. Please check the CSV file and folder name.');
          $this->messenger->addError($this->t('No section paragraphs were created. Cannot create parent paragraph. Please check the CSV file and folder name.'));
        }
      }
    }
    else {
      $this->logger->error('The CSV file could not be loaded as a file entity.');
      $this->messenger->addError($this->t('There was an error loading the CSV file.'));
    }
  }

}
