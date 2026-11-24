<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Form;

use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\makerspace_user_links\AssignedActivities\AssignedActivities;
use Drupal\makerspace_user_links\AssignedActivities\TaskActions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * One task card's note box and buttons on /assigned-to-me.
 *
 * One form per task, so each card submits on its own; the form ID carries
 * the activity ID and the base form ID groups them for theming.
 */
class AssignedTaskForm extends FormBase implements BaseFormIdInterface {

  /**
   * The CiviCRM activity this form acts on.
   */
  protected int $activityId = 0;

  public function __construct(
    protected AssignedActivities $activities,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('makerspace_user_links.assigned_activities'));
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId(): string {
    return 'makerspace_assigned_task';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'makerspace_assigned_task_' . $this->activityId;
  }

  /**
   * Sets the task before the form builder asks for the form ID.
   */
  public function setActivityId(int $activityId): static {
    $this->activityId = $activityId;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $activity_id = 0): array {
    $form_state->set('activity_id', $activity_id ?: $this->activityId);
    $form['#attributes']['class'][] = 'mh-task__form';
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Note'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Add a note (optional). It is saved with whichever button you press.'),
      '#rows' => 2,
      '#resizable' => 'vertical',
    ];
    $form['actions'] = ['#type' => 'actions'];
    foreach (TaskActions::actions() as $key => $spec) {
      $form['actions'][$key] = [
        '#type' => 'submit',
        '#value' => $this->t($spec['label']),
        '#name' => 'task_action_' . $key,
        '#task_action' => $key,
        '#attributes' => ['class' => ['mh-task__btn', 'mh-task__btn--' . $key]],
        '#button_type' => $key === 'done' ? 'primary' : '',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $action = (string) ($trigger['#task_action'] ?? '');
    if ($action === 'note' && trim((string) $form_state->getValue('note')) === '') {
      $form_state->setErrorByName('note', $this->t('Type a note first.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $action = (string) ($form_state->getTriggeringElement()['#task_action'] ?? '');
    $activityId = (int) $form_state->get('activity_id');
    $contactId = $this->activities->contactId($this->currentUser());
    // Only the assignee can act on a task from this page.
    if (!$activityId || !$contactId || !$this->activities->isAssignedTo($activityId, $contactId)) {
      $this->messenger()->addError($this->t('That task is not assigned to you.'));
      return;
    }
    try {
      $closed = $this->activities->act($activityId, $action, (string) $form_state->getValue('note'), $this->currentUser()->getDisplayName());
    }
    catch (\Throwable $e) {
      $this->logger('makerspace_user_links')->error('Assigned task @id action @a failed: @m', [
        '@id' => $activityId,
        '@a' => $action,
        '@m' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('That did not save. Try again, or open the task in CiviCRM.'));
      return;
    }
    $label = $this->t(TaskActions::actions()[$action]['label'] ?? '');
    $message = match (TRUE) {
      $closed => $this->t('Marked "@label". It is off your list.', ['@label' => $label]),
      $action === 'note' => $this->t('Note saved.'),
      $action === 'snooze' => $this->t('Snoozed until @date.', [
        '@date' => date('D, M j', strtotime(TaskActions::snoozeDate(\Drupal::time()->getRequestTime()))),
      ]),
      default => $this->t('Marked "@label".', ['@label' => $label]),
    };
    $this->messenger()->addStatus($message);
    if (!$closed) {
      $form_state->setRedirect('makerspace_user_links.assigned_to_me', [], ['fragment' => 'task-' . $activityId]);
    }
  }

}
