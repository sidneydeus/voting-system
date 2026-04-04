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
      '#title' => $this->t('Unique identifier'),
      '#required' => TRUE,
      '#default_value' => $question_data['machine_name'] ?? '',
      '#disabled' => (bool) $question_data,
      '#machine_name' => [
        'exists' => [$this, 'machineNameExists'],
      ],
      '#description' => $this->t('Used as the technical identifier for the question.'),
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $question_id = $form_state->getValue('question_id');
    $saved_id = $this->repository->saveQuestion($form_state->getValues(), $question_id ? (int) $question_id : NULL);

    $this->messenger()->addStatus($question_id ? $this->t('Question updated.') : $this->t('Question created.'));
    $form_state->setRedirect('simple_voting.question_options', ['question' => $saved_id]);
  }

}
