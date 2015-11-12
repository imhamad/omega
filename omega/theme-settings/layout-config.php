<?php

// create the container for settings
$form['layout-config'] = array(
  '#type' => 'details',
  '#attributes' => array('class' => array('debug')),
  '#title' => t('Layout Configuration'),
  '#description' => t('<p>The options here allow you to enable or disable the entire Omega.gs Layout Management system, as well as choose which layout to use as the default layout, and on various other site pages. </p>'),
  '#weight' => -800,
  '#group' => 'omega',
  //'#open' => TRUE,
  '#tree' => TRUE,
);

// #states flag to indicate that Omega.gs has been enabled
$omegaGSon = array(
  'invisible' => array(
    ':input[name="enable_omegags_layout"]' => array('checked' => FALSE),
  ),
);

// #states flag to indicate that Omega.gs has been disabled
$omegaGSoff = array(
  'invisible' => array(
    ':input[name="enable_omegags_layout"]' => array('checked' => TRUE),
  ),
);

$enable_omegags_layout = theme_get_setting('enable_omegags_layout', $theme);
$form['enable_omegags_layout'] = array(
  '#type' => 'checkbox',
  '#title' => t('Enable Omega.gs Layout Management'),
  '#description' => t('Turning on the Omega.gs layout management system will allow you to configure your site region layout in each breakpoint via a visual interface. <strong>#easybutton</strong>'),
  '#default_value' => isset($enable_omegags_layout) ? $enable_omegags_layout : TRUE,
  '#group' => 'layout-config',
  '#weight' => -999,
);

$form['layout-config']['non_omegags_info'] = array(
  '#type' => 'item',
  '#prefix' => '',
  '#markup' => '<div class="messages messages--warning omega-styles-info"><p>Since you have "<strong><em>disabled the Awesome</em></strong>" above, now the Omega.gs layout is not being used in your theme/subtheme. This means that you will need to provide your own layout system. Easy huh?!? Although, I would really just use the awesome...</p></div>',
  '#suffix' => '',
  '#weight' => -99,
  '#states' => $omegaGSoff,
);

$availableLayouts = _omega_layout_select_options($layouts);

$form['layout-config']['default_layout'] = array(
  '#prefix' => '<div class="default-layout-select">',
  '#suffix' => '</div>',
  '#description' => '<p class="description">The Default Layout is used on any/every page rendered by the <strong>' . $theme . '</strong> theme. Additional layouts can be used for other pages/sections as defined in the additional select options below.</p>',
  '#type' => 'select',
  '#attributes' => array(
    'class' => array(
      'layout-select', 
      'clearfix'
    ),
  ),
  '#title' => 'Default: Select Layout',
  '#options' => $availableLayouts,
  '#default_value' => isset($defaultLayout) ? $defaultLayout : theme_get_setting('default_layout', $theme),
  '#tree' => FALSE,
  '#states' => $omegaGSon,
  // attempting possible jQuery intervention rather than ajax 
);

$homeLayout = isset($form_state->values['home_layout']) ? $form_state->values['home_layout'] : theme_get_setting('home_layout', $theme);
$form['layout-config']['home_layout'] = array(
  '#prefix' => '<div class="home-layout-select">',
  '#suffix' => '</div>',
  '#description' => '<p class="description">The Homepage Layout is used only on the home page rendered by the <strong>' . $theme . '</strong> theme.</p>',
  '#type' => 'select',
  '#attributes' => array(
    'class' => array(
      'layout-select', 
      'clearfix'
    ),
  ),
  '#title' => 'Homepage: Select Layout',
  '#options' => $availableLayouts,
  '#default_value' => isset($homeLayout) ? $homeLayout : theme_get_setting('default_layout', $theme),
  '#tree' => FALSE,
  '#states' => $omegaGSon,
  // attempting possible jQuery intervention rather than ajax 
);

// Show a select menu for each node type, allowing the selection
// of an alternate layout per node type.

/*
$types = node_type_get_types();

foreach ($types AS $ctype => $ctypeData) {
  $layout_name = $ctype . '_layout';
  $ctypeLayout = isset($form_state['values'][$layout_name]) ? $form_state['values'][$layout_name] : theme_get_setting($layout_name, $theme);
  
  $form['layout-config'][$layout_name] = array(
    '#prefix' => '<div class="' . $ctype . '-layout-select">',
    '#suffix' => '</div>',
    '#type' => 'select',
    '#attributes' => array(
      'class' => array(
        'layout-select', 
        'clearfix'
      ),
    ),
    '#title' => $ctypeData->name . ': Select Layout',
    '#options' => $availableLayouts,
    '#default_value' => isset($ctypeLayout) ? $ctypeLayout : theme_get_setting('default_layout', $theme),
    '#tree' => FALSE,
    '#states' => $omegaGSon,
    // attempting possible jQuery intervention rather than ajax 
  );  
}
*/