<?php

/**
* Export & Import WP Super Cache settings.
*
* This class handles the exporting and importing of WP Super Cache settings. Exports are downloaded
* as a JSON file with properties that mirror the WP Super Cache configuration file. Imports are handled
* by uploading an exported JSON file and overwriting the WP Super Cache configuration file with the
* imported parameters. A backup of the original configuration file is kept.
*
* @package  WP Super Cache
* @since 1.4.4
*/

class WP_Super_Cache_Export {

  const TITLE = 'WP_Super_Cache_Export.json';

  const NAME = '_wp_super_cache_export';

  const IMPORT_NONCE = 'wp-super-cache-import';

  const EXPORT_NONCE = 'wp-super-cache-export';

  /**
   * Instantiate the class to enable the importing and exporting features for the WP Super Cache plugin.
   *
   * Caches the location of the WP Super Cache plugin configuration file and the sample configuration file.
   * Adds two actions to the WP Super Cache admin page load that determine if an import or export
   * should be preformed.
   *
   * @uses  $wp_cache_config_file The location of the configuration file.
   * @uses  $wp_cache_config_file_sample The location of the sample configuration file.
   *
   * @since  1.4.4
   */

  function __construct() {
    global $wp_cache_config_file, $wp_cache_config_file_sample;
    $this->cache_config_file = $wp_cache_config_file;
    $this->cache_config_file_sample = $wp_cache_config_file;
    add_action( 'load-settings_page_wpsupercache', array( $this, 'export' ) );
    add_action( 'load-settings_page_wpsupercache', array( $this, 'import' ) );
  }

  /**
   *  The form HTML that shows in the Import/Export tab of the WP Super Cache plugin admin page.
   *
   * Provides a basic form for exporting the plugin's settings.
   * Provides a basic upload field for importing settings.
   *
   * A success message is displayed if new settings have been imported correctly.
   * An error message is displayed if the settings cannot be imported.
   *
   *  @since  1.4.4
   */
  public function form() {
    ?>
    <?php if ( isset( $_GET['success'] ) ) : ?>
        <div class="updated notice notice-success is-dismissible below-h2">
            <p><?php _e( 'Settings imported', 'wp-super-cache' ) ?> </p>
            <button type="button" class="notice-dismiss">
              <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
          </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['error'] ) ) : ?>
        <div class="error notice is-dismissible below-h2">
            <p><?php _e( 'Please upload a file with the correct JSON format.', 'wp-super-cache' ) ?> </p>
            <button type="button" class="notice-dismiss">
              <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
          </div>
    <?php endif; ?>

      <fieldset class="options">
        <h3><?php _e( "Export WP Super Cache Settings", "wp-super-cache" ) ?></h3>
        <p><?php  _e( "Export the WP Super Cache Settings to transfer them to another WordPress site.", "wp-super-cache" ) ?></p>

        <form action="" method="post">
          <input type="hidden" name="<?php echo self::NAME ?>" value="export" />
          <?php wp_nonce_field( self::EXPORT_NONCE ); ?>
          <?php submit_button( __( 'Export settings', 'wp-super-cache' ) ); ?>
        </form>

        <hr>

        <h3><?php _e( "Import WP Super Cache Settings", "wp-super-cache" ) ?></h3>
        <p><?php _e( "Import the WP Super Cache Settings to from another WordPress site. This file must be a json format and exported from a WP Super Cache plugin.", "wp-super-cache" ) ?></p>

        <form action="" method="post" enctype="multipart/form-data">
          <?php wp_nonce_field( self::IMPORT_NONCE ); ?>
          <input type="hidden" name="<?php echo self::NAME ?>" value="import"  />
          <input type="file" name="wp_super_cache_import_file"/>
          <?php submit_button( __( 'Import settings', 'wp-super-cache' ) ); ?>
        </form>

      </fieldset>

    <?php
  }

  /**
   * Imports settings into the WP Super Cache plugin while removing all current settings.
   *
   * When a user uploads a JSON file exported via the WP Super Cache plugin the current configuration file is moved
   * and replaced by a copy of the default, sample configuration file. This file is then updated using the `wp_cache_replace_line`
   * function with the parameters supplied by the uploaded JSON file.
   *
   * Currently checks if the JSON file exists. If not, render the Import/Export tab with an error message.
   * Currently checks if the JSON file has settings in it. If not, render the Import/Export tab with an error message.
   *
   * A backup of the original configuration file is stored in the wp-content directory with a .backup file extension.
   *
   * @uses  check_admin_referer() Checks if the request passes the input nonces
   * @uses  wp_safe_redirect()  Redirects safely to the Import/Export tab with an error or success message
   * @uses  wp_cache_verify_config_file() Creates a new copy of the sample configuration file.
   * @uses  wp_cache_replace_line() Updates the default configuration file with the uploaded settings
   *
   * @since  1.4.4
   *
   */

  public function import() {
    if ( ! $this->canImport() ) {
      return;
    }
    check_admin_referer( self::IMPORT_NONCE );
    $file = $_FILES[ 'wp_super_cache_import_file' ][ 'tmp_name' ];
    if( empty( $file ) ) {
      wp_safe_redirect( admin_url( 'options-general.php?page=wpsupercache&tab=export&error' ) );
      exit;
    }
    $settings = (array) json_decode( file_get_contents( $file ), true );
    if ( 0 === count( $settings ) ) {
      wp_safe_redirect( admin_url( 'options-general.php?page=wpsupercache&tab=export&error' ) );
      exit;
    }
    // Backup the current config file to the same directory with a .backup file extension.
    if ( file_exists( $this->cache_config_file) ) {
      rename( $this->cache_config_file, $this->cache_config_file . '.backup' );
    }
    // Create a new config file from the original sample
    wp_cache_verify_config_file();
    foreach ( $settings as $setting => $value) {
      if ( is_array( $value )  ) {
        // todo: this specific setting outlier could be avoided if the initial config file was adjusted slightly
        if ( $setting === 'wp_cache_pages' ) {
          foreach ($value as $key => $key_value ) {
            $key_value = is_numeric($key_value) ? $key_value : "\"$key_value\"";
            wp_cache_replace_line( '^ *\$' . $setting . '\[ "' . $key . '" \]' ,"\$" . $setting . "[ \"" . $key . "\" ] = $key_value;", $this->cache_config_file );
          }
        } else {
            $text = wp_cache_sanitize_value( join( $value, ' ' ), $value );
            wp_cache_replace_line( '^ *\$' . $setting. ' =', "\$$setting = $text;", $this->cache_config_file );
        }
      } else {
        $value = is_numeric($value) ? $value : "\"$value\"";
        wp_cache_replace_line( '^ *\$' . $setting. ' =', "\$$setting = $value;", $this->cache_config_file );
      }
    }
    wp_safe_redirect( admin_url( 'options-general.php?page=wpsupercache&tab=export&success' ) );
    exit;
  }

  /**
   * Exports the current settings into a JSON file that can be imported into another WP Super Cache.
   *
   * When a user clicks the export button WordPress checks if the request is valid.
   * No variables are declared in the scope of this function. When the configuration file is included
   * its variables are the only ones that are declared. These variables are then encoded and downloaded
   * as a JSON file.
   *
   * @since  1.4.4
   *
   * @uses  check_admin_referer
   * @uses  nocache_header()
   *
   * @return JSON file of the WP Super Cache settings
   */
  public function export() {
    if ( ! $this->canExport() || 0 !== count( get_defined_vars() ) ) {
      return;
    }
    check_admin_referer(  self::EXPORT_NONCE );
    include $this->cache_config_file;
    $wp_cache_config_vars = get_defined_vars();
    nocache_headers();
    header( "Content-disposition: attachment; filename=" . self::TITLE );
    header( 'Content-Type: application/octet-stream; charset=' . get_option( 'blog_charset' ) );
    echo json_encode( $wp_cache_config_vars );
    die();
  }

  /**
   * This is a private function that determines if the user can export the WP Super Cache plugin settings.
   *
   * The $_POST variable and user permissions are checked.
   * If either fail the user cannot export the settings.
   *
   * @since  1.4.4
   *
   * @uses  current_user_can
   *
   * @return boolean Whether the user can export the settings.
   */
  private function canExport() {
    return isset( $_POST[ self::NAME ] ) &&
                $_POST[ self::NAME ] === 'export' &&
                current_user_can( 'manage_options' );
  }


  /**
   * This is a private function that determines if the user can import WP Super Cache plugin settings.
   *
   * The $_POST variable and user permissions are checked.
   * If either fail the user cannot import the settings.
   *
   * @since  1.4.4
   *
   * @uses  current_user_can
   *
   * @return boolean Whether the user can import the settings.
   */
  private function canImport() {
    return isset( $_POST[ self::NAME ] ) &&
                $_POST[ self::NAME ] === 'import' &&
                current_user_can( 'manage_options' );
  }

}

new WP_Super_Cache_Export();