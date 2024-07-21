<?php

namespace Drupal\pb_import_para\Form;

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

  protected CSVProcessorPara $csvProcessorPara;
  protected FileManager $fileManager;
  protected ParagraphCreator $paragraphCreator;
  protected TermManager $termManager;
  protected Utility $utility;
  protected LoggerChannelInterface $logger;
  protected $messenger;

  /**
   * Associative array to store configurable values for each paragraph type.
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
  public function __construct(CSVProcessorPara $csvProcessorPara, FileManager $fileManager, ParagraphCreator $paragraphCreator, TermManager $termManager, Utility $utility, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger) {
    $this->csvProcessorPara = $csvProcessorPara;
    $this->fileManager = $fileManager;
    $this->paragraphCreator = $paragraphCreator;
    $this->termManager = $termManager;
    $this->utility = $utility;
    $this->logger = $logger_factory->get('pb_import_para');
    $this->messenger = $messenger;
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
      $container->get('messenger')
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
    // Dropdown for paragraph type selection.
    $form['paragraph_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Paragraph Type'),
      '#options' => [
        'slideshow' => $this->t('Slideshow'),
        'accordion' => $this->t('Accordion'),
        'tabs' => $this->t('Tabs'),
      ],
      '#ajax' => [
        'callback' => '::updateFormFields',
        'event' => 'change',
        'wrapper' => 'form-fields-wrapper',
      ],
    ];

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload the CSV file containing the data.'),
      '#required' => TRUE,
    ];

    $form['folder_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Folder Name'),
      '#description' => $this->t('Enter the relative path to the image folder inside files, e.g., gallery/austin (it will be @site_path/gallery/austin)', ['@site_path' => $this->utility->getSiteSpecificPath()]),
      '#required' => TRUE,
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

    // Call a helper function to add form fields based on the selected paragraph type.
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
    $folder_name = $form_state->getValue('folder_name');

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

      $result = $this->csvProcessorPara->process($csv_file, $parent_title, $parent_paragraph_type, $parent_entity_reference_field, $section_paragraph_type, $section_entity_reference_field, $target_bundle, $vocabulary_name, $folder_name, $section_title_field);

      if ($result['status'] == 'error') {
        $this->logger->error('CSV processing failed with error: @message', ['@message' => $result['message']]);
        $this->messenger->addError($result['message']);
      }
      else {
        $processed = $result['processed'] ?? 0;
        $skipped = $result['skipped'] ?? 0;

        $this->logger->info('CSV file processed successfully. Sections processed: @processed, Sections skipped: @skipped', [
          '@processed' => $processed,
          '@skipped' => $skipped,
        ]);
        $this->messenger->addMessage($this->t('Sections processed: @processed, Sections skipped: @skipped', [
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
