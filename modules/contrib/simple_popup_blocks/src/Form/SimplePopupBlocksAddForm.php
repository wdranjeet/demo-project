<?php

namespace Drupal\simple_popup_blocks\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_popup_blocks\SimplePopupBlocksStorage;

/**
 * Form to add a popup entry.
 */
class SimplePopupBlocksAddForm implements FormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_popup_blocks_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $block_ids = \Drupal::entityQuery('block')->execute();
    $form = [];
    for ($i = 0; $i < 101; $i++) {
      $visit_counts[] = $i;
    }

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this
        ->t('Choose the identifier'),
      '#default_value' => 0,
      '#options' => [
        0 => $this
          ->t('Drupal blocks'),
        1 => $this
          ->t('Custom css id or class'),
      ],
    ];
    $form['block_list'] = [
      '#type' => 'select',
      '#title' => t("Blocks list"),
      '#options' => $block_ids,
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 0],
        ],
      ],
    ];
    $form['custom_css'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selectors without # or .'),
      '#default_value' => t("custom-css-id"),
      '#description' => $this->t("Ex: my-profile, custom_div_cls, someclass, mypopup-class."),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 1],
        ],
      ],
    ];
    $form['css_selector'] = [
      '#type' => 'radios',
      '#title' => $this
        ->t('Css selector'),
      '#default_value' => 1,
      '#options' => [
        0 => $this
          ->t('Css class (.)'),
        1 => $this
          ->t('Css id (#)'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 1],
        ],
      ],
    ];
    $form['layout'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose layout'),
      '#default_value' => 0,
      '#options' => [
        0 => $this
          ->t('Top left'),
        1 => $this
          ->t('Top Right'),
        2 => $this
          ->t('Bottom left'),
        3 => $this
          ->t('Bottom Right'),
        4 => $this
          ->t('Center'),
        5 => $this
          ->t('Top center'),
        6 => $this
          ->t('Top bar'),
        7 => $this
          ->t('Bottom bar'),
        8 => $this
          ->t('Left bar'),
        9 => $this
          ->t('Right bar'),
      ],
    ];
    $form['visit_counts'] = [
      '#title' => $this->t('Visit counts'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#size' => 8,
      '#options' => $visit_counts,
      '#default_value' => 0,
      '#description' => $this->t("Examples:<br>
        0 = Show popup on users each visit<br> 
        1,2 = Show popup on users first and second visit<br>
        1,4,7 = Show popup on users first, forth and seventh visit"),
    ];
    $form['minimize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Minimize button'),
      '#default_value' => 1,
    ];
    $form['close'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Close button'),
      '#default_value' => 1,
    ];
    $form['escape'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('ESC key to close'),
      '#default_value' => 1,
    ];
    $form['overlay'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Overlay'),
      '#default_value' => 1,
    ];
    $form['trigger_method'] = [
      '#type' => 'radios',
      '#title' => $this
        ->t('Trigger method'),
      '#default_value' => 0,
      '#options' => [
        0 => $this
          ->t('Automatic'),
        1 => $this
          ->t('Manual - on click event'),
        2 => $this
          ->t('Before browser/tab close'),
      ],
    ];
    $form['delay'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delays'),
      '#size' => 5,
      '#default_value' => 0,
      '#description' => $this->t("Show popup after this seconds. 0 will show immediately after the page load."),
      '#states' => [
        'visible' => [
          ':input[name="trigger_method"]' => ['value' => 0],
        ],
      ],
    ];
    $form['trigger_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add css id or class starting with # or .'),
      '#default_value' => t("#custom-css-id"),
      '#description' => $this->t("Ex: #my-profile, #custom_div_cls, .someclass, .mypopup-class."),
      '#states' => [
        'visible' => [
          ':input[name="trigger_method"]' => ['value' => 1],
        ],
      ],
    ];
    $form['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#default_value' => 400,
      '#description' => $this->t("Add popup width in pixels"),
    ];
    $form['adjustments'] = [
      '#type' => 'details',
      '#title' => $this->t('Adjustment class'),
      '#open' => TRUE,
      '#description' => $this->t("Once you created, you can see the css selectors to customize the popup designs."),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Convert to popup'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('type') == 0) {
      $selector = 'block_list';
      $identifier = $form_state->getValue($selector);
    }
    else {
      $selector = 'custom_css';
      $identifier = $form_state->getValue($selector);
    }
    if (!isset($identifier) || empty($identifier)) {
      $form_state->setError($form[$selector], $this->t('You should provide some css selector.'));
    }
    if ($form_state->getValue('type') == 1) {
      // Get the first character using substr.
      $firstCharacter = substr($identifier, 0, 1);
      if (in_array($firstCharacter, ['.', '#'])) {
        $form_state->setError($form[$selector], $this->t('Selector should not start with . or # in %field.', ['%field' => $identifier]));
      }
    }
    $check = SimplePopupBlocksStorage::loadCountByIdentifier($identifier);
    if ($check) {
      $form_state->setError($form[$selector], $this->t('Already popup created with this identifier %field.', ['%field' => $identifier]));
    }
    $trigger_method = $form_state->getValue('trigger_method');
    if ($trigger_method == 1) {
      $trigger_selector = $form_state->getValue('trigger_selector');
      // Get the first character using substr.
      $firstCharacter = substr($trigger_selector, 0, 1);
      if (!in_array($firstCharacter, ['.', '#'])) {
        $form_state->setError($form['trigger_selector'], $this->t('Selector should start with . or # in %field.', ['%field' => $trigger_selector]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('type') == 0) {
      $identifier = $form_state->getValue('block_list');
    }
    else {
      $identifier = $form_state->getValue('custom_css');
    }
    $visit_counts = serialize($form_state->getValue('visit_counts'));
    $delay = $form_state->getValue('delay');
    if (empty($delay) || $delay < 0) {
      $delay = 0;
    }
    $width = $form_state->getValue('width');
    if (empty($width) || $width < 0) {
      $width = 400;
    }
    // Save the submitted entry.
    $entry = [
      'identifier' => $identifier,
      'type' => $form_state->getValue('type'),
      'css_selector' => $form_state->getValue('css_selector'),
      'layout' => $form_state->getValue('layout'),
      'visit_counts' => $visit_counts,
      'overlay' => $form_state->getValue('overlay'),
      'trigger_method' => $form_state->getValue('trigger_method'),
      'trigger_selector' => $form_state->getValue('trigger_selector'),
      'escape' => $form_state->getValue('escape'),
      'delay' => $delay,
      'minimize' => $form_state->getValue('minimize'),
      'close' => $form_state->getValue('close'),
      'width' => $width,
      'status' => 1,
    ];
    $return = SimplePopupBlocksStorage::insert($entry);
    if ($return) {
      drupal_set_message($this->t('Popup settings has been created Successfully. Please place the css code mentioned below under the ADJUSTMENT CLASS Section.'));
      $url = Url::fromRoute('simple_popup_blocks.edit')
        ->setRouteParameters(['first' => $return]);
      $form_state->setRedirectUrl($url);
    }
    else {
      drupal_set_message($this->t('Error while creating.'), 'error');
    }
  }

}
