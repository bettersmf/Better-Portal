<?php
/** Better Portal destructive Remove data cleanup. @version 0.1.0 */
if (!defined('SMF')) die('No direct access...');

global $sourcedir, $smcFunc;
require_once($sourcedir . '/Subs.php');
db_extend('packages');

$smcFunc['db_drop_table']('{db_prefix}betterportal_blocks');
$smcFunc['db_drop_table']('{db_prefix}betterportal_rows');
$smcFunc['db_drop_table']('{db_prefix}betterportal_page_nav');
$smcFunc['db_drop_table']('{db_prefix}betterportal_pages');

$smcFunc['db_query']('', '
    DELETE FROM {db_prefix}settings
    WHERE variable LIKE {string:prefix}',
    array('prefix' => 'betterportal\_%')
);
