<?php

namespace ReCaptcha;

use ReCaptcha\CaptchaTheme;
use ReCaptcha\CaptchaException;

/**
 * PHP reCAPTCHA Google's API Wrapper Library for CodeIgniter
 * This is a PHP library that handles calling reCAPTCHA widget.
 *
 * NOTE: before start using this library you must generate reCAPTCHA API Key
 *          https://www.google.com/recaptcha/admin/create
 * This library was written based on plugin version from
 * AUTHORS: Mike Crawford, Ben Maurer -- http://recaptcha.net
 *
 * @date 2014-06-01 01:30
 * @package Libraries
 * @author  Adriano Rosa (http://adrianorosa.com)
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link    https://github.com/adrianorsouza/codeigniter-recaptcha
 * @link    reCAPTCHA docs Reference: {@link https://developers.google.com/recaptcha/}
 * @version 0.1.0
 */
class Captcha extends CaptchaTheme
{
   /**
    * reCAPTCHA Wrapper Library Version
    *
    * @access public
    * @var string
    */
   public $Version = '0.1.0';

   /**
    * Constant API Server (without scheme)
    *
    * @var string
    */
   const RECAPTCHA_API_SERVER =  'www.google.com/recaptcha/api/';

   /**
    * RECAPTCHA Verify server name
    *
    * @var string
    */
   const RECAPTCHA_VERIFY_SERVER = 'www.google.com';

   /**
    * Public API KEY
    *
    * @access protected
    * @var string
    */
   protected $_publicKey;

   /**
    * Private API KEY
    *
    * @access protected
    * @var string
    */
   protected $_privateKey;

   /**
    * Enable / Disable API call via SSL
    *
    * @access protected
    * @var bool
    */
   protected $_ssl;

   /**
    * Remote IP
    *
    * @access protected
    * @var string
    */
   protected $_remoteIP = '127.0.0.1';

   /**
    * Error Message Response
    *
    * @access public
    * @var string
    */
   protected $_error;

   /**
    * Instance constructor
    *
    * @param string $lang Changes the widget language
    * @return void
    */
   public function __construct($lang = NULL)
   {
      if ( defined('ENVIRONMENT') && defined('APPPATH') ) {

         $CI =& get_instance();
         $CI->config->load('captcha_config');
         $this->_publicKey  = $CI->config->item('captcha_publicKey');
         $this->_privateKey = $CI->config->item('captcha_privateKey');
         $this->_ssl        = $CI->config->item('captcha_ssl');

         // Overwrite default options if it's set in config file
         if ( is_array($CI->config->item('captcha_options')) ) {
            $this->_recaptchaOptions = $CI->config->item('captcha_options');
         }
         // Overwrite Standard_Theme name if it's set in config file
         if ( !empty($CI->config->item('captcha_standard_theme')) ) {
            $this->_recaptchaOptions['theme'] = $CI->config->item('captcha_standard_theme');
         }
      }

      if ( NULL !== $lang ) {
         $this->setTranslation($lang);
      }

      // Stop script if API Keys is not found
      if ( strlen($this->_publicKey == 0 || strlen($this->_privateKey) == 0 ) ) {
         exit('To use reCAPTCHA you must get an API key from
            <a href="https://www.google.com/recaptcha/admin/create">
            https://www.google.com/recaptcha/admin/create</a>');
      }
   }

   /**
    * Set a remote client IP
    * Optional setter for an alternative IP address whether REMOTE_ADDR is empty,
    * anyway default value for FALLBACK is 127.0.0.1
    * Use this setter to use a different one.
    *
    * @param string $ip_address The valid IP address
    * @return object
    */
   public function setRemoteIp($ip_address)
   {
      $this->_remoteIP = $ip_address;
      return $this;
   }

   /**
    * Set reCAPTCHA server Response error.
    * NOTE: Default string error is: incorrect-captcha-sol
    * Use this function to overwrite with your own message.
    *
    * @param $string $e The error message string. Optional Whether this parameter is NULL, it will retrieve
               an error message translated by a given lang e.g: 'it' (for Italian) returns 'Scorretto. Riprova.'
    * @return string
    */
   public function setError($e = NULL)
   {
      // whether lang is set the I18n string is picked
      if ( NULL === $e ) {
         $e = $this->i18n('incorrect_try_again');
      }
      $this->_error = $e;
      return $this;
   }

   /**
    * Get reCAPTCHA server Response error
    *
    * @param string
    * @param strin
    * @return string
    */
   public function getError()
   {
      return $this->_error;
   }

   /**
    * Create embedded widget script HTML called within form
    *
    * @param string $theme_name Optional Standard_Theme or custom theme name
    * @param array $options Optional array of reCAPTCHA options
    * @return string The reCAPTCHA widget embed HTML
    */
   public function displayHTML($theme_name = NULL, $options = array())
   {
      // append a Theme
      $captcha_snippet = $this->_theme($theme_name, $options);
      $captcha_snippet .= '<script type="text/javascript" src="'. $this->_buildServerURI() . '"></script>

      <noscript>
         <iframe src="' . $this->_buildServerURI('noscript') . '" height="300" width="500" frameborder="0"></iframe><br>
         <textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
         <input type="hidden" name="recaptcha_response_field" value="manual_challenge">
      </noscript>';

      return $captcha_snippet;
   }

   /**
    * reCAPTCHA API Response
    * resolves response challenge
    *
    * @param string $inputChallenge The reCAPTCHA image input challenge data
    * @param string $inputResponse The user reCAPTCHA input challenge data response
    * @return bool
    */
   public function isValid()
   {
      // Skip without submission
      if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {

         return FALSE;

      } else {

         $captchaChallenge = isset($_POST['recaptcha_challenge_field'])
            ? $this->_sanitizeField($_POST['recaptcha_challenge_field'])
            : NULL;

         $captchaResponse  = isset($_POST['recaptcha_response_field'])
            ? $this->_sanitizeField($_POST['recaptcha_response_field'])
            : NULL;
         // Skip empty submission
         if ( strlen($captchaChallenge) == 0 || strlen($captchaResponse) == 0 ) {
            $this->setError('incorrect-captcha-sol');
            return FALSE;
         }

         $data = array(
            'privatekey' => $this->_privateKey,
            'remoteip'   => $this->_remoteIp(),
            'challenge'  => $captchaChallenge,
            'response'   => $captchaResponse
            );

         $result = $this->_postHttpChallenge($data);

         if ( is_array($result) ) {
            if ( $result[0] === "true") {
               return TRUE;

            } else {
               $this->setError($result[1]);
               return FALSE;
            }
         }
         return FALSE;
      }
   }

   /**
    * reCAPTCHA API Request
    * Post reCAPTCHA input challenge, response
    *
    * @param array $data Array of reCAPTCHA parameters
    * @return array
    */
   protected function _postHttpChallenge(array $data)
   {
      $httpQuery = http_build_query($data);

      $httpRequest  = "POST /recaptcha/api/verify HTTP/1.0\r\n";
      $httpRequest .= "Host: " . self::RECAPTCHA_VERIFY_SERVER . " \r\n";
      $httpRequest .= "Content-Type: application/x-www-form-urlencoded;\r\n";
      $httpRequest .= "Content-Length: " . strlen($httpQuery) . "\r\n";
      $httpRequest .= "User-Agent: reCAPTCHA/PHP\r\n";
      $httpRequest .= "\r\n";
      $httpRequest .= $httpQuery;

      $httpResponse = '';

      if( false == ( $fs = @fsockopen(self::RECAPTCHA_VERIFY_SERVER, 80, $errno, $errstr, 10) ) ) {
         exit('Could not open socket');
      }

      fwrite($fs, $httpRequest);

      while ( !feof($fs) ) {
         $httpResponse .= stream_get_line($fs, 1024);
      }
      fclose($fs);

      $httpResponse = explode("\r\n\r\n", $httpResponse, 2);

      if ( count($httpResponse) == 2) {
         return explode("\n", $httpResponse[1]);
      }

      return $httpResponse;
   }

   /**
    * Get the client remote IP
    *
    * @date 2014-06-03 19:16
    * @author Adriano Rosa (http://adrianorosa.com)
    * @return string
    */
   private function _remoteIp()
   {
      if ( !$_SERVER['REMOTE_ADDR'] ) {
         return $this->_remoteIP;
      }
      return $_SERVER['REMOTE_ADDR'];
   }

   /**
    * Sanitizes recaptcha_input_field
    *
    * @param string $input
    * @return string
    */
   private function _sanitizeField($recaptcha_input_field)
   {
      return preg_replace('/[^a-zA-Z0-9._\-+\s]/i', '', $recaptcha_input_field);
   }

   /**
    * Build API server URI
    *
    * @param string $path The path whether is noscript for iframe or not
    * @return string
    */
   private function _buildServerURI($path = 'challenge')
   {
      // Scheme
      $uri  = ( TRUE === $this->_ssl ) ? 'https://' : 'http://';
      // Host
      $uri .= self::RECAPTCHA_API_SERVER;
      // Path
      $uri .= ($path !== 'challenge') ? 'noscript' : $path;
      // Query
      $uri .= '?k=' . $this->_publicKey;
      $uri .= ($this->_error) ? '&error=' . $this->_error : NULL;
      $uri .= ( isset($this->_recaptchaOptions['lang']) ) ? '&hl=' . $this->_recaptchaOptions['lang'] : NULL;

      return $uri;
   }
}
