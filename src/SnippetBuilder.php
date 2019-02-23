<?php

namespace Drupal\hotjar;

use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SnippetBuilder.
 *
 * @package Drupal\hotjar
 */
class SnippetBuilder implements SnippetBuilderInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * JS collection optimizer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionOptimizerInterface
   */
  protected $jsCollectionOptimizer;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * SnippetBuilder constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   * @param \Drupal\Core\Asset\AssetCollectionOptimizerInterface $js_collection_optimizer
   *   JS assets optimizer.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    AssetCollectionOptimizerInterface $js_collection_optimizer,
    MessengerInterface $messenger
  ) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->jsCollectionOptimizer = $js_collection_optimizer;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('asset.js.collection_optimizer'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createAssets() {
    $result = TRUE;
    $directory = 'public://hotjar';
    if (!is_dir($directory) || !is_writable($directory)) {
      $result = file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    }
    if ($result) {
      $result = $this->saveSnippets();
    }
    else {
      $this->messenger->addWarning($this->t('Failed to create or make writable the directory %directory, possibly due to a permissions problem. Make the directory writable.', ['%directory' => $directory]));
    }
    return $result;
  }

  /**
   * Saves JS snippet files based on current settings.
   *
   * @return bool
   *   Whether the files were saved.
   */
  protected function saveSnippets() {
    $settings = hotjar_get_settings();
    $snippet = $this->buildSnippet($settings['account'], $settings['snippet_version']);
    $path = file_unmanaged_save_data($snippet, 'public://hotjar/hotjar.script.js', FILE_EXISTS_REPLACE);

    if ($path === FALSE) {
      $this->messenger->addMessage($this->t('An error occurred saving one or more snippet files. Please try again or contact the site administrator if it persists.'));
      return FALSE;
    }

    $this->messenger->addMessage($this->t('Created snippet file based on configuration.'));
    $this->jsCollectionOptimizer->deleteAll();
    _drupal_flush_css_js();

    return TRUE;
  }

  /**
   * Get Hotjar snippet code.
   *
   * @param string $id
   *   Hotjar account ID.
   * @param string $version
   *   Hotjar API version.
   *
   * @return mixed|string
   *   Hotjar snippet.
   */
  protected function buildSnippet($id, $version) {
    // Use escaped HotjarID.
    $clean_id = json_encode($id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $clean_version = json_encode($version, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    // Quote from the Hotjar dashboard:
    // The Tracking Code below should be placed in the <head> tag of
    // every page you want to track on your site.
    $script = <<<HJ
(function(h,o,t,j,a,r){
  h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
  h._hjSettings={hjid:{$clean_id},hjsv:{$clean_version}};
  a=o.getElementsByTagName('head')[0];
  r=o.createElement('script');r.async=1;
  r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
  a.appendChild(r);
})(window,document,'//static.hotjar.com/c/hotjar-','.js?sv=');
HJ;

    // Compact script if core aggregation or advagg module are enabled.
    if (
      $this->isJsPreprocessEnabled()
      || $this->moduleHandler->moduleExists('advagg')
    ) {
      $script = str_replace(["\n", '  '], '', $script);
    }

    return $script;
  }

  /**
   * Checks if JS preprocess is enabled.
   *
   * @return bool
   *   Returns TRUE if JS preprocess is enabled.
   */
  protected function isJsPreprocessEnabled() {
    $config = $this->configFactory->get('system.performance');
    return $config->get('js.preprocess', TRUE) === TRUE;
  }

}