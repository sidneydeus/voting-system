<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_voting\VotingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User voting form.
 */
class VoteForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    protected VotingRepository $repository,
    protected AccountProxyInterface $currentUser,
    protected ConfigFactoryInterface $simpleVotingConfigFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('simple_voting.repository'),
      $container->get('current_user'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_voting_vote_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $question = NULL): array {
    $question_data = $question ? $this->repository->loadQuestion($question) : NULL;
    $options = $question ? $this->repository->getOptions($question) : [];

    $choices = [];
    foreach ($options as $option) {
      $choices[$option->id] = $option->title;
    }

    $form['question_id'] = [
      '#type' => 'value',
      '#value' => $question,
    ];

    $form['option_id'] = [
      '#type' => 'radios',
      '#title' => $question_data['title'] ?? $this->t('Option'),
      '#required' => TRUE,
      '#options' => $choices,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit vote'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $question_id = (int) $form_state->getValue('question_id');
    $option_id = (int) $form_state->getValue('option_id');
    $voting_enabled = (bool) $this->simpleVotingConfigFactory->get('simple_voting.settings')->get('voting_enabled');

    if (!$this->currentUser->isAuthenticated()) {
      $form_state->setErrorByName('option_id', $this->t('You must be logged in to vote.'));
      return;
    }

    if (!$this->currentUser->hasPermission('vote on simple voting')) {
      $form_state->setErrorByName('option_id', $this->t('You do not have permission to vote.'));
      return;
    }

    if (!$voting_enabled) {
      $form_state->setErrorByName('option_id', $this->t('Voting is currently disabled.'));
      return;
    }

    if (!$this->repository->optionBelongsToQuestion($option_id, $question_id)) {
      $form_state->setErrorByName('option_id', $this->t('Select a valid option.'));
      return;
    }

    if ($this->repository->hasUserVoted($question_id, (int) $this->currentUser->id())) {
      $form_state->setErrorByName('option_id', $this->t('You have already voted on this question.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $question_id = (int) $form_state->getValue('question_id');
    $option_id = (int) $form_state->getValue('option_id');

    $saved = $this->repository->recordVote($question_id, $option_id, (int) $this->currentUser->id());

    if ($saved) {
      $this->messenger()->addStatus($this->t('Your vote has been recorded.'));
    }
    else {
      $this->messenger()->addWarning($this->t('Your vote had already been recorded.'));
    }

    $form_state->setRedirect('simple_voting.question_view', ['question' => $question_id]);
  }

}
