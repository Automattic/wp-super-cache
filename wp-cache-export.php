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

  const FILENAME = 'WP_Super_Cache_Export.json';

  const NAME = '_wp_super_cache_export';

  const IMPORT_NONCE = 'wp-super-cache-import';

  const EXPORT_NONCE = 'wp-super-cache-export';

  const RESTORE_NONCE = 'wp-super-cache-restore';

  const REMOVE_NONCE = 'wp-super-cache-remove';

  const OPTIONS = array(
    'ossdl_cname',
    'ossdl_https',
    'ossdl_off_cdn_url',
    'ossdl_off_exclude',
    'ossdl_off_include_dirs',
    'preload_cache_counter',
    'supercache_last_cached',
    'supercache_stats',
    'wpsupercache_count',
    'wpsupercache_gc_time',
    'wpsupercache_start',
  );

  static $cache_config_file;

  static $cache_config_file_backup;

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
    self::$cache_config_file_backup = str_replace( '.php', '-backup.php', self::$cache_config_file );

    self::$MESSAGES = array(
      0 =>  array( 'success', esc_html__( 'Settings imported', 'wp-super-cache' ) ),
      1 =>  array( 'warning', esc_html__( 'Please upload an exported WP Super Cache settings file.', 'wp-super-cache' ) ),
      2 =>  array( 'error', esc_html__( 'Unable to import the uploaded JSON file. The file is either malformed or empty.', 'wp-super-cache' ) ),
      3 =>  array( 'error', esc_html__( 'Unable to create backup settings file. Please check that the wp-content folder is writable via the <em>chmod</em> command on the server.', 'wp-super-cache' ) ),
      4 =>  array( 'error', esc_html__( 'Unable to remove the backup file. Please check that the wp-content folder is writable via the <em>chmod</em> command on the server.', 'wp-super-cache' ) ),
      5 =>  array( 'success', esc_html__( 'All settings have been restored.', 'wp-super-cache' ) ),
      6 =>  array( 'success', esc_html__( 'All backup settings have been removed permanently.', 'wp-super-cache' ) ),
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
  static function form() {
    ?>

    <?php if (  isset( $_GET['message'] ) ):  ?>
      <div id="message" class="notice notice-<?php echo self::$MESSAGES[ $_GET['message'] ][0] ; ?> is-dismissible">
        <p><?php echo self::$MESSAGES[ $_GET['message'] ][1]  ?></p>
      </div>
    <?php endif; ?>

      <fieldset class="options">
        <h3><?php esc_html_e( "Export WP Super Cache Settings", "wp-super-cache" ) ?></h3>
        <p><?php  esc_html_e( "Export the WP Super Cache Settings to transfer them to another WordPress site.", "wp-super-cache" ) ?></p>

        <form action="" method="post">
          <input type="hidden" name="<?php echo self::NAME ?>" value="export" />
          <?php wp_nonce_field( self::EXPORT_NONCE ); ?>
          <?php submit_button( esc_html__( 'Export settings', 'wp-super-cache' ) ); ?>
        </form>

        <hr>

        <h3><?php esc_html_e( "Import WP Super Cache Settings", "wp-super-cache" ) ?></h3>
        <p><?php esc_html_e( "Import the WP Super Cache Settings to from another WordPress site. This file must be a json format and exported from a WP Super Cache plugin.", "wp-super-cache" ) ?></p>

        <form action="" method="post" enctype="multipart/form-data">
          <?php wp_nonce_field( self::IMPORT_NONCE ); ?>
          <input type="hidden" name="<?php echo self::NAME ?>" value="import"  />
          <input type="file" name="wp_super_cache_import_file"/>
          <?php submit_button( esc_html__( 'Import settings', 'wp-super-cache' ) ); ?>
        </form>

        <?php if ( self::backupFileExists() ) : ?>

          <hr>

          <p>
            <?php esc_html_e( "Restore the previous WP Super Cache settings or remove them permanently.", "wp-super-cache" ) ?>
          </p>

          <p>

          <form action="" method="post">
            <input type="hidden" name="<?php echo self::NAME ?>" value="restore"  />
            <?php wp_nonce_field( self::RESTORE_NONCE ); ?>
            <?php submit_button( esc_html__( 'Restore settings', 'wp-super-cache' ), 'secondary', 'submit', false ); ?>
          </form>

          <form action="" method="post" style="margin-top:10px;">
            <input type="hidden" name="<?php echo self::NAME ?>" value="remove"  />
            <?php wp_nonce_field( self::REMOVE_NONCE ); ?>
            <?php submit_button( esc_html__( 'Remove backup settings', 'wp-super-cache' ), 'delete', 'submit',false ); ?>
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
   * When everything is imported the new settings are loaded and the garbage collection is reinstated.
   *
   * A backup of the original configuration file is stored in the wp-content directory with a .backup file extension.
   *
   * @uses  WP_Super_Cache_Sanitizer Sanitizes and inserts the WP Super Cache settings
   * @uses  check_admin_referer() Checks if the request passes the input nonces
   * @uses  wp_safe_redirect()  Redirects safely to the Import/Export tab with an error or success message
   * @uses  wp_cache_verify_config_file() Creates a new copy of the sample configuration file.
   * @uses  wp_cache_replace_line() Updates the default configuration file with the uploaded settings
   *
   * @since  1.4.4
   *
   */

  public function import() {
    if ( ! $this->can_import() ) {
      return;
    }


    check_admin_referer( self::IMPORT_NONCE );
    $file = $_FILES[ 'wp_super_cache_import_file' ][ 'tmp_name' ];
    $location = add_query_arg( 'tab', 'export', admin_url( 'options-general.php?page=wpsupercache' ) );
    if ( ! is_uploaded_file($file) ) {
      wp_safe_redirect( add_query_arg( 'message', 1, $location ) );
      exit;
    }
    if( empty( $file ) ) {
      wp_safe_redirect( add_query_arg( 'message', 1, $location ) );
      exit;
    }
    $settings = (array) json_decode( file_get_contents( $file ), true );
    if ( 0 === count( $settings ) ) {
      wp_safe_redirect( add_query_arg( 'message', 2, $location ) );
      exit;
    }
    if ( ! file_exists(self::$cache_config_file) )
      wp_cache_verify_config_file();
    // Backup the current config file to the same directory as wp-cache-config-backup.php
    if ( file_exists( self::$cache_config_file) ) {
      $renamed = @rename( self::$cache_config_file, self::$cache_config_file_backup );
      if ( ! $renamed ) {
        wp_safe_redirect( add_query_arg( 'message', 3, $location ) );
        exit;
      }
    }
    $validator = new WP_Super_Cache_Sanitizer();
    // // Update the database options that are not stored in the wp-cache-config.php file
    if ( isset( $settings[ '_wp_super_cache_options' ] ) ) {
      $options = array();
      foreach ( $settings[ '_wp_super_cache_options'] as $key => $value ) {
        $options[ $key ] = get_option( $key );
        // The sanitize function will sanitize and insert the setting to the wp-cache-config.php file if passed the parameter $insert = true.
        $validator->sanitize( $key, $value, $insert = true );
      }
      update_option( '_wp_super_cache_backup_options', $options );
      unset( $settings[ '_wp_super_cache_options' ] );
    }

    // Create a new config file from the original sample
    wp_cache_verify_config_file();
    foreach ( $settings as $setting => $value) {
      $validator->sanitize( $setting, $value, $insert = true );
    }
    include self::$cache_config_file;
    // Reset the garbage collection to the new values
    if ( function_exists( 'schedule_wp_gc' ) )
      schedule_wp_gc( $forced = 1 );
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
    if ( ! $this->can_export() || 0 !== count( get_defined_vars() ) ) {
      return;
    }
    check_admin_referer(  self::EXPORT_NONCE );
    include self::$cache_config_file;
    $wp_cache_config_vars = get_defined_vars();
    $wp_cache_config_options = $this->gather_plugin_options();
    nocache_headers();
    header( "Content-disposition: attachment; filename=" . self::FILENAME );
    header( 'Content-Type: application/octet-stream;' );
    echo json_encode( array_merge($wp_cache_config_vars, $wp_cache_config_options )  );
    die();
  }

  /**
   * Restore the settings that existed before new settings were imported.
   *
   * This function will remove the current wp-cache-config.php file and replace it with the backup generated
   * when settings were imported. The previous settings in the database are also reverted. The database option
   * that stored these settings and their values is deleted once the restore is complete.
   *
   * @since  1.4.4
   *
   * @uses  check_admin_referer
   * @uses  wp_safe_redirect
   *
   * @return  nothing Doesn't return anything. Redirects the user to the import/export form when the restore is complete.
   *
   */

  public function restore() {
    if ( ! $this->can_restore() ) {
      return;
    }
    check_admin_referer( self::RESTORE_NONCE );
    $location = add_query_arg( 'tab', 'export', admin_url( 'options-general.php?page=wpsupercache' ) );
    if ( $this->backupFileExists() && file_exists( self::$cache_config_file ) )
      $renamed = @rename( self::$cache_config_file_backup, self::$cache_config_file );
    if ( ! $renamed ) {
      wp_safe_redirect( add_query_arg( 'message', 4, $location ) );
      exit;
    }
    foreach ( get_option( '_wp_super_cache_backup_options', array() ) as $key => $value ) {
        update_option( $key, $value );
    }
    delete_option( '_wp_super_cache_backup_options' );
    // Reset the garbage collection to the new values
    if ( function_exists( 'schedule_wp_gc' ) )
      schedule_wp_gc( $forced = 1 );
    wp_safe_redirect( add_query_arg( 'message', 5, $location ) );
    exit;
  }

  /**
   * Removes the backup file and the database backup option.
   *
   * If the import is successful and the user does not want to have their back ups any longer then this function will
   * remove the wp-cache-config-backup.php file and the _wp_super_cache_backup_options database option.
   * The backs ups are no longer retrievable after this.
   *
   * @since  1.4.4
   *
   * @uses  wp_safe_redirect
   * @uses  delete_option
   *
   * @return  nothing The user is redirected to the import/export form after the removal of the backup file and database option.
   */

  public function remove() {
    if ( ! $this->can_remove() ) {
      return;
    }
    $location = add_query_arg( 'tab', 'export', admin_url( 'options-general.php?page=wpsupercache' ) );
    if ( $this->backupFileExists() )
      $file = @unlink( self::$cache_config_file_backup );
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
  private function can_export() {
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
  private function can_import() {
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
  static private function backupFileExists() {
    return file_exists( self::$cache_config_file_backup );
  }

}

new WP_Super_Cache_Export();
