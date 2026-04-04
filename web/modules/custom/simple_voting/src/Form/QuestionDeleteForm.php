<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_voting\VotingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for deleting questions.
 */
class QuestionDeleteForm extends ConfirmFormBase {

  /**
   * The question being deleted.
   */
  protected ?array $question = NULL;

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
    return 'simple_voting_question_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $question = NULL): array {
    $this->question = $question ? $this->repository->loadQuestion($question) : NULL;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->question
      ? (string) $this->t('Are you sure you want to delete the question "@title" and all related data?', ['@title' => $this->question['title']])
      : (string) $this->t('Are you sure you want to delete this question?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('simple_voting.admin_questions');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return (string) $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->question) {
      $this->repository->deleteQuestion((int) $this->question['id']);
      $this->messenger()->addStatus($this->t('Question deleted.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
