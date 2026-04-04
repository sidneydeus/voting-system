<?php

namespace Drupal\simple_voting\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\simple_voting\VotingRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin form for creating and editing question options.
 */
class OptionForm extends FormBase {

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
    return 'simple_voting_option_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $question = NULL, ?int $option = NULL): array {
    $question_data = $question ? $this->repository->loadQuestion($question) : NULL;
    $option_data = $option ? $this->repository->loadOption($option) : NULL;

    if (!$question_data || ($option && (!$option_data || (int) $option_data['question_id'] !== $question))) {
      throw new NotFoundHttpException();
    }

    $form['question_id'] = [
      '#type' => 'value',
      '#value' => $question,
    ];

    $form['option_id'] = [
      '#type' => 'value',
      '#value' => $option,
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option title'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $option_data['title'] ?? '',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Short description'),
      '#default_value' => $option_data['description'] ?? '',
    ];

    $form['image_fid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Image'),
      '#upload_location' => 'public://simple-voting',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif webp'],
      ],
      '#default_value' => !empty($option_data['image_fid']) ? [$option_data['image_fid']] : [],
      '#description' => $this->t('Optional field.'),
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $option_data['weight'] ?? 0,
      '#description' => $this->t('Lower values appear first.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $option_data ? $this->t('Save') : $this->t('Add option'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('simple_voting.question_options', ['question' => $question]),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $question_id = (int) $form_state->getValue('question_id');
    $option_id = $form_state->getValue('option_id');
    $image_ids = array_filter((array) $form_state->getValue('image_fid'));
    $image_fid = $image_ids ? (int) reset($image_ids) : 0;

    if ($image_fid && ($file = File::load($image_fid))) {
      $file->setPermanent();
      $file->save();
    }

    $this->repository->saveOption($question_id, [
      'title' => $form_state->getValue('title'),
      'description' => $form_state->getValue('description'),
      'image_fid' => $image_fid,
      'weight' => $form_state->getValue('weight'),
    ], $option_id ? (int) $option_id : NULL);

    $this->messenger()->addStatus($option_id ? $this->t('Option updated.') : $this->t('Option created.'));
    $form_state->setRedirect('simple_voting.question_options', ['question' => $question_id]);
  }

}
