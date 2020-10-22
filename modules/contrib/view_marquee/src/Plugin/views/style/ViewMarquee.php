<?php

namespace Drupal\view_marquee\Plugin\views\style;

use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\core\form\FormStateInterface;

/**
 * Style plugin for the marquee view.
 *
 * @ViewsStyle(
 *   id = "view_marquee",
 *   title = @Translation("View Marquee"),
 *   help = @Translation("Displays content in marquee."),
 *   theme = "views_view_view_marquee",
 *   display_types = {"normal"}
 * )
 */
class ViewMarquee extends StylePluginBase {

  /**
   * {@inheritdoc}
   */

  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['direction'] = ['default' => 'left'];
    $options['behavior'] = ['default' => 'scroll'];
    $options['mouseover'] = ['default' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Field direction.
    $form['row_class'] = [
      '#type' => 'textfield',
      '#title' => t('Row class'),
      '#default_value' => (isset($this->options['row_class'])) ? $this->options['row_class'] : 'marquee-row',
      '#description' => t('The class to provide on each row. You may use field tokens from as per the "Replacement patterns" used in "Rewrite the output of this field" for all fields. .'),
    ];
    $form['direction'] = [
      '#title' => $this->t('Direction'),
      '#type' => 'select',
      '#options' => [
        'left' => $this->t('Left'),
        'up' => $this->t('Up'),
        'right' => $this->t('Right'),
        'down' => $this->t('Down'),
      ],
      '#default_value' => $this->options['direction'],
      '#description' => t('Sets the direction for the scrolling content.'),
    ];
    $form['behavior'] = [
      '#title' => $this->t('Behavior'),
      '#type' => 'select',
      '#options' => [
        'scroll' => $this->t('Scroll'),
        'alternate' => $this->t('Alternate'),
      ],
      '#default_value' => $this->options['behavior'],
      '#description' => t('Defines the scrolling type.'),
    ];
    $form['speed'] = [
      '#title' => $this->t('Scroll speed'),
      '#type' => 'textfield',
      '#default_value' => $this->options['speed'],
      '#description' => t('Defines the scrolling amount at each interval in pixels.'),
    ];
    $form['delay'] = [
      '#title' => $this->t('Scroll delay'),
      '#type' => 'textfield',
      '#default_value' => $this->options['delay'],
      '#description' => t('Defines how long delay will be between each jump.'),
    ];
    $form['mouseover'] = [
      '#title' => $this->t('onMouseOver / onMouseOut'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['mouseover'],
    ];

  }

}
