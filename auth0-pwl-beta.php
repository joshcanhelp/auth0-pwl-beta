<?php
/**
 * Plugin Name: Auth0 Passwordless (BETA)
 * Description: Testing Auth0 Passwordless login
 * Version: 0.9.0
 * Author: Auth0
 * Author URI: https://auth0.com
 */

const WP_AUTH0_PWL_PLUGIN_HTML_GLOBAL = 'wp_auth0_pwl_plugin_early_html';

/**
 * Grab any HTML coming from other filter functions
 *
 * @param string $html - passed-in HTML, modify or return as-is
 *
 * @return mixed
 */
function wp_auth0_pwl_plugin_login_message_before ( $html = '' ) {

  if ( WP_Auth0_Options::Instance()->get('passwordless_enabled') ) {
    $GLOBALS[ WP_AUTH0_PWL_PLUGIN_HTML_GLOBAL ] = $html;
  } else {
    return $html;
  }
}
add_filter( 'login_message', 'wp_auth0_pwl_plugin_login_message_before', 5 );

/**
 * Add Auth0 PWL form if the options is on
 *
 * @param string $html - passed-in HTML, modify or return as-is
 *
 * @return mixed|string
 */
function wp_auth0_pwl_plugin_login_message_after ( $html = '' ) {

  $options = WP_Auth0_Options::Instance();
  if ( ! $options->get('passwordless_enabled') ) {
    return $html;
  }

  $html = $GLOBALS[ WP_AUTH0_PWL_PLUGIN_HTML_GLOBAL ];
  $lock_options = new WP_Auth0_Lock10_Options();

  wp_deregister_script( 'wpa0_lock' );
  wp_enqueue_script( 'wpa0_lock', 'https://cdn.auth0.com/js/lock/11.5/lock.min.js', array( 'jquery' ), FALSE );
  wp_enqueue_script( 'auth0-pwl', plugin_dir_url( __FILE__ ) . 'login-pwl.js', array( 'jquery' ), WPA0_VERSION, TRUE );

  $lock_options_arr = $lock_options->get_lock_options();

  $lock_options_arr['auth']['params']['scope'] = 'openid email_verified email name nickname';

  $pwl_method = $options->get( 'passwordless_method' );
  switch ( $pwl_method ) {

    // SMS passwordless just needs 'sms' as a connection
    case 'sms' :
      $lock_options_arr['allowedConnections'] = [ 'sms' ];
      break;

    // Social + SMS means there are existing social connections we want to keep
    case 'socialOrSms' :
      $lock_options_arr['allowedConnections'][] = 'sms';
      break;
    // Email link passwordless just needs 'email' as a connection
    case 'emailcode' :
    case 'magiclink' :
      $lock_options_arr['allowedConnections'] = [ 'email' ];
      break;
    // Social + Email means there are social connections be want to keep
    case 'socialOrMagiclink' :
    case 'socialOrEmailcode' :
      $lock_options_arr['allowedConnections'][] = 'email';
      break;
  }

  if ( in_array( $pwl_method, array( 'emailcode', 'socialOrEmailcode' ) ) ) {
    $lock_options_arr['passwordlessMethod'] = 'code';
  } elseif ( in_array( $pwl_method, array( 'magiclink', 'socialOrMagiclink' ) ) ) {
    $lock_options_arr['passwordlessMethod'] = 'link';
  }

  // Set required global var
  wp_localize_script(
    'auth0-pwl',
    'wpAuth0PwlGlobal',
    array(
      'i18n' => array(),
      'lock' => array(
        'options' => $lock_options_arr,
        'ready' => ( $options->get( 'client_id' ) && $options->get( 'domain' ) ),
        'domain' => $options->get( 'domain' ),
        'clientId' => $options->get( 'client_id' ),
      ),
    )
  );

  $html .= '<div id="form-signin-wrapper" class="auth0-login"><div class="form-signin">
        <div id="auth0-login-form"></div></div></div>';

  $html .= sprintf(
    '<style type="text/css">%s %s</style>',
    apply_filters( 'auth0_login_css', '#auth0-login-form {margin-bottom: 2em}' ),
    $options->get( 'custom_css' )
  );

  if ( $custom_js = $options->get( 'custom_js' ) ) {
    $html .= sprintf(
      '<script type="text/javascript">document.addEventListener("DOMContentLoaded", function() {%s})</script>',
      $custom_js
    );
  }

  return $html;
}

add_filter( 'login_message', 'wp_auth0_pwl_plugin_login_message_after', 6 );