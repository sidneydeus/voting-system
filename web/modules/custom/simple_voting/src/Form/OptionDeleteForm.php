<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simple_voting\VotingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for deleting options.
 */
class OptionDeleteForm extends ConfirmFormBase {

  /**
   * The option being deleted.
   */
  protected ?array $option = NULL;

  /**
   * The parent question id.
   */
  protected int $questionId = 0;

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
    return 'simple_voting_option_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $question = NULL, ?int $option = NULL): array {
    $this->questionId = (int) $question;
    $option_data = $option ? $this->repository->loadOption($option) : NULL;
    if ($option_data && (int) $option_data['question_id'] === $this->questionId) {
      $this->option = $option_data;
    }

    if ($this->option && $this->repository->countVotesForOption((int) $this->option['id']) > 0) {
      $this->messenger()->addError($this->t('You cannot delete an option that has already received votes.'));
      $form_state->setRedirect('simple_voting.question_options', ['question' => $this->questionId]);
      return [];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->option
      ? (string) $this->t('Are you sure you want to delete the option "@title"?', ['@title' => $this->option['title']])
      : (string) $this->t('Are you sure you want to delete this option?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('simple_voting.question_options', ['question' => $this->questionId]);
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
    if ($this->option) {
      $this->repository->deleteOption((int) $this->option['id']);
      $this->messenger()->addStatus($this->t('Option deleted.'));
    }

    $form_state->setRedirect('simple_voting.question_options', ['question' => $this->questionId]);
  }

}
