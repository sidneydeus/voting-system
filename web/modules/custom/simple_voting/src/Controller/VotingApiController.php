<?php

namespace Drupal\simple_voting\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_voting\VotingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * JSON API controller for external voting integrations.
 */
class VotingApiController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    protected VotingRepository $repository,
    protected AccountProxyInterface $simpleVotingCurrentUser,
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
   * Returns the available questions.
   */
  public function listQuestions(): JsonResponse {
    $questions = [];

    foreach ($this->repository->getAvailableQuestions() as $question) {
      $questions[] = [
        'id' => (int) $question->id,
        'identifier' => $question->machine_name,
        'title' => $question->title,
        'description' => $question->description,
        'show_results' => (bool) $question->show_results,
      ];
    }

    return new JsonResponse([
      'voting_enabled' => $this->isVotingEnabled(),
      'questions' => $questions,
    ]);
  }

  /**
   * Returns one question and its options by machine name.
   */
  public function viewQuestion(string $identifier): JsonResponse {
    $question = $this->repository->loadQuestionByMachineName($identifier);
    if (!$question || !$question['status']) {
      return $this->errorResponse('Question not found.', 404);
    }

    return new JsonResponse([
      'question' => $this->buildQuestionPayload($question, TRUE),
    ]);
  }

  /**
   * Records a vote for the current authenticated user.
   */
  public function vote(Request $request, string $identifier): JsonResponse {
    $question = $this->repository->loadQuestionByMachineName($identifier);
    if (!$question || !$question['status']) {
      return $this->errorResponse('Question not found.', 404);
    }

    if (!$this->isVotingEnabled()) {
      return $this->errorResponse('Voting is currently disabled.', 403);
    }

    if (!$this->simpleVotingCurrentUser->isAuthenticated()) {
      return $this->errorResponse('You must be logged in to vote.', 403);
    }

    if (!$this->simpleVotingCurrentUser->hasPermission('vote on simple voting')) {
      return $this->errorResponse('You do not have permission to vote.', 403);
    }

    $data = json_decode($request->getContent(), TRUE) ?: [];
    $option_id = isset($data['option_id']) ? (int) $data['option_id'] : 0;

    if (!$option_id || !$this->repository->optionBelongsToQuestion($option_id, (int) $question['id'])) {
      return $this->errorResponse('Select a valid option.', 422);
    }

    if ($this->repository->hasUserVoted((int) $question['id'], (int) $this->simpleVotingCurrentUser->id())) {
      return $this->errorResponse('You have already voted on this question.', 409);
    }

    $saved = $this->repository->recordVote((int) $question['id'], $option_id, (int) $this->simpleVotingCurrentUser->id());
    if (!$saved) {
      return $this->errorResponse('Your vote had already been recorded.', 409);
    }

    return new JsonResponse([
      'message' => 'Your vote has been recorded.',
      'question' => $this->buildQuestionPayload($question, TRUE),
      'results' => $question['show_results'] ? $this->buildResultsPayload((int) $question['id']) : NULL,
    ], 201);
  }

  /**
   * Returns results when allowed by the question settings.
   */
  public function results(string $identifier): JsonResponse {
    $question = $this->repository->loadQuestionByMachineName($identifier);
    if (!$question || !$question['status']) {
      return $this->errorResponse('Question not found.', 404);
    }

    if (empty($question['show_results'])) {
      return $this->errorResponse('Results are not available for this question.', 403);
    }

    if (!$this->simpleVotingCurrentUser->isAuthenticated()) {
      return $this->errorResponse('You must be logged in to view results.', 403);
    }

    if (!$this->repository->hasUserVoted((int) $question['id'], (int) $this->simpleVotingCurrentUser->id())) {
      return $this->errorResponse('Results are available only after voting.', 403);
    }

    return new JsonResponse([
      'question' => $this->buildQuestionPayload($question),
      'results' => $this->buildResultsPayload((int) $question['id']),
    ]);
  }

  /**
   * Builds the payload for a question.
   */
  protected function buildQuestionPayload(array $question, bool $include_options = FALSE): array {
    $payload = [
      'id' => (int) $question['id'],
      'identifier' => $question['machine_name'],
      'title' => $question['title'],
      'description' => $question['description'],
      'show_results' => (bool) $question['show_results'],
      'status' => (bool) $question['status'],
    ];

    if ($include_options) {
      $payload['options'] = $this->buildOptionsPayload((int) $question['id']);
    }

    return $payload;
  }

  /**
   * Builds the payload for question options.
   */
  protected function buildOptionsPayload(int $question_id): array {
    $options = [];

    foreach ($this->repository->getOptions($question_id) as $option) {
      $options[] = [
        'id' => (int) $option->id,
        'title' => $option->title,
        'description' => $option->description,
        'weight' => (int) $option->weight,
      ];
    }

    return $options;
  }

  /**
   * Builds the payload for results.
   */
  protected function buildResultsPayload(int $question_id): array {
    $results = [];

    foreach ($this->repository->getResults($question_id) as $result) {
      $results[] = [
        'option_id' => (int) $result->id,
        'title' => $result->title,
        'votes_count' => (int) $result->votes_count,
      ];
    }

    return [
      'total_votes' => $this->repository->countVotesForQuestion($question_id),
      'options' => $results,
    ];
  }

  /**
   * Returns a standard JSON error response.
   */
  protected function errorResponse(string $message, int $status): JsonResponse {
    return new JsonResponse(['message' => $message], $status);
  }

  /**
   * Checks whether voting is globally enabled.
   */
  protected function isVotingEnabled(): bool {
    return (bool) $this->simpleVotingConfigFactory->get('simple_voting.settings')->get('voting_enabled');
  }

}
