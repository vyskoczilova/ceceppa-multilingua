<?php
if ( ! defined( 'ABSPATH' ) ) die( "Access denied" );

$GLOBALS[ '_cml_supported_plugin' ] = array( 'all-in-one-seo-pack', 'wordpress-seo' );

/*
 * simple wpml-config.xml parser
 *
 * this class extract:
 *
 *  *) admin-texts to allow user to translate them in "My translations" page
 *  *) language-switcher-settings to extract "Combo" style :)
 * 
 */
class CML_WPML_Parser {
  protected $values;
  protected $group = null;
  protected $options = null;

  function __construct( $filename, $group, $options = null, $generate_style = false ) { 
    $xml = file_get_contents( $filename );

    $parser = xml_parser_create();
    xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, 0 );
    xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parse_into_struct( $parser, $xml, $this->values );
    xml_parser_free( $parser );
    
    $this->options = $options;
    $this->group = $group;
    $this->style = $generate_style;

    $this->parse();    
  }
  
  function parse() {
    $add_text = false;
    $key = null;
    $style = array();
    $is_switcher_style = false;

    /*
     * for now I check only for "admin-texts"
     */
    foreach( $this->values as $value ) {
      if( $add_text && 'close' !== $value[ 'tag' ]  ) {
        if( null == $key && "key" == $value[ 'tag' ] ) {
          $key = $value[ 'attributes' ][ 'name' ];
          
          if( ! is_array( $this->options ) ) {
            $this->options = get_option( $key );
          }
        } else {
          if( isset( $value[ 'attributes' ] ) ) {
            $name = $value[ 'attributes' ][ 'name' ];
            
//             if( is_array( $this->options ) ) {
              if( isset( $this->options[ $name ] ) ) {
                $v = $this->options[ $name ];
              } else {
                $v = "";
              }
              
              $add = ! empty( $v );
//             } else {
//               $v = get_option( $name );
              
//               $add = true;
//             }
            
            if( $add ) {
              CMLTranslations::add( strtolower( $this->group ) . "_" . $name,
                                    $v,
                                    $this->group );
              
              $this->names[] = $name;
            }
          }
        }
      }

      /* translable strings */
      if( "admin-texts" == $value[ 'tag' ] ) {
        if( 'open' == $value[ 'type' ] ) {
          $add_text = true;
        }

        //Done
        if( 'close' == $value[ 'type' ] ) {
          $add_text = false;
        }
      }

      /* language switcher */
      if( $this->style && $is_switcher_style ) {
        if( isset( $value[ 'value' ] ) ) {
          $v = $value[ 'value' ];
  
          switch( $value[ 'attributes' ][ 'name' ] ) {
          case 'font-current-normal':
            $style[] = "#cml-lang > li > a { color: $v; } ";
            break;
          case 'font-current-hover':
            $style[] = "#cml-lang > li > a:hover { color: $v; } ";
            break;
          case 'background-current-normal':
            $style[] = "#cml-lang > li > a { background-color: $v; } ";
            break;
          case 'background-current-hover':
            $style[] = "#cml-lang > li > a:hover { background-color: $v; } ";
            break;
          case 'font-other-normal':
            $style[] = "#cml-lang > li > ul a { color: $v; } ";
            break;
          case 'font-other-hover':
            $style[] = "#cml-lang > li > ul a:hover { color: $v; } ";
            break;
          case 'background-other-normal':
            $style[] = "#cml-lang > li > ul li { background-color: $v; } ";
            break;
          case 'background-other-hover':
            $style[] = "#cml-lang > li > ul li:hover { background-color: $v; } ";
            break;
          case 'border':
            $style[] = "#cml-lang { border-color: $v; } ";
            break;
          }
        }
      }

      if( isset( $value[ 'attributes' ][ 'name' ] ) &&
         "icl_lang_sel_config" == $value[ 'attributes' ][ 'name' ] ) {
        if( 'open' == $value[ 'type' ] ) {
          $is_switcher_style = true;
        }
      }
      
      if( $is_switcher_style ) {
        //Done
        if( 'close' == $value[ 'type' ] ) {
          $is_switcher_style = false;
        }
      }
      
      if( "icl_additional_css" == @$value[ 'attributes' ][ 'name' ] ) {
        $style[] = str_replace( "#cml-langlang_sel", "#cml-lang", $value[ 'value' ] );
      }
    }

    if( $this->style ) {
      file_put_contents( CML_UPLOAD_DIR . "combo_style.css", join( "\n", $style ) );
  
      if( ! empty( $style ) ) {
        echo '<div class="updated"><p>';
        echo CML_UPLOAD_DIR . "combo_style.css " . __( 'generated from "wpml-config.xml"', 'ceceppaml' );
        echo '</div>';
      }
    }

    if( ! isset( $this->names ) ) {
      $names = "";
    } else {
      $names = join( ",", $this->names );
    }
    update_option( "cml_translated_fields" . strtolower( $this->group ), $names );
    update_option( "cml_translated_fields" . strtolower( $this->group ) . "_key", $key );
  }
}

/*
 * Scan plugins folders to search "wpml-config.xml"
 */
function cml_admin_scan_plugins_folders() {
  $plugins = WP_CONTENT_DIR . "/plugins";
  
  $old = get_option( '_cml_wpml_config_paths', "" );

  $xmls = @glob( "$plugins/*/wpml-config.xml" );
  
  //nothing to do?
  if( empty( $xmls ) ) {
      return;
  }
  
  $link = add_query_arg( array( "lang" => "ceceppaml-translations-page" ), admin_url() );
  $txt  = __( 'Current plugins contains WPML Language Configuration Files ( wpml-config.xml )', 'ceceppaml' );
  $txt .= '<br /><ul class="cml-ul-list">';
  
  $not = array();

  foreach( $xmls as $file ) {
      $path = str_replace( WP_CONTENT_DIR . "/plugins/", "", dirname( $file ) );
      $supported = ( in_array( $path, $GLOBALS[ '_cml_supported_plugin' ] ) ) ? " (" . __( 'officially supported', 'ceceppaml' ) . ")" : "";
      $txt .= "<li>$path<i>$supported</i></li>";

      //not officially supported...
      if( empty( $supported ) ) {
          $not[] = dirname( $file );
      }
  }

  $not = join( ",", $not );
  update_option( '_cml_wpml_config_paths', $not );

  if( $not == $old ) {
    return;
  }

  $txt .= "</ul>";
  $txt .= sprintf( _( "Now you can translate Admin texts / wp_options in <%s>\"My Translations\"</a> page", "ceceppaml" ),
          'a href="' . $link . '"' );
  $txt .= "<br /><b>";
  $txt .= __( "Support to wpml-config.xml is experimental and could not works correctly", "ceceppaml" );
  $txt .= "<br /><b>";

  cml_admin_print_notice( "_cml_wpml_config", $txt );
}

/*
 * Google XML Sitemap
 *
 * http://wordpress.org/plugins/google-sitemap-generator/
 */
add_filter( 'cml_translate_home_url', 'cml_yoast_translate_home_url', 10, 2 );
CMLUtils::_append( "_seo", array(
                                'pagenow' => "options-general.php",
                                'page' => "google-sitemap-generator/sitemap.php",
                               )
                );

/*
 * Yoast
 *
 * https://yoast.com/wordpress/
 */
function cml_yoast_seo_strings( $types ) {
  if( defined( 'WPSEO_VERSION' ) ) {
    //CMLTranslations::delete( "_YOAST" );
    $options = get_wpseo_options();

    $xml = WPSEO_PATH . "wpml-config.xml";
    new CML_WPML_Parser( $xml, "_YOAST", $options );

    $types[ "_YOAST" ] = "YOAST";
  }
  
  return $types;
}

/*
 * translate yoast settings
 */
function cml_yoast_translate_options() {
  global $wpseo_front;

  if( ! defined( 'WPSEO_VERSION' ) || is_admin() ) return;

  if( is_admin() ) { //|| CMLUtils::_get( "_real_language" ) == CMLLanguage::get_default_id() ) {
    return;
  }

  $names = get_option( "cml_translated_fields_yoast", array() );
  if( empty( $names ) ) return;

  $name = explode( ",", $names );
  foreach( get_wpseo_options() as $key => $opt ) {
    if( in_array( $key, $names ) ) {
      $value = CMLTranslations::get( CMLLanguage::get_current_id(),
                                                           "_yoast_$key",
                                                           "_YOAST" );

      if( empty( $value ) ) continue;

      $wpseo_front->options[ $key ] = $value;
    }
  }
}

/**
 * I don't have to translate home_url for *.xml and *.xsl
 */
function cml_yoast_translate_home_url( $translate, $url ) {
  if( defined( 'WPSEO_VERSION' ) && preg_match( "/.*xsl|.*xml/", $url ) ) {
    CMLUtils::_set( '_is_sitemap', 1 );

    return false;
  }
  
  //Nothing to do
  remove_filter( 'cml_yoast_translate_home_url', 10, 2 );

  return $translate;
}

add_filter( 'cml_my_translations', 'cml_yoast_seo_strings' );
add_action( 'wp_loaded', 'cml_yoast_translate_options' );
add_filter( 'cml_translate_home_url', 'cml_yoast_translate_home_url', 10, 2 );

/*
 * All in one seo
 *
 * https://wordpress.org/plugins/all-in-one-seo-pack/
 */
function cml_aioseo_strings( $groups ) {
  //Nothing to do  
  if( ! defined( 'AIOSEOP_VERSION' ) ) return $groups;

  global $aioseop_options;

  $xml = AIOSEOP_PLUGIN_DIR . "wpml-config.xml";
  new CML_WPML_Parser( $xml, "_AIOSEO", $aioseop_options );

  $groups[ "_AIOSEO" ] = "All in one SEO";
  
  return $groups;
}

function cml_aioseo_translate_options() {
  //Nothing to do  
  if( ! defined( 'AIOSEOP_VERSION' ) || is_admin() ) return;

  global $aioseop_options;
  
  $names = get_option( "cml_translated_fields_aioseo", array() );
  if( empty( $names ) ) return;

  $names = explode( ",", $names );

  foreach( $aioseop_options as $key => $opt ) {
    if( in_array( $key, $names ) ) {
      $value = CMLTranslations::get( CMLLanguage::get_current_id(),
                                                           "_aioseo_$key",
                                                           "_AIOSEO",
                                                           true);
      
      if( empty( $value ) ) return;
      
      $aioseop_options[ $key ] = $value;
    }
  }
}

function cml_aioseo_translate_home_url( $translate, $url ) {
  if( defined( 'AIOSEOP_VERSION' ) && preg_match( "/.*xsl|.*xml/", $url ) ) {
    CMLUtils::_set( '_is_sitemap', 1 );

    return false;
  }

  //Nothing to do
  remove_filter( 'cml_aioseo_translate_home_url', 10, 2 );

  return $translate;
}

add_filter( 'cml_my_translations', 'cml_aioseo_strings' );
add_action( 'wp_loaded', 'cml_aioseo_translate_options' );
add_filter( 'cml_translate_home_url', 'cml_aioseo_translate_home_url', 10, 2 );

/*
 * Theme contains wpml-config.xml?
 */
function cml_get_strings_from_wpml_config( $groups ) {
  if( ! is_admin() ) return;

  update_option( "cml_theme_use_wpml_config", 0 );

  $theme = wp_get_theme();

  $root = trailingslashit( $theme->theme_root ) . $theme->template;
  $filename = "$root/wpml-config.xml";
  $name = strtolower( $theme->get( 'Name' ) );

  if( file_exists( $filename ) ) {
    new CML_WPML_Parser( $filename, "_$name", null, true );

    echo '<div class="updated"><p>';
    echo __( 'Your theme is designed for WPML', 'ceceppaml' ) . '<br />';
    _e( 'Support for theme compatible with WPML is experimental and could not works correctly, if you need help contact me.', 'ceceppaml' );
    echo '</p></div>';

    update_option( "cml_theme_${name}_use_wpml_config", 1 );
    
    $groups[ "_$name" ] = sprintf( "%s: %s", __( 'Theme' ), $theme->get( 'Name' ) );
  }
  
  //Look for unsupported plugins
  $plugins = get_option( '_cml_wpml_config_paths', "" );
  if( empty( $plugins ) ) return $groups;

  $plugins = explode( ",", $plugins );  
  foreach( $plugins as $plugin ) {
    $path = str_replace( WP_CONTENT_DIR . "/plugins/", "", $plugin);

    new CML_WPML_Parser( "$plugin/wpml-config.xml", "_$path", null );
    
    $groups[ "_$path"] = sprintf( "%s: %s", __( 'Plugin' ), $path );
  }

  return $groups;
}

/*
 * current theme has wpml-config.xml?
 */
function cml_translate_theme_strings() {
  if( is_admin() ) return;

  $theme = wp_get_theme();
  $name = strtolower( $theme->get( 'Name' ) );
  
  CMLUtils::_set( "theme-name", $name );

  if( ! get_option( "cml_theme_${name}_use_wpml_config", 0 ) ) {
    return;
  }
  
  $names = get_option( "cml_translated_fields_{$name}", array() );
  if( empty( $names ) ) return;

  $options = get_option( "cml_translated_fields_{$name}_key", "" );
  if( empty( $options ) ) {
    return;
  }

  $options = & $GLOBALS[ $options ];
  foreach( $options as $key => $value ) {
    $v = CMLTranslations::get( CMLLanguage::get_current_id(),
                              "_{$theme}_{$name}",
                              "_{$theme}" );
                              
    if( empty( $v ) ) continue;

    $options[ $key ] = $v;                            
  }

/*
  $names = explode( ",", $names );
  foreach( $names as $name ) {
    $value = get_option( $name );

    //wp hook don't pass me the option key, so I have to retrive default value before apply filter
    @add_filter( "option_$name", cml_translate_theme_option( $name, $value ), 10 );
  }*/

  //Not officially supported plugin
  $plugins = get_option( '_cml_wpml_config_paths', "" );
  $plugins = explode( ",", $plugins );  
  foreach( $plugins as $plugin ) {
    $path = str_replace( WP_CONTENT_DIR . "/plugins/", "", $plugin);

    $names = get_option( "cml_translated_fields_{$path}", "" );
    if( empty( $names ) ) continue;

    $options = get_option( "cml_translated_fields_{$path}_key", "" );
    if( empty( $options ) ) {
      continue;
    }

    $options = & $GLOBALS[ $options ];
    foreach( $options as $key => $value ) {
      $v = CMLTranslations::get( CMLLanguage::get_current_id(),
                                "_{$path}_{$name}",
                                "_{$path}" );
                                
      if( empty( $v ) ) continue;

      $options[ $key ] = $v;                            
    }
/*
    $names = explode( ",", $names );
    foreach( $names as $name ) {
      $value = get_option( $name );
      
      //wp hook don't pass me the option key, so I have to retrive default value before apply filter
      @add_filter( "option_$name", cml_translate_wp_option( $path, $name, $value ), 10, 3 );
    }*/
  }
}

// /*
//  * translate theme/(not supported) plugins
//  */
// function cml_translate_theme_option( $name, $value ) {
//   $theme = CMLUtils::_get( "theme-name" );
// 
//   $v = CMLTranslations::get( CMLLanguage::get_current_id(),
//                             "_{$theme}_{$name}",
//                             "_{$theme}" );
// 
//   return ( ! empty( $v ) ) ? $v : $value;
// }
// 
// /*
//  * translate theme/(not supported) plugins
//  */
// function cml_translate_wp_option( $group, $name, $value ) {
//   $v = CMLTranslations::get( CMLLanguage::get_current_id(),
//                             "_{$group}_{$name}",
//                             "_{$group}" );
// 
//   return ( ! empty( $v ) ) ? $v : $value;
// }

add_filter( 'cml_my_translations', 'cml_get_strings_from_wpml_config', 99 );
add_action( 'wp_loaded', 'cml_translate_theme_strings', 10 );
?>
