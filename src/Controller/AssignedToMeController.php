<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\makerspace_user_links\AssignedActivities\AssignedActivities;
use Drupal\makerspace_user_links\AssignedActivities\TaskActions;
use Drupal\makerspace_user_links\Form\AssignedTaskForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the open CiviCRM activities assigned to the viewer (/assigned-to-me).
 *
 * CiviCRM's own activity lists mix these with every record filed under the
 * person's name; this page shows only what is still to do. Each task is a
 * card with the request, the person's contact details and mailing address,
 * a note box and one-click status buttons (Kate, 2026-11-24), so most tasks
 * never need CiviCRM's view and edit pages.
 */
class AssignedToMeController extends ControllerBase {

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
   * Renders the list.
   */
  public function page(): array {
    $build = [
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
      '#attached' => ['library' => ['makerspace_user_links/assigned_to_me']],
    ];
    $contactId = $this->activities->contactId($this->currentUser());
    if (!$contactId) {
      $build['empty'] = ['#markup' => '<p>' . $this->t('Your account is not linked to a CiviCRM contact, so nothing can be assigned to you.') . '</p>'];
      return $build;
    }

    $rows = $this->activities->open($contactId);
    $overdue = count(array_filter($rows, static fn(array $r): bool => $r['overdue']));
    $build['intro'] = [
      '#markup' => '<p class="mh-tasks__intro">'
      . ($rows
        ? $this->formatPlural(count($rows), '1 open task', '@count open tasks') . ($overdue ? ' · <strong>' . $this->formatPlural($overdue, '1 overdue', '@count overdue') . '</strong>' : '')
        : '')
      . '</p><p class="mh-tasks__help">' . $this->t('CiviCRM activities assigned to you that are still open, soonest first. <em>Done</em> and <em>Not needed</em> take a task off this list; <em>Waiting on them</em> keeps it here. A note is saved, dated and signed, with whichever button you press.') . '</p>',
    ];
    if (!$rows) {
      $build['none'] = ['#markup' => '<p>' . $this->t('Nothing is assigned to you right now.') . '</p>'];
      return $build;
    }

    $build['tasks'] = ['#type' => 'container', '#attributes' => ['class' => ['mh-tasks']]];
    foreach ($rows as $row) {
      $build['tasks'][$row['id']] = $this->card($row);
    }
    if (count($rows) >= 200) {
      $build['more'] = ['#markup' => '<p>' . $this->t('Showing the first 200.') . '</p>'];
    }
    return $build;
  }

  /**
   * One task card.
   */
  protected function card(array $row): array {
    $contact = ['email' => '', 'phone' => '', 'address' => []];
    if ($row['target_id']) {
      $contact = $this->activities->contactCard($row['target_id']);
    }
    $date = $row['date'] !== '' ? date('D, M j, g:ia', strtotime($row['date'])) : '';
    $title = $row['subject'] !== '' ? $row['subject'] : ($row['type'] ?: $this->t('(no subject)'));

    $card = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'task-' . $row['id'],
        'class' => array_filter(['mh-task', $row['overdue'] ? 'mh-task--overdue' : '']),
      ],
    ];
    $card['head'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="mh-task__meta">{% if overdue %}<span class="mh-task__flag">{{ "Overdue"|t }}</span> {% endif %}<span>{{ date }}</span> · <span>{{ type }}</span>{% if status %} · <span class="mh-task__status">{{ status }}</span>{% endif %}</div><h3 class="mh-task__title">{{ title }}</h3>',
      '#context' => [
        'overdue' => $row['overdue'],
        'date' => $date,
        'type' => $row['type'],
        'status' => TaskActions::statusLabel($row['status']),
        'title' => $title,
      ],
    ];

    $card['who'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="mh-task__who">'
      . '{% if targets %}<div><strong>{{ targets }}</strong></div>{% endif %}'
      . '{% if email %}<div><a href="mailto:{{ email }}">{{ email }}</a></div>{% endif %}'
      . '{% if phone %}<div><a href="tel:{{ phone }}">{{ phone }}</a></div>{% endif %}'
      . '{% if address %}<div class="mh-task__address"><span class="mh-task__label">{{ "Mailing address"|t }}</span><address>{% for line in address %}{{ line }}{% if not loop.last %}<br>{% endif %}{% endfor %}</address></div>{% endif %}'
      . '{% if source %}<div class="mh-task__source">{{ "Filed by"|t }} {{ source }}</div>{% endif %}'
      . '</div>',
      '#context' => [
        'targets' => implode(', ', array_slice($row['targets'], 0, 3)) . (count($row['targets']) > 3 ? ' +' . (count($row['targets']) - 3) : ''),
        'email' => $contact['email'],
        'phone' => $contact['phone'],
        'address' => $contact['address'],
        'source' => $row['source'],
      ],
    ];

    if (trim(strip_tags($row['details'])) !== '') {
      $card['details'] = [
        '#type' => 'details',
        '#title' => $this->t('Details'),
        '#open' => mb_strlen(strip_tags($row['details'])) < 600,
        '#attributes' => ['class' => ['mh-task__details']],
        'body' => ['#markup' => Xss::filterAdmin($row['details'])],
      ];
    }

    $links = [];
    if ($mailto = TaskActions::mailto($contact['email'], $title)) {
      $links[] = '<a class="mh-task__reply" href="' . htmlspecialchars($mailto, ENT_QUOTES) . '">' . $this->t('Reply by email') . '</a>';
    }
    $links[] = '<a class="mh-task__civi" href="' . htmlspecialchars($row['url'], ENT_QUOTES) . '">' . $this->t('Open in CiviCRM') . '</a>';
    $card['links'] = ['#markup' => '<div class="mh-task__links">' . implode(' · ', $links) . '</div>'];

    $form = $this->classResolver()->getInstanceFromDefinition(AssignedTaskForm::class)->setActivityId($row['id']);
    $card['form'] = $this->formBuilder()->getForm($form, $row['id']);
    return $card;
  }

  /**
   * The class resolver, which ControllerBase does not expose.
   */
  protected function classResolver() {
    return \Drupal::classResolver();
  }

}
