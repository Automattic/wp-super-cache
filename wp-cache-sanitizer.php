<?php

/**
* A sanitizer for WP Super Cache settings
*
* This class handles sanitizing the WP Super Cache settings. Each settings has a specific
* value that can be evaluated before being inserted into the database or written to the wp-cache-config.php file.
* This class is developed for the import/export tab in order to sanitize and sanitize the imported values.
*
* The settings are organized in terms of their allowed values: booleans, integers, strings, database options.
*
* Custom settings can be added by using the filters 'wp_super_cache_extend_allowed_{type}' to add the setting to the
* allowed values and 'wp_super_cache_sanitize_{setting}' to sanitize the new setting value. The value can then be sanitized using
* this class's sanitize function.
*
* @package  WP Super Cache
* @since 1.4.4
*/

class WP_Super_Cache_Sanitizer {

  // The list of allowed database options
  // Note: The stats and counts are commented out as they may not be necessary to import since they are site specific.
  protected $ALLOWED_OPTIONS = array(
      'ossdl_cname',
      'ossdl_https',
      'ossdl_off_cdn_url',
      'ossdl_off_exclude',
      'ossdl_off_include_dirs',
      'preload_cache_counter',
      // 'supercache_last_cached',
      // 'supercache_stats',
      // 'wpsupercache_count',
      // 'wpsupercache_gc_time',
      // 'wpsupercache_start',
  );

  protected $ALLOWED_SETTING_ARRAYS = array(
      "cache_acceptable_files",
      "cache_rejected_uri",
      "cache_rejected_user_agent",
      "cached_direct_pages",
      "wp_cache_pages",
  );

  // The list of allowed global boolean variables that are written to the
  // wp-cache-config.php file as either true, false, 0 or 1
  protected $ALLOWED_SETTING_BOOLEANS = array(
      "cache_compression",
      "cache_enabled",
      "cache_gc_email_me",
      "cache_jetpack",
      "cache_rebuild_files",
      "dismiss_gc_warning",
      "dismiss_htaccess_warning",
      "dismiss_readable_warning",
      "ossdlcdn",
      "super_cache_enabled",
      "use_flock",
      "wp_cache_anon_only",
      "wp_cache_clear_on_post_edit",
      "wp_cache_cron_check",
      "wp_cache_debug_email",
      "wp_cache_debug_level",
      "wp_cache_debug_to_file",
      "wp_cache_disable_utf8",
      "wp_cache_front_page_checks",
      "wp_cache_hello_world",
      "wp_cache_hide_donation",
      "wp_cache_make_known_anon",
      "wp_cache_mfunc_enabled",
      "wp_cache_mobile",
      "wp_cache_mobile_enabled",
      "wp_cache_mobile_whitelist",
      "wp_cache_mod_rewrite",
      "wp_cache_mutex_disabled",
      "wp_cache_no_cache_for_get",
      "wp_cache_not_logged_in",
      "wp_cache_object_cache",
      "wp_cache_preload_email_me",
      "wp_cache_preload_on",
      "wp_cache_preload_taxonomies",
      "wp_cache_refresh_single_only",
      "wp_cache_shutdown_gc",
      "wp_cache_slash_check",
      "wp_super_cache_advanced_debug",
      "wp_super_cache_comments",
      "wp_super_cache_debug",
      "wp_super_cache_front_page_check",
      "wp_super_cache_front_page_clear",
      "wp_super_cache_front_page_notification",
      "wp_super_cache_late_init",
      "wp_supercache_304",
      "wp_supercache_cache_list",
  );

  // The list of allowed global integer variables that are written to the wp-cache-config.php
  protected $ALLOWED_SETTING_INTS = array(
      "cache_badbehaviour",
      "cache_awaitingmoderation",
      "cache_domain_mapping",
      "cache_time_interval",
      "cache_max_time",
      "wp_cache_preload_interval",
      "cache_wptouch",
      "sem_id",
  );

  // The list of allowed global string variables that are written to the wp-cache-config.php
  protected $ALLOWED_SETTING_STRINGS = array(
      "cache_badbehaviour_file",
      "cache_no_adverts_for_friends",
      "cache_page_secret",
      "cache_scheduled_time",
      "cache_schedule_interval",
      "cache_schedule_type",
      "file_prefix",
      "wp_cache_debug_ip",
      "wp_cache_debug_log",
      "wp_cache_home_path",
      "wp_cache_mobile_browsers",
      "wp_cache_mobile_groups",
      "wp_cache_mobile_prefixes",
      'wp_cache_plugins_dir',
      "wp_cache_preload_email_volume",
      "wp_cache_preload_posts",
      "wp_super_cache_front_page_text",
      "wptouch_exclude_ua",
  );

  // The default list of mobile browsers taken from wp-cache.php:648
  protected $MOBILE_BROWSERS = array(
      '2.0 MMP',
      '240x320',
      '400X240',
      'Android',
      'AvantGo',
      'BlackBerry',
      'BlackBerry9530',
      'Blazer',
      'Cellphone',
      'Danger',
      'DoCoMo',
      'Elaine/3.0',
      'EudoraWeb',
      'Googlebot-Mobile',
      'hiptop',
      'IEMobile',
      'iPhone',
      'iPod',
      'KYOCERA/WX310K',
      'LG-TU915',
      'LG/U990',
      'LGE',
      'MIDP-2.',
      'MMEF20',
      'MOT-V',
      'NetFront',
      'Newt',
      'Nintendo',
      'Nitro',
      'Nokia',
      'Nokia5800',
      'Obigo',
      'Opera',
      'Palm',
      'PlayStation',
      'portalmmm',
      'Proxinet',
      'ProxiNet',
      'SHARP-TQ-GX10',
      'SHG-i900',
      'Small',
      'SonyEricsson',
      'Symbian',
      'SymbianOS',
      'TS21i-10',
      'UP.Browser',
      'UP.Link',
      'webOS',
      'webOS',
      'Windows',
      'WinWAP',
      'YahooSeeker/M1A1-R2D2',
      'CE',
      'Mini',
      'OS',
      'Portable',
      'VX',
      'Wii',
  );
  // The default list of mobile prefixes taken from wp-cache.php:653
  // Note: the wp-cache.php list is taken from http://svn.wp-plugins.org/wordpress-mobile-pack/trunk/plugins/wpmp_switcher/lite_detection.php
  protected $MOBILE_PREFIXES = array(
      'w3c ',
      'w3c-',
      'acs-',
      'alav',
      'alca',
      'amoi',
      'audi',
      'avan',
      'benq',
      'bird',
      'blac',
      'blaz',
      'brew',
      'cell',
      'cldc',
      'cmd-',
      'dang',
      'doco',
      'eric',
      'hipt',
      'htc_',
      'inno',
      'ipaq',
      'ipod',
      'jigs',
      'kddi',
      'keji',
      'leno',
      'lg-c',
      'lg-d',
      'lg-g',
      'lge-',
      'lg/u',
      'maui',
      'maxo',
      'midp',
      'mits',
      'mmef',
      'mobi',
      'mot-',
      'moto',
      'mwbp',
      'nec-',
      'newt',
      'noki',
      'palm',
      'pana',
      'pant',
      'phil',
      'play',
      'port',
      'prox',
      'qwap',
      'sage',
      'sams',
      'sany',
      'sch-',
      'sec-',
      'send',
      'seri',
      'sgh-',
      'shar',
      'sie-',
      'siem',
      'smal',
      'smar',
      'sony',
      'sph-',
      'symb',
      't-mo',
      'teli',
      'tim-',
      'tosh',
      'tsm-',
      'upg1',
      'upsi',
      'vk-v',
      'voda',
      'wap-',
      'wapa',
      'wapi',
      'wapp',
      'wapr',
      'webc',
      'winw',
        'winw',
      'xda ',
      'xda-'
  );

    /**
     * Merges the list of all the options that will act as a list of options allowed to imported
     *
     * @since  1.4.4
     */

  function __construct() {

    $this->allowed_options = apply_filters( 'wp_super_cache_extend_allowed_option', $this->ALLOWED_OPTIONS );
    $this->allowed_arrays = apply_filters( 'wp_super_cache_extend_allowed_booleans', $this->ALLOWED_SETTING_ARRAYS );
    $this->allowed_booleans = apply_filters( 'wp_super_cache_extend_allowed_booleans', $this->ALLOWED_SETTING_BOOLEANS );
    $this->allowed_integers = apply_filters( 'wp_super_cache_extend_allowed_integers', $this->ALLOWED_SETTING_INTS );
    $this->allowed_strings = apply_filters( 'wp_super_cache_extend_allowed_strings', $this->ALLOWED_SETTING_STRINGS );

  }

  /**
   * Determines if the current setting is allowed to imported.  If the current setting is allowed
   * to imported then perform then sanitize the value correctly.  If the $insert variable is set to
   * true then this function will call the insert function to write the settings to the wp-cache-config.php
   * file or to the database, depending on the setting.
   *
   * @param String $setting The current setting of the value to be sanitized
   * @param Mixed $value The value of the current setting being imported
   * @param Boolean $insert Determines whether the value will be written to the wp-cache-config.php file or inserted into the database
   *
   * @since  1.4.4
   */
  function sanitize( $setting, $value, $insert = false ) {

    if ( in_array( $setting, $this->allowed_booleans ) )
      $sanitized_value = $this->sanitize_boolean( $setting, $value );
    else if ( in_array( $setting, $this->allowed_integers ) )
      $sanitized_value = $this->sanitize_int( $setting, $value );
    else if ( in_array( $setting, $this->allowed_strings ) )
      $sanitized_value = $this->sanitize_string( $setting, $value );
    else if ( in_array( $setting, $this->allowed_arrays ) )
      $sanitized_value = $this->sanitize_array( $setting, $value );
    else if ( in_array( $setting, $this->allowed_options ) )
      $sanitized_value = $this->sanitize_options( $setting, $value );

    if ( isset( $sanitized_value ) ) {
      if ( $insert === true )
          $this->insert( $setting, $sanitized_value );
      return $sanitized_value;
    } else {
      return false;
    }

  }

  /**
   * This function inserts the current setting and its sanitized value into the wp-cache-config.php
   * file or the database depending on the setting.
   *
   * @param String $setting The current setting being inserted
   * @param Mixed $sanitized_value The sanitized value of the setting
   *
   * @since  1.4.4
   *
   * @uses  wp_cache_replace_line Replace the lines in the wp-cache-config.php file
   */
  private function insert( $setting, $sanitized_value ) {
    global $wp_cache_config_file;

    if ( in_array( $setting, $this->allowed_booleans ) ) {
        wp_cache_replace_line('^ *\$' . $setting . ' =', "\$" . $setting . " = " . $sanitized_value . ";", $wp_cache_config_file);
    } else if ( in_array( $setting, $this->allowed_arrays ) ) {
        if ( is_array( $sanitized_value ) && $setting === 'wp_cache_pages' ) {
            foreach ( $sanitized_value as $page => $status ) {
                wp_cache_replace_line('^ *\$wp_cache_pages\[ "' . $page . '" \]', "\$wp_cache_pages[ \"{$page}\" ] = $status;", $wp_cache_config_file);
            }
        } else {
          wp_cache_replace_line('^ *\$' . $setting . ' =', "\$". $setting ." = $sanitized_value;", $wp_cache_config_file);
        }
    } else if ( in_array( $setting, $this->allowed_strings ) ) {
        wp_cache_replace_line('^ *\$' . $setting . ' =', "\$". $setting ." = $sanitized_value;", $wp_cache_config_file);
    } else if ( in_array( $setting, $this->allowed_integers ) ) {
        wp_cache_replace_line('^ *\$' . $setting . ' =', "\$". $setting ." = $sanitized_value;", $wp_cache_config_file);
    } else if ( in_array( $setting, $this->allowed_options ) ) {
        update_option( $setting, $sanitized_value );
    }
  }

  /**
   * This sanitizes the settings where the allowed values are boolean, 0 or 1. The boolean values true and false
   * are converted to strings to maintain the values in wp-cache-config.php file
   *
   * @param String $setting The current boolean setting to be sanitized
   * @param Mixed $value The mixed value of the current setting that will be sanitized
   *
   * @return Boolean|String A sanitized version of the inputed value
   */
  function sanitize_boolean( $setting, $value ) {
    // Note: necessary to force cache_enable and super_cache_enabled to be true/false
    $sanitized_value = apply_filters( "wp_super_cache_sanitize_$setting", $value );
    if ( is_bool( $value ) === true ) {
        $sanitized_value = $value ? 'true' : 'false';
    } else {
        $sanitized_value = intval( $value ) === 1 ? 1 : 0;
    }
    return $sanitized_value;
  }

  /**
   * This sanitizes the settings where the allowed values are only arrays.
   * The sanitization method is different per setting name.
   *
   * @param String $setting The current array setting to be sanitized
   * @param Mixed $value The mixed value of the current setting that will be sanitized.
   *
   * @return  Array A sanitized version of the inputed value
   */
  function sanitize_array( $setting, $value ) {
    switch ( $setting )  {

      case "cache_rejected_uri":
          global $cache_rejected_uri;
          foreach ( $value as $index => $url ) {
              $value[ $index ] = str_replace( '\\\\', '\\', $url );
          }
          $sanitized_value = wp_cache_sanitize_value( implode(', ', $value), $cache_rejected_uri );
          break;

      case 'cache_acceptable_files' :
          global $cache_acceptable_files;
          if ( is_array( $value ) ) {
              $value = implode( ',', $value );
          }
          $sanitized_value = wp_cache_sanitize_value( $value, $cache_acceptable_files );
          break;

      case 'cache_rejected_user_agent':
          global $cache_rejected_user_agent;
          if ( is_array( $value ) ) {
              $value = implode( ',', $value );
          }
          $value = str_replace( ' ', '___', $value );
          $sanitized_value = str_replace( '___', ' ', wp_cache_sanitize_value( $value, $cache_rejected_user_agent ) );
          break;

      case 'cached_direct_pages':
          if ( is_array( $value ) )  {
              foreach ( $value as $page ) {
                  $page = str_replace( get_option( 'siteurl' ), '', $page );
                  if( substr( $page, 0, 1 ) != '/' )
                      $page = '/' . $page;
                  $page = esc_sql( $page );
                  $page = "'$page'";
                  $sanitized_values[] = $page;
              }
              $sanitized_values = array_unique( $sanitized_values );
              $sanitized_value = 'array(' . implode(', ', $sanitized_values ) . ');';
          } else {
              $sanitized_value = '';
          }
          break;

      case 'wp_cache_pages':
          $sanitized_value = array(
              'single' => 0,
              'pages' => 0,
              'archives' => 0,
              'tag' => 0,
              'frontpage' => 0,
              'home' => 0,
              'category' => 0,
              'feed' => 0,
              'author' => 0,
              'search' => 0,
          );
          foreach ( $value as $page => $cache ) {
              if ( array_key_exists( $page, $sanitized_value ) ) {
                  $sanitized_value[ $page ] = intval( $cache ) === 1 ? 1 : 0;
              }
          }
          break;

      default:
          $sanitized_value = apply_filters( "wp_super_cache_sanitize_$setting", $value );
          if ( ! is_array($sanitized_value) ) (array) $sanitized_value;
          break;
    }
    return $sanitized_value;
  }

  /**
   * This sanitizes the settings where the allowed values are only integers.
   * The sanitation method is different per setting name.
   *
   * @param String $setting The current integer setting to be sanitized
   * @param Mixed $value The mixed value of the current setting that will be sanitized
   *
   * @return Integer A sanitized version of the inputed value
   */
  function sanitize_int( $setting, $value ) {
    switch ( $setting ) {
        case "cache_badbehaviour":
        case "cache_awaitingmoderation":
        case "cache_wptouch":
        case "cache_domain_mapping":
            $sanitized_value = intval( $value );
            break;

        case "cache_time_interval":
            $sanitized_value = is_numeric( $value ) ? intval( $value ) : 600;
            if ( $sanitized_value !== $value )
                $sanitized_value = $value;
            break;

        case "cache_max_time":
            $sanitized_value = is_numeric( $value ) ? intval( $value ) : 3600;
            if ( $sanitized_value !== $value )
                $sanitized_value = $value;
            break;


        case "wp_cache_preload_interval":
            $sanitized_value = is_numeric( $value ) && $value >= 30 ? intval( $value ) : 0;
            break;

        case "sem_id":
            global $WPSC_HTTP_HOST, $cache_path;
            if ( preg_match('/^\d{9}$/', intval( $value ) ) ) {
                $sanitized_value = intval( $value );
            } else {
                $cache_path =  '/' != substr( $cache_path, -1 )  ? $cache_path .= '/' : '';
                $sanitized_value = crc32( $WPSC_HTTP_HOST . $cache_path ) & 0x7fffffff;
            }
            break;

        default:
            $sanitized_value = apply_filters( "wp_super_cache_sanitize_$setting", $value );
            $sanitized_value = intval( $sanitized_value );
            break;
    }
    return $sanitized_value;
  }

  /**
   * This sanitizes the settings where the allowed values are only strings.
   * The sanitation method is different per setting name.
   *
   * @param String $setting The current string setting to be sanitized
   * @param Mixed $value The mixed value of the current setting that will be sanitized
   *
   * @uses  wp_cache_sanitize_value Sanitizes an array value
   *
   * @return String|Array A sanitized version of the inputed value
   */
  function sanitize_string( $setting, $value ) {
    switch ( $setting ) {

        case 'cache_badbehaviour_file':
            $sanitized_value = function_exists('get_bb_file_loc') ? get_bb_file_loc() : "";
            $sanitized_value = "\"$sanitized_value\"";
            break;

        case 'cache_no_adverts_for_friends':
            $sanitized_value = in_array( $value, array( 'yes', 'no' ) ) ? $value : "yes";
            $sanitized_value = "\"$sanitized_value\"";
            break;

        case "cache_scheduled_time":
            $sanitized_value = preg_match( '/^\d{2}:\d{2}$/', $value ) ? "\"$value\"" : "\"00:00\"";
            break;

        case "cache_schedule_interval":
            $sanitized_value = in_array( $value, array( 'daily', 'twicedaily', 'hourly' ) ) ? "\"$value\"" : "\"daily\"";
            break;

        case 'cache_schedule_type':
            $sanitized_value = in_array( $value, array( 'time', 'interval' ) ) ? "\"$value\"" : "\"interval\"";
            break;

        case 'cache_page_secret':
            if ( strlen( $value ) != 32 || ! ctype_xdigit( $value ) ) {
                $sanitized_value = md5( date( 'H:i:s' ) . mt_rand() );
            } else {
                $sanitized_value = $value;
            }
            $sanitized_value = "\"$sanitized_value\"";
            break;

        case 'file_prefix':
            $sanitized_value = ! is_string( $value ) ? 'wp-cache-' : esc_html( $value );
            $sanitized_value = "\"$sanitized_value\"";
            break;

        case "wp_cache_debug_ip":
            $sanitized_value = filter_var( $value, FILTER_VALIDATE_IP ) === false ? "" : esc_html( $value );
            $sanitized_value = "\"$sanitized_value\"" ;
            break;

        case "wp_cache_debug_log":
            $sanitized_value = md5( time() ) . ".txt";
            $logname = str_replace( '.txt', '', $value );
            if ( strlen( $logname ) === 32 && ctype_xdigit( $logname ) ) {
                $sanitized_value = $value;
            }
            $sanitized_value = "\"$sanitized_value\"" ;
            break;

        case "wp_cache_home_path":
            global $wp_cache_home_path;
            $sanitized_value = '/';
            $home_path = parse_url( site_url() );
            $home_path = trailingslashit( array_key_exists( 'path', $home_path ) ? $home_path[ 'path' ] : '' );
            if ( false == isset( $wp_cache_home_path ) )
                $sanitized_value =  $wp_cache_home_path;
            if ( "$home_path" !== "$sanitized_value" )
                $sanitized_value = $home_path;
            $sanitized_value = "\"$sanitized_value\"" ;
            break;

        case 'wp_cache_mobile_browsers':
            if ( function_exists( "cfmobi_default_browsers" ) ) {
                $value = cfmobi_default_browsers( "mobile" );
                $value = array_merge( $value, cfmobi_default_browsers( "touch" ) );
            } elseif ( function_exists( 'lite_detection_ua_contains' ) ) {
                $value = explode( '|', lite_detection_ua_contains() );
            } else {
                $value = $this->MOBILE_BROWSERS;
            }
            $sanitized_value = apply_filters( 'cached_mobile_browsers', $value );
            if ( is_array( $sanitized_value ) ) {
                $sanitized_value = implode( ', ', $sanitized_value );
            }
            $sanitized_value = "\"$sanitized_value\"";
            break;

        case 'wp_cache_mobile_groups':
            $value = apply_filters( 'cached_mobile_groups', array() );
            if ( is_array( $value ) ) {
                $sanitized_value = implode( ', ', $value );
            } else {
                $sanitized_value = '';
            }
            $sanitized_value = "\"$sanitized_value\"" ;
            break;


        case 'wp_cache_plugins_dir':
          $sanitized_value = 'WPCACHEHOME . \'plugins\'';
          break;

        // Note: same approach as wp-cache.php:1875
        case 'wp_super_cache_front_page_text':
            $sanitized_value = "\"" . esc_html( $value ) . "\"" ;
            break;

        case 'wp_cache_mobile_prefixes':
            if ( function_exists( "lite_detection_ua_prefixes" ) ) {
                $value = lite_detection_ua_prefixes();
            } else {
                $value = $this->MOBILE_PREFIXES;
            }
            if ( is_array( $value ) ) {
                $sanitized_value = implode( ', ', $value );
            }
            $sanitized_value = "\"$sanitized_value\"";
            break;

        case "wp_cache_preload_email_volume" :
            $sanitized_value = in_array( $value , array( 'less', 'medium', 'many' ) ) ? $value : 'medium';
            $sanitized_value = "\"$sanitized_value\"";
            break;

        case "wp_cache_preload_posts":
            global $wpdb;
            if ( $value === 'all' ) {
                $sanitized_value = "\"all\"";
            } else if ( is_numeric($value ) ) {
                $sanitized_value = intval( $value );
            } else {
                $value = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->posts} WHERE post_status = 'publish'" );
                $sanitized_value = intval( $value );
            }
            break;

        case "wptouch_exclude_ua":
            $sanitized_value = function_exists( 'bnc_wptouch_get_exclude_user_agents' ) ?  implode( ',', bnc_wptouch_get_exclude_user_agents() ) : '';
            $sanitized_value = "\"$sanitized_value\"";
            break;

        default:
            $sanitized_value = apply_filters( "wp_super_cache_sanitize_$setting", $value );
            $sanitized_value = "\"$sanitized_value\"";
            break;
    }
    return $sanitized_value;
  }

  /**
   * This sanitizes the settings where the allowed values are entered into the database.
   * The sanitation method is different per setting name.
   *
   * @param String $setting The current setting to be sanitized
   * @param Mixed $value The mixed value of the current setting that will be sanitized
   *
   * @return Integer A sanitized version of the inputed value
   */
  function sanitize_options( $setting, $value ) {
    switch ( $setting ) {
        case 'ossdl_cname':
            $sanitized_value = is_string($value) ? trim( (string) $value ) : '';
            break;

        case 'ossdl_https':
            $sanitized_value = $value === 1 ? 1 : 0;
            break;

        case 'ossdl_off_cdn_url':
            $sanitized_value = filter_var( $value, FILTER_VALIDATE_URL ) === false ? '' : $value;
            break;

        case 'ossdl_off_exclude':
            if ( $value === '' )
                $value = '.php';
            $sanitized_value = esc_html( $value );
            break;

        case 'ossdl_off_include_dirs':
            if ( $value === '' )
                $value = 'wp-content,wp-includes';
            $sanitized_value = esc_html( $value );
            break;

        case 'preload_cache_counter':
            $sanitized_value = array( 'c' => 0, 't' => time() );
            if ( is_array( $value ) ){
                if( array_key_exists( 'c', $value) )
                    $sanitized_value['c'] = $value['c'];
                if ( array_key_exists( 't', $value ) )
                    $sanitized_value['t'] = $value['t'];
                if ( array_key_exists( 'first', $value ) )
                    $sanitized_value['first'] = 1;
            }
            break;

        // Note: possible no need to import. See const ALLOWED_OPTIONS
        // case 'supercache_last_cached':
        //     break;
        // case 'supercache_stats':
        //     break;
        // case 'wpsupercache_count':
        //     break;
        // case 'wpsupercache_gc_time':
        //     break;
        // case 'wpsupercache_start':
        //     break;

        default:
            $sanitized_value = apply_filters( "wp_super_cache_sanitize_$setting", $value );
            break;
    }
    return $sanitized_value;
  }
}