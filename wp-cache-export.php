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

  const RESTORE_NONCE = 'wp-super-cache-restore';

  const REMOVE_NONCE = 'wp-super-cache-remove';

  const OPTIONS = array(
    'ossdl_cname',
    'ossdl_https',
    'ossdl_off_cdn_url',
    'ossdl_off_include_dirs',
    'ossdl_off_exclude',
    'preload_cache_counter',
    'wpsupercache_start',
    'wpsupercache_count',
    'supercache_last_cached',
    'supercache_stats',
    'wpsupercache_gc_time',
  );

  static $cache_config_file;

  static $cache_config_file_sample;

  static $MESSAGES = array();

  /**
   * Instantiate the class to enable the importing and exporting features for the WP Super Cache plugin.
   *
   * Caches the location of the WP Super Cache plugin configuration file and the sample configuration file.
   * Adds two actions to the WP Super Cache admin page load that determine if an import or export
   * should be preformed.
   *
   * Defines the static $MESSAGES array that displays the success or error messages in the upload form.
   *
   * @uses  $wp_cache_config_file The location of the configuration file.
   * @uses  $wp_cache_config_file_sample The location of the sample configuration file.
   *
   * @since  1.4.4
   */

  function __construct() {
    global $wp_cache_config_file, $wp_cache_config_file_sample;

    self::$cache_config_file = $wp_cache_config_file;
    self::$cache_config_file_sample = $wp_cache_config_file;
    self::$MESSAGES = array(
      0 =>  array( 'success', __( 'Settings imported', 'wp-super-cache' ) ),
      1 =>  array( 'warning', __( 'Please upload an exported WP Super Cache settings file.', 'wp-super-cache' ) ),
      2 =>  array( 'error', __( 'Unable to import the uploaded JSON file. Please export the settings and import them again.', 'wp-super-cache' ) ),
      3 =>  array( 'error', __( 'Unable to create backup settings file. Please check that the wp-content folder is writable via the <em>chmod</em> command on the server.', 'wp-super-cache' ) ),
      4 =>  array( 'error', __( 'Unable to remove the backup file. Please check that the wp-content folder is writable via the <em>chmod</em> command on the server.', 'wp-super-cache' ) ),
      5 =>  array( 'success', __( 'All settings have been restored.', 'wp-super-cache' ) ),
      6 =>  array( 'success', __( 'All backup settings have been removed permanently.', 'wp-super-cache' ) ),
    );

    add_action( 'load-settings_page_wpsupercache', array( $this, 'export' ) );
    add_action( 'load-settings_page_wpsupercache', array( $this, 'import' ) );
    add_action( 'load-settings_page_wpsupercache', array( $this, 'restore' ) );
    add_action( 'load-settings_page_wpsupercache', array( $this, 'remove' ) );
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

    <?php if (  isset( $_GET['message'] ) ):  ?>
      <div id="message" class="notice notice-<?php echo self::$MESSAGES[ $_GET['message'] ][0] ; ?> is-dismissible">
        <p><?php echo self::$MESSAGES[ $_GET['message'] ][1]  ?></p>
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

        <?php if ( self::backupFileExists() ) : ?>

          <hr>

          <p>
            <?php _e( "Restore the previous WP Super Cache Settings settings or remove them permanently.", "wp-super-cache" ) ?>
          </p>

          <p>

          <form action="" method="post">
            <input type="hidden" name="<?php echo self::NAME ?>" value="restore"  />
            <?php wp_nonce_field( self::RESTORE_NONCE ); ?>
            <?php submit_button( __( 'Restore settings', 'wp-super-cache' ), 'secondary', 'submit', false ); ?>
          </form>

          <form action="" method="post" style="margin-top:10px;">
            <input type="hidden" name="<?php echo self::NAME ?>" value="remove"  />
            <?php wp_nonce_field( self::REMOVE_NONCE ); ?>
            <?php submit_button( __( 'Remove backup settings', 'wp-super-cache' ), 'delete', 'submit',false ); ?>
          </p>

        <?php endif; ?>

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
   * Currently checks if the JSON file exists. If not, render the Import/Export tab with an error message defined by the $MESSAGES array.
   * Currently checks if the JSON file is malformed. If so, render the Import/Export tab with an error message defined by the $MESSAGES array.
   * If the JSON file is imported correctly, render the Import/Export tab with a success message defined by the $MESSAGES array.
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
    $location = add_query_arg( 'tab', 'export', admin_url( 'options-general.php?page=wpsupercache' ) );
    if( empty( $file ) ) {
      wp_safe_redirect( add_query_arg( 'message', 1, $location ) );
      exit;
    }
    $settings = (array) json_decode( file_get_contents( $file ), true );
    if ( 0 === count( $settings ) ) {
      wp_safe_redirect( add_query_arg( 'message', 2, $location ) );
      exit;
    }
    // Backup the current config file to the same directory with a .backup file extension.
    if ( file_exists( self::$cache_config_file) ) {
      $renamed = @rename( self::$cache_config_file, str_replace( '.php', '-backup.php', self::$cache_config_file ) );
      if ( ! $renamed ) {
        wp_safe_redirect( add_query_arg( 'message', 3, $location ) );
        exit;
      }
    }

    // Update the database options that are not stored in the wp-cache-config.php file
    if ( isset( $settings[ '_wp_super_cache_options' ] ) ) {
      $options = array();
      foreach ( $settings[ '_wp_super_cache_options'] as $key => $value ) {
        $options[ $key ] = get_option( $key );
        update_option( $key, $value );
      }
      update_option( '_wp_super_cache_backup_options', $options );
      unset( $settings[ '_wp_super_cache_options' ] );
    }

    // Create a new config file from the original sample
    wp_cache_verify_config_file();
    foreach ( $settings as $setting => $value) {
      if ( is_array( $value )  ) {
        // todo: this specific setting outlier could be avoided if the initial config file was adjusted slightly
        if ( $setting === 'wp_cache_pages' ) {
          foreach ($value as $key => $key_value ) {
            $key_value = $this->sanitize_value( $key_value );
            wp_cache_replace_line( '^ *\$' . $setting . '\[ "' . $key . '" \]' ,"\$" . $setting . "[ \"" . $key . "\" ] = $key_value;", self::$cache_config_file );
          }
        } else {
            $text = wp_cache_sanitize_value( join( $value, ' ' ), $value );
            wp_cache_replace_line( '^ *\$' . $setting. ' =', "\$$setting = $text;", self::$cache_config_file );
        }
      } else {
        $value = $this->sanitize_value( $value );
        wp_cache_replace_line( '^ *\$' . $setting. ' =', "\$$setting = $value;", self::$cache_config_file );
      }
    }
    wp_safe_redirect( add_query_arg( 'message', 0, $location ) );
    exit;
  }

  /**
   * Exports the current settings into a JSON file that can be imported into another WP Super Cache.
   *
   * When a user clicks the export button WordPress checks if the request is valid.
   * Within the scope of this function, when the configuration file is included
   * its variables are the only ones that are declared and gathered.  The additional options are
   * then retrieved from the database. These variables are then encoded and downloaded
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
    include self::$cache_config_file;
    $wp_cache_config_vars = get_defined_vars();
    $wp_cache_config_options = $this->gather_plugin_options();
    nocache_headers();
    header( "Content-disposition: attachment; filename=" . self::TITLE );
    header( 'Content-Type: application/octet-stream;' );
    echo json_encode( array_merge($wp_cache_config_vars, $wp_cache_config_options )  );
    die();
  }

  public function restore() {
    if ( ! $this->can_restore() ) {
      return;
    }
    check_admin_referer( self::RESTORE_NONCE );
    $location = add_query_arg( 'tab', 'export', admin_url( 'options-general.php?page=wpsupercache' ) );
    $renamed = @rename( str_replace( '.php', '-backup.php', self::$cache_config_file ), self::$cache_config_file );
    if ( ! $renamed ) {
      wp_safe_redirect( add_query_arg( 'message', 4, $location ) );
      exit;
    }
    foreach ( get_option( '_wp_super_cache_backup_options', array() ) as $key => $value ) {
        update_option( $key, $value );
    }
    delete_option( '_wp_super_cache_backup_options' );
    wp_safe_redirect( add_query_arg( 'message', 5, $location ) );
    exit;
  }

  public function remove() {
    if ( ! $this->can_remove() ) {
      return;
    }
    $location = add_query_arg( 'tab', 'export', admin_url( 'options-general.php?page=wpsupercache' ) );
    $file = @unlink( str_replace( '.php', '-backup.php', self::$cache_config_file ) );
    if ( ! $file ) {
      wp_safe_redirect( add_query_arg( 'message', 4, $location ) );
      exit;
    }
    delete_option( '_wp_super_cache_backup_options' );
    wp_safe_redirect( add_query_arg( 'message', 6, $location ) );
    exit;
  }

  /**
   * This is a private function that gathers all the database options WP Super Cache stores outside of the wp-cache-config.php file.
   * The array of option names is stored as a class constant.
   *
   * @since  1.4.4
   *
   * @uses  get_option Gather the array of option key/value pairs.
   *
   * @return array An array of the key/value pairs for the WP Super Cache options stored in the database
   */
  private function gather_plugin_options() {
    $options = array();
    foreach ( self::OPTIONS  as $value ) {
      $options[ $value ] = get_option( $value );
    }
    return array( '_wp_super_cache_options' => $options );
  }

  /**
   * This is a private function dedicated to sanitizing the JSON file input.
   * Given that a malicious JSON file could be uploaded and converted into the plugin settings it is best practice to sanitize
   * the JSON values before converting them.
   *
   * Since only numeric values and strings are accepted inputs we cast integers on numeric values and strip tags/escape
   * the html of string values.
   *
   * @since  1.4.4
   *
   * @uses esc_html Escape the string values
   *
   * @return number|string A sanitized version of the input value.
   */

  private function sanitize_value( $value ) {
    if ( is_numeric( $value ) ) {
      return (int)  $value;
    }
    if ( is_string( $value ) ) {
      $value = esc_html( strip_tags( $value ) );
      return "\"$value\"";
    }
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

  /**
   * This is a private function that determines if the user can restore the backed up WP Super Cache plugin settings.
   *
   * The $_POST variable and user permissions are checked.
   * If either fail the user cannot restore the backup settings.
   *
   * @since  1.4.4
   *
   * @uses  current_user_can
   *
   * @return boolean Whether the user can restore the settings.
   */
  private function can_restore() {
    return isset( $_POST[ self::NAME ] ) &&
                $_POST[ self::NAME ] === 'restore' &&
                $this->backupFileExists() &&
                current_user_can( 'manage_options' );

  }

  /**
   * This is a private function that determines if the user can remove the backed up WP Super Cache plugin settings.
   *
   * The $_POST variable and user permissions are checked.
   * If either fail the user cannot remove the backup settings.
   *
   * @since  1.4.4
   *
   * @uses  current_user_can
   *
   * @return boolean Whether the user can remove the settings.
   */
  private function can_remove() {
    return isset( $_POST[ self::NAME ] ) &&
                $_POST[ self::NAME ] === 'remove' &&
                $this->backupFileExists() &&
                current_user_can( 'manage_options' );

  }

  /**
   * Check to see if the backup file exists.
   *
   * @since  1.4.4
   *
   * @return boolean Whether or not the backup file exists
   */
  private function backupFileExists() {
    return file_exists( str_replace( '.php', '-backup.php', self::$cache_config_file ) );
  }

}

new WP_Super_Cache_Export();