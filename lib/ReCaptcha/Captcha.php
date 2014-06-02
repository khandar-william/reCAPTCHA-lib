<?php

namespace ReCaptcha;

use ReCaptcha\Exception;
use ReCaptcha\CaptchaTheme;
// throw new Exception\InvalidArgumentException(sprintf(
//                 'Could not open file %s for reading',
//                 $filename
//             ));
// -------------------------------------------------------------

/**
*
*/
class Captcha
{
   /**
    * Constant API Server (defined without scheme)
    *
    * @var string
    **/
   const RECAPTCHA_API_SERVER =  "www.google.com/recaptcha/api";

   /**
    * RECAPTCHA Verify server name
    *
    * @var string
    **/
   const RECAPTCHA_VERIFY_SERVER =  "www.google.com";

   /**
    * Public API KEY
    *
    * @access protected
    * @var string
    **/
   protected $_publicKey;

   /**
    * Private API KEY
    *
    * @access protected
    * @var string
    **/
   protected $_privateKey;

   /**
    * Enable / Disable API call via SSL
    *
    * @access protected
    * @var bool
    **/
   protected $_ssl;

   /**
    * Theme default options
    * RecaptchaOptions Reference: {@link https://developers.google.com/recaptcha/docs/customization}
    *
    * @access protected
    * @var array
    **/
   protected $_recaptchaOptions = array(
               'theme'               => 'red',
               'lang'                => 'en',
               'custom_translations' => null,
               'custom_theme_widget' => null,
               'tabindex'            => 0
               );

   /**
    * List of Standard Theme names available
    * Standard names Reference: {@link https://developers.google.com/recaptcha/docs/customization#Standard_Themes}
    *
    * @access protected
    * @var array
    **/
   protected $_standardThemes = array('red','white','blackglass','clean');

   /**
    * i18n Internationalization
    * List of Built-in available languages
    * @update 2014-06-01 01:30
    * Languages not found can be translate to your own language
    *
    * @access protected
    * @var array
    **/
   protected $_builtInlang = array(
               'English'    => 'en',
               'Dutch'      => 'nl',
               'French'     => 'fr',
               'German'     => 'de',
               'Portuguese' => 'pt',
               'Russian'    => 'ru',
               'Spanish'    => 'es',
               'Turkish'    => 'tr',
            );

   /**
    * Error Message Response
    *
    * @access public
    * @var string
    **/
   public $_errorResponse;

   public function __construct($lang = NULL)
   {
      if ( defined('ENVIRONMENT') && defined('APPPATH') ) {

         $CI =& get_instance();
         $CI->config->load('captcha_config');
         $this->_publicKey  = $CI->config->item('captcha_publicKey');
         $this->_privateKey = $CI->config->item('captcha_privateKey');
         $this->_ssl        = $CI->config->item('captcha_ssl');

         // Overwrite's default options if it's set in config file
         if ( is_array($CI->config->item('captcha_options')) ) {
            $this->_recaptchaOptions = $CI->config->item('captcha_options');
         }
         // Overwrite's Standard_Theme name if it's set in config file
         if ( !empty($CI->config->item('captcha_standard_theme')) ) {
            $this->_recaptchaOptions['theme'] = $CI->config->item('captcha_standard_theme');
         }
      }

      if ( NULL !== $lang ) {
         $this->setTranslation($lang);
      }
   }

   /**
    * Create embedded widget script HTML called within form
    *
    * @date 2014-05-30 22:44
    * @author Adriano Rosa (http://adrianorosa.com)
    *
    * @param string $theme_name
    * @param array $options = array()
    * @return string The reCAPTCHA widget embedd HTML
    **/
   public function displayHTML($theme_name = NULL, $options = array())
   {
      if ($this->_publicKey === NULL || $this->_publicKey === '') {
         exit('To use reCAPTCHA you must get an API key from https://www.google.com/recaptcha/admin/create');
      }

      $scheme = ( $this->_ssl ) ? 'https://' : 'http://';
      $errorpart = ($this->_errorResponse) ? $errorpart = "&amp;error=" . $this->_errorResponse : NULL;
      // Overwrites config captcha_standard_theme value
      if ( NULL !== $theme_name ) {
         $this->_recaptchaOptions['theme'] = $theme_name;
      }

      $captcha_html = $this->_theme($options);
      $captcha_html .= '<script type="text/javascript" src="'. $scheme . self::RECAPTCHA_API_SERVER . '/challenge?k=' . $this->_publicKey . $errorpart . '"></script>

      <noscript>
         <iframe src="' . $scheme . self::RECAPTCHA_API_SERVER . '/noscript?k=' . $this->_publicKey . $errorpart . '" height="300" width="500" frameborder="0"></iframe><br>
         <textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
         <input type="hidden" name="recaptcha_response_field" value="manual_challenge">
      </noscript>';

      return $captcha_html;

   }

   /**
    * Display Theme customization
    *
    * @date 2014-05-30 23:22
    * @author Adriano Rosa (http://adrianorosa.com)
    *
    * @param array $options reCAPTCHA Options that can be overwridden for custom theme only
    * @return string Standard_Theme | Custom_Theme | Fallback default reCAPTCHA theme
    **/
   private function _theme($options = array())
   {
      $options = array_merge($this->_recaptchaOptions, $options);
      unset($options['theme']); // We don't use theme=>value set in options array
// _vd($options);
      // Skip to default reCAPTCHA theme if it's not set or options is not required
      if ( !isset($this->_recaptchaOptions['theme']) && count($options) == 0 ) {
         return;
      }
      // Skip to default reCAPTCHA theme if it's set to 'red' and there is no options at all
      if ( $this->_recaptchaOptions['theme'] === 'red' && count($options) == 0 ) {
         return;
      }

      // If theme name is on the list of Standard Themes assumed we are using correct one
      if ( in_array($this->_recaptchaOptions['theme'], $this->_standardThemes) ) {

         $js_options = "theme : '{$this->_recaptchaOptions['theme']}'\n";
         // unset this just it in case, this option is not used in standard themes
         unset($options['custom_theme_widget']);

         if ( count($options) > 0 ) {
            foreach ($options as $std_option => $value) {
               // if value is an array we assume it might be a language options set
               if ( is_array($value) ) {
                  $js_options .= ",{$std_option} : { \n";
                  foreach ($value as $dic => $item) {
                     $js_options .= "\t{$dic} :" . json_encode($item) . ",\n";
                  }
                  $js_options .= "}\n";

               } else {

               $js_options .= ",{$std_option}:'{$value}'\n";

               }
             }
         }
         return "
            <script type=\"text/javascript\">
               var RecaptchaOptions = {
                  {$js_options}
                };
            </script>";
      // If a theme name is not listed in Stardard theme it might be a custom
      } elseif ( $this->_recaptchaOptions['theme'] === 'custom' ) {

         return $this->_customTheme($options);
      }
      // FALLBACK to red one default theme
      return;
   }

   /**
    * Custom Theme Template
    * If we want to use a custom theme, also must provide a custom CSS to it.
    * Fully Custom Reference: {@link: https://developers.google.com/recaptcha/docs/customization#Custom_Theming}
    *
    * @date 2014-05-31 02:57
    * @author Adriano Rosa (http://adrianorosa.com)
    *
    * @param array $custom_options Associative array with custom options to be set for reCAPTCHA
    * @return string reCAPTCHA HTML template
    **/
   private function _customTheme($custom_options)
   {
      $js_options = '';
// _vd($custom_options);
      foreach ($custom_options as $option => $value) {

         if ( is_array($value) ) {
            $js_options .= ",{$option} : { \n";
            foreach ($value as $dic => $item) {
               $js_options .= "\t{$dic} :" . json_encode($item) . ",\n";
            }
            $js_options .= "}\n";
         } else {

            $js_options .= ",{$option}:'{$value}'\n";
         }
      }
      // Custom theme MUST have an option [custom_theme_widget: ID_some_widget_name] set for recaptcha
      // If there is not, we make it.
      if ( !isset($custom_options['custom_theme_widget']) ) {
         $widget_ID = 'recaptcha_widget';
         $js_options .= ',custom_theme_widget : \'' . $widget_ID . '\'';

      } else {
         $widget_ID = $custom_options['custom_theme_widget'];
      }
      // $widget_ID = ( !isset($custom_options['custom_theme_widget']) )
            // ? 'recaptcha_widget'
            // : $custom_options['custom_theme_widget'];

      $custom_template = "
      <script type=\"text/javascript\">
         var RecaptchaOptions = {
            theme : 'custom'
            {$js_options}
       };
      </script>";

      if ( $this->_recaptchaOptions['theme'] === 'custom' ) {

         $custom_template .= '
            <div id="'. $widget_ID .'" style="display:none">

            <div id="recaptcha_image"></div>
            <div class="recaptcha_only_if_incorrect_sol" style="color:red">{Incorrect, please try again.}'. $this->lang("incorrect_try_again") .'</div>

            <span class="recaptcha_only_if_image">{Enter the words above:}'. $this->lang('instructions_visual') .'</span>
            <span class="recaptcha_only_if_audio">{Enter the numbers you hear:}'. $this->lang('instructions_audio') .'</span>

            <input type="text" id="recaptcha_response_field" name="recaptcha_response_field" />

            <div><a href="javascript:Recaptcha.reload()">{Get another CAPTCHA}'. $this->lang('refresh_btn') .'</a></div>
            <div class="recaptcha_only_if_image"><a href="javascript:Recaptcha.switch_type(\'audio\')">{Get an audio CAPTCHA}'. $this->lang('audio_challenge') .'</a></div>
            <div class="recaptcha_only_if_audio"><a href="javascript:Recaptcha.switch_type(\'image\')">{Get an image CAPTCHA}'. $this->lang('visual_challenge') .'</a></div>

            <div><a href="javascript:Recaptcha.showhelp()">Help'. $this->lang('help_btn') .'</a></div>
          </div>
          ';
       }
       return vsprintf($custom_template, $this->_recaptchaOptions['custom_translations']);
   }
   /**
    * Custom Translations
    *
    * In order to use custom translation (even if it is not built in specially for a custom theme),
    * the translations must be set manually by this method or by passing the lang two letters code to
    * instance constructor It will set translation by a lang code given and overwrittes other languages
    * if it was set into captcha_config or array of options, if recaptcha.lang[lang_code].php file with
    * its respective translation strings within a folder i18n is not found default lang English 'en'  will be used instead.
    *
    * Set reCAPTCHA translation language option and if it's not Built in Languague then
    * include the translations file if it does exists.
    *
    * @date 2014-06-01 02:00
    * @author Adriano Rosa (http://adrianorosa.com)
    *
    * @param string $language
    * @param string $path
    * @return void
    **/
   public function setTranslation($language = 'en', $path = NULL)
   {
      $RECAPTCHA_LANG = array(
         'instructions_visual' => 'Enter the words above:',
         'instructions_audio'  => 'Type what you hear:',
         'play_again'          => 'Play sound again',
         'cant_hear_this'      => 'Download sound as MP3',
         'visual_challenge'    => 'Get an image CAPTCHA',
         'audio_challenge'     => 'Get an audio CAPTCHA',
         'refresh_btn'         => 'Get another CAPTCHA',
         'help_btn'            => 'Help',
         'incorrect_try_again' => 'Incorrect, please try again.'
         );

      // path/vendor/lib/I18n/recaptcha.lang.[langcode].php
      $path = ( NULL === $path )
         ? dirname(__DIR__) . DIRECTORY_SEPARATOR . 'I18n' . DIRECTORY_SEPARATOR
         : $path;

      if ( file_exists( $path . 'recaptcha.lang.' . $language . '.php' ) ) {
         @include_once $path . 'recaptcha.lang.' . $language . '.php';
      }
      $this->_recaptchaOptions['custom_translations'] = $RECAPTCHA_LANG;
      $this->_recaptchaOptions['lang'] = $language;
   }

   /**
    * Get the lang translations line
    *
    * @date 2014-06-02 02:50
    * @author Adriano Rosa (http://adrianorosa.com)
    *
    * @param string $key
    * @param string $lang
    * @return string
    **/
   protected function lang($key, $lang = 'en')
   {
      if ( !isset($this->_recaptchaOptions['lang']) ) {
         $this->setTranslation($lang);
      } elseif ( isset($this->_recaptchaOptions['lang'])
                  && !isset($this->_recaptchaOptions['custom_translations'])
                  && !is_array($this->_recaptchaOptions['custom_translations'])
                  ) {
         $this->setTranslation($this->_recaptchaOptions['lang']);
      }

      return isset($this->_recaptchaOptions['custom_translations'][$key])
         ? $this->_recaptchaOptions['custom_translations'][$key]
         : false;
   }

}