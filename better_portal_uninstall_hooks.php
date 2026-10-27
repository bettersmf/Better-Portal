<?php
/** Better Portal non-destructive uninstall hook cleanup. @version 0.1.0 */
if (!defined('SMF')) die('No direct access...');

global $sourcedir;
require_once($sourcedir . '/Subs.php');

$file = '$sourcedir/BetterPortal.php';
$hooks = array(
    'integrate_actions' => 'BetterPortal_Actions',
    'integrate_admin_areas' => 'BetterPortal_AdminAreas',
    'integrate_menu_buttons' => 'BetterPortal_MenuButtons',
    'integrate_default_action' => 'BetterPortal_DefaultAction',
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
}
