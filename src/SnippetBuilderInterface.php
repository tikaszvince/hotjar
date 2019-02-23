<?php

namespace Drupal\hotjar;

/**
 * Interface SnippetBuilderInterface.
 *
 * @package Drupal\hotjar
 */
interface SnippetBuilderInterface {

  /**
   * Prepares directory for and saves snippet files based on current settings.
   *
   * @return bool
   *   Whether the files were saved.
   */
  public function createAssets();

}
