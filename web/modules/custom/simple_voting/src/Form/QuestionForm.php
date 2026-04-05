<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_voting\VotingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form for creating and editing questions.
 */
class QuestionForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    protected VotingRepository $repository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('simple_voting.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_voting_question_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $question = NULL): array {
    $question_data = $question ? $this->repository->loadQuestion($question) : NULL;
    $machine_name_prefix = VotingRepository::MACHINE_NAME_PREFIX;
    $machine_name_default = $question_data['machine_name'] ?? '';

    if ($machine_name_default && str_starts_with($machine_name_default, $machine_name_prefix)) {
      $machine_name_default = substr($machine_name_default, strlen($machine_name_prefix));
    }

    $form['question_id'] = [
      '#type' => 'value',
      '#value' => $question,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $question_data['title'] ?? '',
    ];

    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Unique identifier suffix'),
      '#required' => TRUE,
      '#default_value' => $machine_name_default,
      '#disabled' => (bool) $question_data,
      '#field_prefix' => $machine_name_prefix,
      '#placeholder' => 'my_question',
      '#machine_name' => [
        'exists' => [$this, 'machineNameExists'],
      ],
      '#description' => $this->t('Enter only the final part of the identifier. It will be stored as "@example".', [
        '@example' => $machine_name_prefix . 'my_question',
      ]),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $question_data['description'] ?? '',
    ];

    $form['show_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show results after voting'),
      '#default_value' => $question_data['show_results'] ?? 0,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Question is active'),
      '#default_value' => $question_data['status'] ?? 1,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $question_data ? $this->t('Save') : $this->t('Add question'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('simple_voting.admin_questions'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * Machine name callback for the form element.
   */
  public function machineNameExists(string $value, array $element, FormStateInterface $form_state): bool {
    $question_id = $form_state->getValue('question_id');
    return $this->repository->machineNameExists($value, $question_id ? (int) $question_id : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $machine_name = $form_state->getValue('machine_name');

    if ($machine_name) {
      $form_state->setValue('machine_name', $this->repository->normalizeMachineName($machine_name));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $question_id = $form_state->getValue('question_id');
    $saved_id = $this->repository->saveQuestion($form_state->getValues(), $question_id ? (int) $question_id : NULL);

    $this->messenger()->addStatus($question_id ? $this->t('Question updated.') : $this->t('Question created.'));
    $form_state->setRedirect('simple_voting.question_options', ['question' => $saved_id]);
  }

}
