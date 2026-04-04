<?php

namespace Drupal\simple_voting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\file\Entity\File;
use Drupal\simple_voting\Form\VoteForm;
use Drupal\simple_voting\VotingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for admin and user-facing voting pages.
 */
class VotingController extends ControllerBase {

  /**
   * Constructs the controller.
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
   * Admin listing of questions.
   */
  public function adminQuestions(): array {
    $rows = [];
    foreach ($this->repository->getAdminQuestions() as $question) {
      $operations = [
        Link::fromTextAndUrl($this->t('Edit'), Url::fromRoute('simple_voting.question_edit', ['question' => $question->id]))->toString(),
        Link::fromTextAndUrl($this->t('Options'), Url::fromRoute('simple_voting.question_options', ['question' => $question->id]))->toString(),
        Link::fromTextAndUrl($this->t('Delete'), Url::fromRoute('simple_voting.question_delete', ['question' => $question->id]))->toString(),
      ];

      $rows[] = [
        'data' => [
          $question->title,
          $question->machine_name,
          (int) $question->options_count,
          (int) $question->votes_count,
          $question->show_results ? $this->t('Yes') : $this->t('No'),
          $question->status ? $this->t('Active') : $this->t('Inactive'),
          ['data' => ['#markup' => implode(' | ', $operations)]],
        ],
      ];
    }

    return [
      'actions' => [
        '#type' => 'link',
        '#title' => $this->t('Add question'),
        '#url' => Url::fromRoute('simple_voting.question_add'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Question'),
          $this->t('Identifier'),
          $this->t('Options'),
          $this->t('Votes'),
          $this->t('Show results'),
          $this->t('Status'),
          $this->t('Operations'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No questions available.'),
      ],
    ];
  }

  /**
   * Escapes plain text for safe markup output.
   */
  protected function escape(string $text): string {
    return Html::escape($text);
  }

  /**
   * Admin listing of options for a question.
   */
  public function adminOptions(int $question): array {
    $question_data = $this->repository->loadQuestion($question);
    if (!$question_data) {
      throw new NotFoundHttpException();
    }

    $rows = [];
    foreach ($this->repository->getOptions($question) as $option) {
      $operations = [
        Link::fromTextAndUrl($this->t('Edit'), Url::fromRoute('simple_voting.option_edit', ['question' => $question, 'option' => $option->id]))->toString(),
        Link::fromTextAndUrl($this->t('Delete'), Url::fromRoute('simple_voting.option_delete', ['question' => $question, 'option' => $option->id]))->toString(),
      ];

      $rows[] = [
        'data' => [
          $option->title,
          $option->description,
          $option->image_fid ? $this->t('Yes') : $this->t('No'),
          (int) $option->weight,
          ['data' => ['#markup' => implode(' | ', $operations)]],
        ],
      ];
    }

    return [
      'summary' => [
        '#markup' => '<p><strong>' . $this->t('Question:') . '</strong> ' . $this->escape($question_data['title']) . '</p>',
      ],
      'actions' => [
        '#type' => 'link',
        '#title' => $this->t('Add option'),
        '#url' => Url::fromRoute('simple_voting.option_add', ['question' => $question]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to questions'),
        '#url' => Url::fromRoute('simple_voting.admin_questions'),
        '#attributes' => ['class' => ['button']],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Title'),
          $this->t('Description'),
          $this->t('Image'),
          $this->t('Weight'),
          $this->t('Operations'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No options available.'),
      ],
    ];
  }

  /**
   * User-facing question listing.
   */
  public function listQuestions(): array {
    $global_enabled = (bool) $this->config('simple_voting.settings')->get('voting_enabled');
    $items = [];

    foreach ($this->repository->getAvailableQuestions() as $question) {
      $items[] = Link::fromTextAndUrl(
        $question->title,
        Url::fromRoute('simple_voting.question_view', ['question' => $question->id])
      );
    }

    $build = [];
    if (!$global_enabled) {
      $build['notice'] = [
        '#markup' => '<p>' . $this->t('Voting is temporarily disabled.') . '</p>',
      ];
    }

    if ($items) {
      $build['questions'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Available questions'),
        '#items' => $items,
      ];
    }
    else {
      $build['questions'] = [
        '#markup' => '<p>' . $this->t('No questions are available right now.') . '</p>',
      ];
    }

    return $build;
  }

  /**
   * Displays one question, the voting form and optional results.
   */
  public function viewQuestion(int $question): array {
    $question_data = $this->repository->loadQuestion($question);
    if (!$question_data || !$question_data['status']) {
      throw new NotFoundHttpException();
    }

    $options = $this->repository->getOptions($question);
    $current_user = $this->currentUser();
    $global_enabled = (bool) $this->config('simple_voting.settings')->get('voting_enabled');
    $has_voted = $current_user->isAuthenticated() ? $this->repository->hasUserVoted($question, (int) $current_user->id()) : FALSE;
    $selected_option_id = $current_user->isAuthenticated() ? $this->repository->getUserVoteOptionId($question, (int) $current_user->id()) : NULL;

    $build = [
      'question' => [
        '#type' => 'container',
        'title' => [
          '#markup' => '<h2>' . $this->escape($question_data['title']) . '</h2>',
        ],
        'description' => [
          '#markup' => nl2br($this->escape($question_data['description'] ?? '')),
        ],
      ],
    ];

    foreach ($options as $option) {
      $item = [
        '#type' => 'container',
        '#attributes' => ['class' => ['simple-voting-option']],
        'title' => ['#markup' => '<h3>' . $this->escape($option->title) . '</h3>'],
        'description' => ['#markup' => nl2br($this->escape($option->description ?? ''))],
      ];

      if ($option->image_fid && ($file = File::load($option->image_fid))) {
        $item['image'] = [
          '#theme' => 'image',
          '#uri' => $file->getFileUri(),
          '#alt' => $option->title,
          '#attributes' => ['style' => 'max-width: 320px; height: auto;'],
        ];
      }

      if ($selected_option_id === (int) $option->id) {
        $item['selected'] = [
          '#markup' => '<p><strong>' . $this->t('Your vote') . '</strong></p>',
        ];
      }

      $build['options'][] = $item;
    }

    if (!$current_user->isAuthenticated()) {
      $build['auth_notice'] = [
        '#markup' => '<p>' . $this->t('Only authenticated users can vote.') . '</p>',
      ];
    }
    elseif (!$global_enabled) {
      $build['disabled_notice'] = [
        '#markup' => '<p>' . $this->t('Voting is temporarily disabled.') . '</p>',
      ];
    }
    elseif (!$options) {
      $build['no_options'] = [
        '#markup' => '<p>' . $this->t('This question does not have any answer options yet.') . '</p>',
      ];
    }
    elseif (!$has_voted && $current_user->hasPermission('vote on simple voting')) {
      $build['form'] = $this->formBuilder()->getForm(VoteForm::class, $question);
    }

    if ($has_voted && $question_data['show_results']) {
      $rows = [];
      $total_votes = max(1, $this->repository->countVotesForQuestion($question));
      foreach ($this->repository->getResults($question) as $result) {
        $votes = (int) $result->votes_count;
        $rows[] = [
          'data' => [
            $result->title,
            $votes,
            round(($votes / $total_votes) * 100, 2) . '%',
          ],
        ];
      }

      $build['results'] = [
        '#type' => 'table',
        '#caption' => $this->t('Current results'),
        '#header' => [
          $this->t('Option'),
          $this->t('Votes'),
          $this->t('Percentage'),
        ],
        '#rows' => $rows,
      ];
    }
    elseif ($has_voted) {
      $build['results_hidden'] = [
        '#markup' => '<p>' . $this->t('Your vote has been recorded. Results are hidden for this question.') . '</p>',
      ];
    }

    return $build;
  }

}
