<?php

declare(strict_types=1);

namespace Drupal\makerspace_user_links\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\makerspace_user_links\AssignedActivities\AssignedActivities;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the open CiviCRM activities assigned to the viewer (/assigned-to-me).
 *
 * CiviCRM's own activity lists mix these with every record filed under the
 * person's name; this page shows only what is still to do.
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
    ];
    $contactId = $this->activities->contactId($this->currentUser());
    if (!$contactId) {
      $build['empty'] = ['#markup' => '<p>' . $this->t('Your account is not linked to a CiviCRM contact, so nothing can be assigned to you.') . '</p>'];
      return $build;
    }

    $rows = $this->activities->open($contactId);
    $build['intro'] = [
      '#markup' => '<p>' . $this->t('CiviCRM activities assigned to you that are not marked Completed or Cancelled, soonest first. Filed emails, tours and orientations are recorded already completed, so they do not appear. You get an email when something new is assigned to you.') . '</p>',
    ];

    $table = [];
    foreach ($rows as $row) {
      $date = $row['date'] !== '' ? date('M j, Y g:ia', strtotime($row['date'])) : '';
      $table[] = [
        'class' => $row['overdue'] ? ['mh-assigned--overdue'] : [],
        'data' => [
          ['data' => $row['overdue'] ? ['#markup' => '<strong>' . $this->t('Overdue') . '</strong><br>' . $date] : $date],
          $row['type'],
          Link::fromTextAndUrl($row['subject'] !== '' ? $row['subject'] : $this->t('(no subject)'), Url::fromUri($row['url'])),
          implode(', ', array_slice($row['targets'], 0, 3)) . (count($row['targets']) > 3 ? ' +' . (count($row['targets']) - 3) : ''),
          $row['source'],
        ],
      ];
    }
    $build['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Due'), $this->t('Type'), $this->t('Subject'), $this->t('With'), $this->t('Filed by')],
      '#rows' => $table,
      '#empty' => $this->t('Nothing is assigned to you right now.'),
    ];
    if (count($rows) >= 200) {
      $build['more'] = ['#markup' => '<p>' . $this->t('Showing the first 200.') . '</p>'];
    }
    return $build;
  }

}
