<?php

require_once dirname(__FILE__) . '/includes/alpha.inc';

/**
 * Implements hook_theme().
 */
function alpha_theme($existing, $type, $theme, $path) {
  return array(
  	'section' => array(
      'template' => 'section',
      'path' => $path . '/templates',
      'render element' => 'elements',
      'pattern' => 'section__',
      'preprocess functions' => array(
        'template_preprocess', 
        'template_preprocess_section',
        'alpha_preprocess',
        'alpha_preprocess_section',
      ),
      'process functions' => array(
        'template_process', 
        'template_process_section',
        'alpha_process',
        'alpha_process_section'
      ),
    ),  
  	'zone' => array(
      'template' => 'zone',
      'path' => $path . '/templates',
      'render element' => 'elements',
      'pattern' => 'zone__',
      'preprocess functions' => array(
        'template_preprocess', 
        'template_preprocess_zone',
        'alpha_preprocess',
        'alpha_preprocess_zone',
      ),
      'process functions' => array(
        'template_process', 
        'template_process_zone',
        'alpha_process',
        'alpha_process_zone'
      ),
    ),
  );
}

/**
 * Implements hook_preprocess().
 */
function alpha_preprocess(&$vars, $hook) {
  alpha_invoke('preprocess', $hook, $vars);
}

/**
 * Implements hook_process().
 */
function alpha_process(&$vars, $hook) {
  alpha_invoke('process', $hook, $vars);
}

/**
 * Implements hook_theme_registry_alter().
 */
function alpha_theme_registry_alter(&$registry) {
  alpha_build_registry($registry);
  alpha_register_grids();
  alpha_register_css();
  alpha_register_libraries();
}

/**
 * @todo
 */
function alpha_element_info_alter(&$elements) {
  array_unshift($elements['styles']['#pre_render'], 'alpha_pre_render_styles');
}

/**
 * @todo
 */
function alpha_pre_render_styles($elements) {
  $groups = array();
  
  foreach ($elements['#items'] as $basename => $item) {
    if (!empty($item['responsive']) && $item['type'] == 'file' && $item['preprocess']) {
      ksort($item['browsers']);
      
      $key = hash('sha256', serialize(array($item['group'], $item['every_page'], $item['browsers'])));
      
      if (!isset($groups[$key])) {
        $groups[$key]['browsers'] = $item['browsers'];
        $groups[$key]['group'] = $item['group'];
        $groups[$key]['items'] = array();
      }
      
      $groups[$key]['items'][$basename] = $item;
      
      //unset($elements['#items'][$basename]);
    }
  }
  
  foreach ($groups as $key => $stylesheets) {
    //krumo($stylesheets);
  }
  
  return $elements;
}

/**
 * @todo
 */
function alpha_build_css_cache($css) {
  $data = '';
  $uri = '';
  $map = variable_get('drupal_css_cache_files', array());
  $key = hash('sha256', serialize($css));
  if (isset($map[$key])) {
    $uri = $map[$key];
  }

  if (empty($uri) || !file_exists($uri)) {
    // Build aggregate CSS file.
    foreach ($css as $stylesheet) {
      // Only 'file' stylesheets can be aggregated.
      if ($stylesheet['type'] == 'file') {
        $contents = drupal_load_stylesheet($stylesheet['data'], TRUE);

        // Build the base URL of this CSS file: start with the full URL.
        $css_base_url = file_create_url($stylesheet['data']);
        // Move to the parent.
        $css_base_url = substr($css_base_url, 0, strrpos($css_base_url, '/'));
        // Simplify to a relative URL if the stylesheet URL starts with the
        // base URL of the website.
        if (substr($css_base_url, 0, strlen($GLOBALS['base_root'])) == $GLOBALS['base_root']) {
          $css_base_url = substr($css_base_url, strlen($GLOBALS['base_root']));
        }

        _drupal_build_css_path(NULL, $css_base_url . '/');
        // Anchor all paths in the CSS with its base URL, ignoring external and absolute paths.
        $data .= preg_replace_callback('/url\(\s*[\'"]?(?![a-z]+:|\/+)([^\'")]+)[\'"]?\s*\)/i', '_drupal_build_css_path', $contents);
      }
    }

    // Per the W3C specification at http://www.w3.org/TR/REC-CSS2/cascade.html#at-import,
    // @import rules must proceed any other style, so we move those to the top.
    $regexp = '/@import[^;]+;/i';
    preg_match_all($regexp, $data, $matches);
    $data = preg_replace($regexp, '', $data);
    $data = implode('', $matches[0]) . $data;

    // Prefix filename to prevent blocking by firewalls which reject files
    // starting with "ad*".
    $filename = 'css_' . drupal_hash_base64($data) . '.css';
    // Create the css/ within the files folder.
    $csspath = 'public://css';
    $uri = $csspath . '/' . $filename;
    // Create the CSS file.
    file_prepare_directory($csspath, FILE_CREATE_DIRECTORY);
    if (!file_exists($uri) && !file_unmanaged_save_data($data, $uri, FILE_EXISTS_REPLACE)) {
      return FALSE;
    }
    // If CSS gzip compression is enabled, clean URLs are enabled (which means
    // that rewrite rules are working) and the zlib extension is available then
    // create a gzipped version of this file. This file is served conditionally
    // to browsers that accept gzip using .htaccess rules.
    if (variable_get('css_gzip_compression', TRUE) && variable_get('clean_url', 0) && extension_loaded('zlib')) {
      if (!file_exists($uri . '.gz') && !file_unmanaged_save_data(gzencode($data, 9, FORCE_GZIP), $uri . '.gz', FILE_EXISTS_REPLACE)) {
        return FALSE;
      }
    }
    // Save the updated map.
    $map[$key] = $uri;
    variable_set('drupal_css_cache_files', $map);
  }
  return $uri;
}

/**
 * Implements hook_css_alter().
 */
function alpha_css_alter(&$css) {
  $settings = alpha_settings();
  
  foreach(array_filter($settings['exclude']) as $item) {
    unset($css[$item]);
  }
}

/**
 * Implements hook_page_alter().
 */
function alpha_page_alter(&$vars) {
  $settings = alpha_settings();
  $regions = $columns = array();
  $reference = &drupal_static('alpha_regions');

  if ($settings['debug']['access']) {
    if ($settings['debug']['block']) {      
      foreach (alpha_regions() as $region => $item) {
        if ($item['enabled']) {
          $block = new stdClass();
          $block->delta = 'debug-' . $region;
          $block->region = $region;
          $block->module = 'alpha-debug';
          $block->subject = $item['name'];
          
          $vars[$region]['#sorted'] = FALSE;
          $vars[$region]['alpha_debug_' . $region] = array(       
            '#block' => $block,
            '#weight' => -999,
            '#markup' => t('This is a debugging block'),
            '#theme_wrappers' => array('block'),
          );
        }
      }
    }    
       
    if ($settings['responsive'] && $settings['debug']['grid']) {
      if (empty($vars['page_bottom'])) {
        $vars['page_bottom']['#region'] = 'page_bottom';
        $vars['page_bottom']['#theme_wrappers'] = array('region');
      }
        
      $vars['page_bottom']['alpha_resize_indicator'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="alpha-resize-indicator"></div>',
      );
    }
  }
  
  foreach (alpha_regions() as $region => $item) {  
    if ($item['enabled'] && ($item['force'] || isset($vars[$region]))) {
      $zone = $item['zone'];
      
      $regions[$zone][$region] = isset($vars[$region]) ? $vars[$region] : array();
      $regions[$zone][$region]['#region'] = $region;
      $regions[$zone][$region]['#theme_wrappers'] = array('region');
      $regions[$zone][$region]['#data'] = $item;      
      $regions[$zone][$region]['#weight'] = (int) $item['weight'];
      
      if ($children = element_children($regions[$zone][$region])) {
        $last = count($children) - 1;
        
        foreach ($children as $element) {
          $regions[$zone][$region][$element]['#first'] = $element == $children[0];
          $regions[$zone][$region][$element]['#last'] = $element == $children[$last];
        }
      }
    }
    
    unset($vars[$region]);
  }
  
  foreach (alpha_zones() as $zone => $item) {
    if ($item['enabled'] && ($item['force'] || isset($regions[$zone]))) {
      $section = $item['section'];
      $columns[$item['columns']] = $item['columns']; 
      
      if (!empty($item['primary']) && isset($regions[$zone][$item['primary']])) {
        $children = element_children($regions[$zone]);
        $theme = $GLOBALS['theme_key'];
        $primary = &$regions[$zone][$item['primary']];
        $primary['#weight'] = -999;
        $primary['#data']['columns'] = $item['columns'] - $primary['#data']['prefix'] - $primary['#data']['suffix'];
        $primary['#data']['width'] = $item['columns'];
           
        foreach ($children as $region) {
          if (!$regions[$zone][$region]['#data']['primary']) {
            $primary['#data']['columns'] -= $regions[$zone][$region]['#data']['width'];
            $primary['#data']['width'] -= $regions[$zone][$region]['#data']['width'];
    
            if ($primary['#data']['weight'] > $regions[$zone][$region]['#data']['weight']) {
              $primary['#data']['push'] += $regions[$zone][$region]['#data']['width'];              
            }
          }
        }
        
        $reference[$theme][$item['primary']]['columns'] = $primary['#data']['columns'];
        $reference[$theme][$item['primary']]['width'] = $primary['#data']['width'];
        $reference[$theme][$item['primary']]['push'] = $primary['#data']['push'];
        
        foreach ($children as $region) {
          if (!$regions[$zone][$region]['#data']['primary'] && $primary['#data']['weight'] > $regions[$zone][$region]['#data']['weight']) {
            $regions[$zone][$region]['#data']['pull'] = $primary['#data']['width'];            
            $reference[$theme][$region]['pull'] = $primary['#data']['width'];
          }
        }
      }
      
      $vars[$section][$zone] = isset($regions[$zone]) ? $regions[$zone] : array();
      $vars[$section][$zone]['#theme_wrappers'] = array('zone');      
      $vars[$section][$zone]['#zone'] = $zone;
      $vars[$section][$zone]['#weight'] = (int) $item['weight'];
      $vars[$section][$zone]['#sorted'] = FALSE;
      $vars[$section][$zone]['#data'] = $item;
      $vars[$section][$zone]['#data']['type'] = !empty($item['primary']) && isset($vars[$section][$zone][$item['primary']]) ? 'dynamic' : 'static';
    }
  }

  foreach (alpha_sections() as $section => $item) {
    if (isset($vars[$section])) {   
      $vars[$section]['#theme_wrappers'] = array('section');
      $vars[$section]['#section'] = $section;
    }
  }
  
  alpha_include_grid($settings['grid'], $columns);
  
  if ($settings['debug']['grid'] && $settings['debug']['access']) {
    alpha_debug_grid($settings['grid'], $columns);
  }
}

/**
 * Implements hook_preprocess_section().
 */
function template_preprocess_section(&$vars) {
  $vars['theme_hook_suggestions'][] = 'section__' . $vars['elements']['#section'];  
  $vars['section'] = $vars['elements']['#section'];  
  $vars['content'] = $vars['elements']['#children'];
  $vars['attributes_array']['id'] = drupal_html_id('section-' . $vars['section']);
  $vars['attributes_array']['class'] = array('section', $vars['attributes_array']['id']);
}

/**
 * Implements hook_preprocess_zone().
 */
function template_preprocess_zone(&$vars) {
  $data = $vars['elements']['#data'];
  $vars['theme_hook_suggestions'] = array('zone__' . $vars['elements']['#zone']);
  $vars['zone'] = $vars['elements']['#zone'];
  $vars['content'] = $vars['elements']['#children'];  
  $vars['columns'] = $data['columns'];
  $vars['wrapper'] = $data['wrapper'];
  $vars['type'] = $data['type'];  
  $vars['attributes_array']['id'] = drupal_html_id('zone-' . $vars['zone']);
  $vars['attributes_array']['class'] = array('zone', $vars['attributes_array']['id'], 'zone-' . $vars['type'], 'container-' . $vars['columns'], 'clearfix');
  
  if (!empty($data['css'])) {
    $extra = array_map('drupal_html_class', explode(' ', $data['css']));
      
    foreach ($extra as $class) {
      $vars['attributes_array']['class'][] = $class;
    }
  }
  
  if ($vars['wrapper']) {
    $vars['wrapper_attributes_array']['id'] = $vars['attributes_array']['id'] . '-wrapper';
    $vars['wrapper_attributes_array']['class'] = array('zone-wrapper', 'zone-' . $vars['type'] . '-wrapper', $vars['wrapper_attributes_array']['id']);
    
    if (!empty($data['wrapper_css'])) {
      $extra = array_map('drupal_html_class', explode(' ', $data['wrapper_css']));
        
      foreach ($extra as $class) {
        $vars['wrapper_attributes_array']['class'][] = $class;
      }
    }
    
    $vars['wrapper_attributes_array']['class'][] = 'clearfix';
  }
}

/**
 * Implements hook_process_zone().
 */
function template_process_zone(&$vars) {
  $vars['wrapper_attributes'] = isset($vars['wrapper_attributes_array']) ? drupal_attributes($vars['wrapper_attributes_array']) : '';
}