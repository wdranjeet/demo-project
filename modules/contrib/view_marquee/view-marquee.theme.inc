<?php

/**
 * @file
 * Theme for View Marquee views.
 */

  /**
   * {@inheritdoc}
   */
function template_preprocess_views_view_view_marquee(&$variables) {

  $options = $variables['view']->style_plugin->options;
  $variables['row_class'] = !empty($options['row_class']) ? 'class=' . $options['row_class'] : 'class=marquee-row';
  $variables['direction'] = !empty($options['direction']) ? 'direction=' . $options['direction'] : '';
  $variables['behavior'] = !empty($options['behavior']) ? 'behavior=' . $options['behavior'] : '';
  $variables['speed'] = !empty($options['speed']) ? 'scrollamount=' . $options['speed'] : 'scrollamount=1';
  $variables['delay'] = !empty($options['delay']) ? 'scrolldelay=' . $options['delay'] : 'scrolldelay=1';

  $variables['mouseover'] = !empty($options['mouseover']) ? 'onmouseover=this.stop(); onmouseout=this.start();' : '';
  $variables['options'] = $options;
}
