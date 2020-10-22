<?php

namespace Drupal\bootstrap_quicktabs\Plugin\TabRenderer;

use Drupal\quicktabs\TabRendererBase;
use Drupal\quicktabs\Entity\QuickTabsInstance;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a 'Bootstrap Accordion' tab renderer.
 *
 * @TabRenderer(
 *   id = "bootstrap_accordion",
 *   name = @Translation("bootstrap accordion"),
 * )
 */
class BootstrapAccordion extends TabRendererBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function render(QuickTabsInstance $instance) {
    $qt_id = $instance->id();
    $type = \Drupal::service('plugin.manager.tab_type');

    // The render array used to build the block.
    $build = [];

    // Add a wrapper.
    $build['#theme_wrappers'] = [
      'container' => [
        '#attributes' => [
          'class' => ['panel-group'],
          'id' => 'panel-group-' . $qt_id,
        ],
      ],
    ];

    $panels = [];
    foreach ($instance->getConfigurationData() as $index => $tab) {

      $qsid = 'quickset-' . $qt_id;
      $object = $type->createInstance($tab['type']);
      $render = $object->render($tab);
      $panel_id = $qsid . '-' . $index;

      // If user wants to hide empty tabs and there is no content
      // then skip to next tab.
      if ($instance->getHideEmptyTabs() && empty($render)) {
        continue;
      }

      $active_tab = $instance->getDefaultTab() == 9999 ? 0 : $instance->getDefaultTab();
      $panel_class = '';
      if ($active_tab == $index) {
        $panel_class = 'in';
      }

      if (!empty($tab['content'][$tab['type']]['options']['display_title']) && !empty($tab['content'][$tab['type']]['options']['block_title'])) {
        $build['pages'][$index]['#title'] = $tab['content'][$tab['type']]['options']['block_title'];
      }

      $panel = [
        'id' => $panel_id,
        'classes' => $panel_class,
        'title' => new TranslatableMarkup($tab['title']),
        'content' => render($render),
      ];

      $panels["$panel_id"] = $panel;
    }

    $build['#theme'] = 'bootstrap_accordion';
    $build['#panels'] = $panels;
    $build['#id'] = $qt_id;
    return $build;
  }

}
