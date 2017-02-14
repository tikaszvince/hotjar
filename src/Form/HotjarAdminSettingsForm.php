<?php

namespace Drupal\hotjar\Form;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hotjar settings for this site.
 */
class HotjarAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hotjar_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hotjar.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = hotjar_get_settings();

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];

    $form['general']['hotjar_account'] = [
      '#default_value' => $settings['account'],
      '#description' => $this->t('Your Hotjar ID can be found in your tracking code on the line <code>h._hjSettings={hjid:<b>12345</b>,hjsv:5};</code> where <code><b>12345</b></code> is your Hotjar ID'),
      '#maxlength' => 20,
      '#required' => TRUE,
      '#size' => 15,
      '#title' => $this->t('Hotjar ID'),
      '#type' => 'textfield',
    ];

    $visibility = $settings['visibility_pages'];
    $pages = $settings['pages'];

    // Visibility settings.
    $form['tracking']['page_track'] = [
      '#type' => 'details',
      '#title' => $this->t('Pages'),
      '#group' => 'tracking_scope',
      '#open' => TRUE,
    ];

    if ($visibility == 2) {
      $form['tracking']['page_track'] = [];
      $form['tracking']['page_track']['hotjar_visibility_pages'] = ['#type' => 'value', '#value' => 2];
      $form['tracking']['page_track']['hotjar_pages'] = ['#type' => 'value', '#value' => $pages];
    }
    else {
      $options = [
        t('Every page except the listed pages'),
        t('The listed pages only'),
      ];
      $description_args = [
        '%blog' => 'blog',
        '%blog-wildcard' => 'blog/*',
        '%front' => '<front>',
      ];
      $description = $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.", $description_args);
      $title = $this->t('Pages');

      $form['tracking']['page_track']['hotjar_visibility_pages'] = [
        '#type' => 'radios',
        '#title' => $this->t('Add tracking to specific pages'),
        '#options' => $options,
        '#default_value' => $visibility,
      ];
      $form['tracking']['page_track']['hotjar_pages'] = [
        '#type' => 'textarea',
        '#title' => $title,
        '#title_display' => 'invisible',
        '#default_value' => $pages,
        '#description' => $description,
        '#rows' => 10,
      ];
    }

    // Render the role overview.
    $visibility_roles = $settings['roles'];

    $form['tracking']['role_track'] = [
      '#type' => 'details',
      '#title' => $this->t('Roles'),
      '#group' => 'tracking_scope',
      '#open' => TRUE,
    ];

    $form['tracking']['role_track']['hotjar_visibility_roles'] = [
      '#type' => 'radios',
      '#title' => $this->t('Add tracking for specific roles'),
      '#options' => [
        $this->t('Add to the selected roles only'),
        $this->t('Add to every role except the selected ones'),
      ],
      '#default_value' => $settings['visibility_roles'],
    ];
    $role_options = array_map(['\Drupal\Component\Utility\SafeMarkup', 'checkPlain'], user_role_names());
    $form['tracking']['role_track']['hotjar_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#default_value' => !empty($visibility_roles) ? $visibility_roles : [],
      '#options' => $role_options,
      '#description' => $this->t('If none of the roles are selected, all users will be tracked. If a user has any of the roles checked, that user will be tracked (or excluded, depending on the setting above).'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Trim some text values.
    $form_state->setValue('hotjar_account', trim($form_state->getValue('hotjar_account')));
    $form_state->setValue('hotjar_pages', trim($form_state->getValue('hotjar_pages')));
    $form_state->setValue('hotjar_roles', array_filter($form_state->getValue('hotjar_roles')));

    // Verify that every path is prefixed with a slash.
    if ($form_state->getValue('hotjar_visibility_pages') != 2) {
      $pages = preg_split('/(\r\n?|\n)/', $form_state->getValue('hotjar_pages'));
      foreach ($pages as $page) {
        if (strpos($page, '/') !== 0 && $page !== '<front>') {
          $form_state->setErrorByName('hotjar_pages', t('Path "@page" not prefixed with slash.', ['@page' => $page]));
          // Drupal forms show one error only.
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('hotjar.settings');
    $config
      ->set('account', $form_state->getValue('hotjar_account'))
      ->set('visibility_pages', $form_state->getValue('hotjar_visibility_pages'))
      ->set('pages', $form_state->getValue('hotjar_pages'))
      ->set('visibility_roles', $form_state->getValue('hotjar_visibility_roles'))
      ->set('roles', $form_state->getValue('hotjar_roles'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
