<?php

namespace Drupal\simple_voting;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/**
 * Lean repository for the voting MVP.
 */
class VotingRepository {

  /**
   * Prefix used for question machine names.
   */
  public const MACHINE_NAME_PREFIX = 'voting_';

  /**
   * The database connection.
   */
  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Returns the admin question listing with aggregated counts.
   */
  public function getAdminQuestions(): array {
    $query = $this->database->select('simple_voting_question', 'q');
    $query->leftJoin('simple_voting_option', 'o', 'o.question_id = q.id');
    $query->leftJoin('simple_voting_vote', 'v', 'v.question_id = q.id');
    $query->addField('q', 'id');
    $query->addField('q', 'machine_name');
    $query->addField('q', 'title');
    $query->addField('q', 'show_results');
    $query->addField('q', 'status');
    $query->addExpression('COUNT(DISTINCT o.id)', 'options_count');
    $query->addExpression('COUNT(DISTINCT v.id)', 'votes_count');
    $query->groupBy('q.id');
    $query->groupBy('q.machine_name');
    $query->groupBy('q.title');
    $query->groupBy('q.show_results');
    $query->groupBy('q.status');
    $query->orderBy('q.id', 'DESC');

    return $query->execute()->fetchAllAssoc('id');
  }

  /**
   * Returns active questions for the CMS listing.
   */
  public function getAvailableQuestions(): array {
    return $this->database->select('simple_voting_question', 'q')
      ->fields('q', ['id', 'machine_name', 'title', 'description', 'show_results'])
      ->condition('status', 1)
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Loads a question by id.
   */
  public function loadQuestion(int $question_id): ?array {
    $question = $this->database->select('simple_voting_question', 'q')
      ->fields('q')
      ->condition('id', $question_id)
      ->execute()
      ->fetchAssoc();

    return $question ?: NULL;
  }

  /**
   * Persists a question and returns its id.
   */
  public function saveQuestion(array $values, ?int $question_id = NULL): int {
    $timestamp = time();
    $record = [
      'machine_name' => $this->normalizeMachineName($values['machine_name']),
      'title' => $values['title'],
      'description' => $values['description'] ?? '',
      'show_results' => (int) !empty($values['show_results']),
      'status' => (int) !empty($values['status']),
      'changed' => $timestamp,
    ];

    if ($question_id) {
      $this->database->update('simple_voting_question')
        ->fields($record)
        ->condition('id', $question_id)
        ->execute();
      return $question_id;
    }

    $record['created'] = $timestamp;
    return (int) $this->database->insert('simple_voting_question')
      ->fields($record)
      ->execute();
  }

  /**
   * Deletes a question and its related data.
   */
  public function deleteQuestion(int $question_id): void {
    $option_ids = array_keys($this->getOptions($question_id));
    if ($option_ids) {
      $this->database->delete('simple_voting_vote')
        ->condition('option_id', $option_ids, 'IN')
        ->execute();

      $this->database->delete('simple_voting_option')
        ->condition('id', $option_ids, 'IN')
        ->execute();
    }

    $this->database->delete('simple_voting_vote')
      ->condition('question_id', $question_id)
      ->execute();

    $this->database->delete('simple_voting_question')
      ->condition('id', $question_id)
      ->execute();
  }

  /**
   * Checks whether a machine name already exists.
   */
  public function machineNameExists(string $machine_name, ?int $exclude_question_id = NULL): bool {
    $query = $this->database->select('simple_voting_question', 'q')
      ->condition('machine_name', $this->normalizeMachineName($machine_name))
      ->fields('q', ['id']);

    if ($exclude_question_id) {
      $query->condition('id', $exclude_question_id, '<>');
    }

    return (bool) $query->execute()->fetchField();
  }

  /**
   * Ensures the question machine name always carries the expected prefix.
   */
  public function normalizeMachineName(string $machine_name): string {
    $machine_name = trim($machine_name);

    if (str_starts_with($machine_name, self::MACHINE_NAME_PREFIX)) {
      return $machine_name;
    }

    return self::MACHINE_NAME_PREFIX . $machine_name;
  }

  /**
   * Returns the options for a given question.
   */
  public function getOptions(int $question_id): array {
    return $this->database->select('simple_voting_option', 'o')
      ->fields('o')
      ->condition('question_id', $question_id)
      ->orderBy('weight')
      ->orderBy('id')
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Loads a single option.
   */
  public function loadOption(int $option_id): ?array {
    $option = $this->database->select('simple_voting_option', 'o')
      ->fields('o')
      ->condition('id', $option_id)
      ->execute()
      ->fetchAssoc();

    return $option ?: NULL;
  }

  /**
   * Persists an option and returns its id.
   */
  public function saveOption(int $question_id, array $values, ?int $option_id = NULL): int {
    $record = [
      'question_id' => $question_id,
      'title' => $values['title'],
      'description' => $values['description'] ?? '',
      'image_fid' => (int) ($values['image_fid'] ?? 0),
      'weight' => (int) ($values['weight'] ?? 0),
    ];

    if ($option_id) {
      $this->database->update('simple_voting_option')
        ->fields($record)
        ->condition('id', $option_id)
        ->execute();
      return $option_id;
    }

    return (int) $this->database->insert('simple_voting_option')
      ->fields($record)
      ->execute();
  }

  /**
   * Deletes an option.
   */
  public function deleteOption(int $option_id): void {
    $this->database->delete('simple_voting_option')
      ->condition('id', $option_id)
      ->execute();
  }

  /**
   * Returns the number of votes for an option.
   */
  public function countVotesForOption(int $option_id): int {
    return (int) $this->database->select('simple_voting_vote', 'v')
      ->condition('option_id', $option_id)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Ensures an option belongs to a question.
   */
  public function optionBelongsToQuestion(int $option_id, int $question_id): bool {
    return (bool) $this->database->select('simple_voting_option', 'o')
      ->condition('id', $option_id)
      ->condition('question_id', $question_id)
      ->fields('o', ['id'])
      ->execute()
      ->fetchField();
  }

  /**
   * Checks whether the user already voted on the question.
   */
  public function hasUserVoted(int $question_id, int $uid): bool {
    return (bool) $this->database->select('simple_voting_vote', 'v')
      ->condition('question_id', $question_id)
      ->condition('uid', $uid)
      ->fields('v', ['id'])
      ->execute()
      ->fetchField();
  }

  /**
   * Returns the chosen option id for a user, if available.
   */
  public function getUserVoteOptionId(int $question_id, int $uid): ?int {
    $option_id = $this->database->select('simple_voting_vote', 'v')
      ->condition('question_id', $question_id)
      ->condition('uid', $uid)
      ->fields('v', ['option_id'])
      ->execute()
      ->fetchField();

    return $option_id !== FALSE ? (int) $option_id : NULL;
  }

  /**
   * Records a vote, honoring the unique vote per user/question pair.
   */
  public function recordVote(int $question_id, int $option_id, int $uid): bool {
    try {
      $this->database->insert('simple_voting_vote')
        ->fields([
          'question_id' => $question_id,
          'option_id' => $option_id,
          'uid' => $uid,
          'created' => time(),
        ])
        ->execute();

      return TRUE;
    }
    catch (IntegrityConstraintViolationException) {
      return FALSE;
    }
  }

  /**
   * Returns aggregated results for a question.
   */
  public function getResults(int $question_id): array {
    $query = $this->database->select('simple_voting_option', 'o');
    $query->leftJoin('simple_voting_vote', 'v', 'v.option_id = o.id');
    $query->fields('o', ['id', 'title']);
    $query->addExpression('COUNT(v.id)', 'votes_count');
    $query->condition('o.question_id', $question_id);
    $query->groupBy('o.id');
    $query->groupBy('o.title');
    $query->orderBy('o.weight');
    $query->orderBy('o.id');

    return $query->execute()->fetchAllAssoc('id');
  }

  /**
   * Counts all votes for a question.
   */
  public function countVotesForQuestion(int $question_id): int {
    return (int) $this->database->select('simple_voting_vote', 'v')
      ->condition('question_id', $question_id)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
