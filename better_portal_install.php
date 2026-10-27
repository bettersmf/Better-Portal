<?php
/**
 * Better Portal hook installer / self-healer.
 *
 * @package BetterPortal
 * @version 0.1.0
 */

if (!defined('SMF'))
    die('No direct access...');

global $sourcedir, $modSettings, $smcFunc;
require_once($sourcedir . '/Subs.php');

$file = '$sourcedir/BetterPortal.php';
$hooks = array(
    'integrate_actions' => 'BetterPortal_Actions',
    'integrate_admin_areas' => 'BetterPortal_AdminAreas',
    'integrate_menu_buttons' => 'BetterPortal_MenuButtons',
    'integrate_messageindex_buttons' => 'BetterPortal_MessageIndexButtons',
    'integrate_display_buttons' => 'BetterPortal_DisplayButtons',
    'integrate_post_end' => 'BetterPortal_PostEnd',
    'integrate_post2_end' => 'BetterPortal_Post2End',
    'integrate_redirect' => 'BetterPortal_Redirect',
);

foreach ($hooks as $hook => $function)
{
    remove_integration_function($hook, $function, true, $file);
    remove_integration_function($hook, $function, true);
    add_integration_function($hook, $function, true, $file);
}

$defaults = array(
    'betterportal_mode' => 'separate',
    'betterportal_menu_button' => 1,
    'betterportal_version' => '0.1.0',
);

foreach ($defaults as $setting => $default)
{
    if (!isset($modSettings[$setting]))
    {
        updateSettings(array($setting => $default));
        $modSettings[$setting] = $default;
    }
}

// The default-action hook must only exist when Better Portal owns the forum home.
remove_integration_function('integrate_default_action', 'BetterPortal_DefaultAction', true, $file);
remove_integration_function('integrate_default_action', 'BetterPortal_DefaultAction', true);
if (!empty($modSettings['betterportal_mode']) && $modSettings['betterportal_mode'] === 'home')
    add_integration_function('integrate_default_action', 'BetterPortal_DefaultAction', true, $file);

// dev9: page-title visibility existed before public page titles were rendered.
// Turn those legacy no-op flags off once so upgrading preserves the dev5-dev8
// public appearance. Admins can enable a heading per page after the upgrade.
if (empty($modSettings['betterportal_dev9_page_title_migrated']))
{
    $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_pages SET show_title={int:off}', array('off' => 0));
    updateSettings(array('betterportal_dev9_page_title_migrated' => 1));
    $modSettings['betterportal_dev9_page_title_migrated'] = 1;
}

updateSettings(array('betterportal_version' => '0.1.0'));
