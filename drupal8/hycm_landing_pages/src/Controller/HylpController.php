<?php

namespace Drupal\hycm_landing_pages\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\hycm_landing_pages\Entity\HylpInterface;

/**
 * Class HylpController.
 *
 *  Returns responses for Landing page routes.
 */
class HylpController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Landing page  revision.
   *
   * @param int $hylp_revision
   *   The Landing page  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($hylp_revision) {
    $hylp = $this->entityManager()->getStorage('hylp')->loadRevision($hylp_revision);
    $view_builder = $this->entityManager()->getViewBuilder('hylp');

    return $view_builder->view($hylp);
  }

  /**
   * Page title callback for a Landing page  revision.
   *
   * @param int $hylp_revision
   *   The Landing page  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($hylp_revision) {
    $hylp = $this->entityManager()->getStorage('hylp')->loadRevision($hylp_revision);
    return $this->t('Revision of %title from %date', ['%title' => $hylp->label(), '%date' => format_date($hylp->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Landing page .
   *
   * @param \Drupal\hycm_landing_pages\Entity\HylpInterface $hylp
   *   A Landing page  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(HylpInterface $hylp) {
    $account = $this->currentUser();
    $langcode = $hylp->language()->getId();
    $langname = $hylp->language()->getName();
    $languages = $hylp->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $hylp_storage = $this->entityManager()->getStorage('hylp');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $hylp->label()]) : $this->t('Revisions for %title', ['%title' => $hylp->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all landing page revisions") || $account->hasPermission('administer landing page entities')));
    $delete_permission = (($account->hasPermission("delete all landing page revisions") || $account->hasPermission('administer landing page entities')));

    $rows = [];

    $vids = $hylp_storage->revisionIds($hylp);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\hycm_landing_pages\HylpInterface $revision */
      $revision = $hylp_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $hylp->getRevisionId()) {
          $link = $this->l($date, new Url('entity.hylp.revision', ['hylp' => $hylp->id(), 'hylp_revision' => $vid]));
        }
        else {
          $link = $hylp->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => ['#markup' => $revision->getRevisionLogMessage(), '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.hylp.translation_revert', ['hylp' => $hylp->id(), 'hylp_revision' => $vid, 'langcode' => $langcode]) :
              Url::fromRoute('entity.hylp.revision_revert', ['hylp' => $hylp->id(), 'hylp_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.hylp.revision_delete', ['hylp' => $hylp->id(), 'hylp_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['hylp_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
