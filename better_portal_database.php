<?php
/** Better Portal database installer / upgrader. @version 0.1.0 */
if (!defined('SMF')) die('No direct access...');

global $sourcedir, $smcFunc, $user_info;
require_once($sourcedir . '/Subs.php');
db_extend('packages');

$smcFunc['db_create_table']('{db_prefix}betterportal_pages', array(
    array('name' => 'id_page', 'type' => 'int', 'unsigned' => true, 'auto' => true),
    array('name' => 'slug', 'type' => 'varchar', 'size' => 80, 'default' => ''),
    array('name' => 'title', 'type' => 'varchar', 'size' => 160, 'default' => ''),
    array('name' => 'page_type', 'type' => 'varchar', 'size' => 20, 'default' => 'portal'),
    array('name' => 'is_home', 'type' => 'tinyint', 'unsigned' => true, 'default' => 0),
    array('name' => 'is_enabled', 'type' => 'tinyint', 'unsigned' => true, 'default' => 1),
    array('name' => 'show_title', 'type' => 'tinyint', 'unsigned' => true, 'default' => 1),
    array('name' => 'allowed_groups', 'type' => 'text'),
    array('name' => 'layout_version', 'type' => 'smallint', 'unsigned' => true, 'default' => 1),
    array('name' => 'created_at', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'updated_at', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'created_by', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'updated_by', 'type' => 'int', 'unsigned' => true, 'default' => 0),
), array(
    array('type' => 'primary', 'columns' => array('id_page')),
    array('type' => 'unique', 'columns' => array('slug')),
    array('type' => 'index', 'columns' => array('is_home', 'is_enabled')),
), array(), 'ignore');

// dev19: navigation metadata is kept separate from page content/audience data so
// menu controls can evolve without changing the core page record.
$smcFunc['db_create_table']('{db_prefix}betterportal_page_nav', array(
    array('name' => 'id_page', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'show_in_nav', 'type' => 'tinyint', 'unsigned' => true, 'default' => 1),
    array('name' => 'menu_label', 'type' => 'varchar', 'size' => 160, 'default' => ''),
    array('name' => 'nav_order', 'type' => 'smallint', 'unsigned' => true, 'default' => 0),
), array(
    array('type' => 'primary', 'columns' => array('id_page')),
    array('type' => 'index', 'columns' => array('show_in_nav', 'nav_order')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}betterportal_rows', array(
    array('name' => 'id_row', 'type' => 'int', 'unsigned' => true, 'auto' => true),
    array('name' => 'id_page', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'row_order', 'type' => 'smallint', 'unsigned' => true, 'default' => 0),
    array('name' => 'layout', 'type' => 'varchar', 'size' => 32, 'default' => '1'),
    array('name' => 'css_class', 'type' => 'varchar', 'size' => 120, 'default' => ''),
    array('name' => 'is_enabled', 'type' => 'tinyint', 'unsigned' => true, 'default' => 1),
), array(
    array('type' => 'primary', 'columns' => array('id_row')),
    array('type' => 'index', 'columns' => array('id_page', 'row_order')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}betterportal_blocks', array(
    array('name' => 'id_block', 'type' => 'int', 'unsigned' => true, 'auto' => true),
    array('name' => 'id_page', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'id_row', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'column_key', 'type' => 'varchar', 'size' => 20, 'default' => 'col1'),
    array('name' => 'block_type', 'type' => 'varchar', 'size' => 80, 'default' => ''),
    array('name' => 'title', 'type' => 'varchar', 'size' => 160, 'default' => ''),
    array('name' => 'block_order', 'type' => 'smallint', 'unsigned' => true, 'default' => 0),
    array('name' => 'is_enabled', 'type' => 'tinyint', 'unsigned' => true, 'default' => 1),
    array('name' => 'show_title', 'type' => 'tinyint', 'unsigned' => true, 'default' => 1),
    array('name' => 'allowed_groups', 'type' => 'text'),
    array('name' => 'cache_ttl', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'settings', 'type' => 'mediumtext'),
    array('name' => 'created_at', 'type' => 'int', 'unsigned' => true, 'default' => 0),
    array('name' => 'updated_at', 'type' => 'int', 'unsigned' => true, 'default' => 0),
), array(
    array('type' => 'primary', 'columns' => array('id_block')),
    array('type' => 'index', 'columns' => array('id_page', 'id_row', 'column_key', 'block_order')),
    array('type' => 'index', 'columns' => array('block_type')),
), array(), 'ignore');

// Seed the first portal only when no Better Portal page exists. This makes reinstall safe.
$request = $smcFunc['db_query']('', '
    SELECT COUNT(*) AS page_count
    FROM {db_prefix}betterportal_pages',
    array()
);
$row = $smcFunc['db_fetch_assoc']($request);
$smcFunc['db_free_result']($request);

if (empty($row['page_count']))
{
    $now = time();
    $member = !empty($user_info['id']) ? (int) $user_info['id'] : 0;
    $id_page = $smcFunc['db_insert']('insert', '{db_prefix}betterportal_pages', array(
        'slug' => 'string-80', 'title' => 'string-160', 'page_type' => 'string-20',
        'is_home' => 'int', 'is_enabled' => 'int', 'show_title' => 'int', 'allowed_groups' => 'string',
        'layout_version' => 'int', 'created_at' => 'int', 'updated_at' => 'int',
        'created_by' => 'int', 'updated_by' => 'int',
    ), array(
        'home', 'Welcome', 'portal', 1, 1, 0, '["everyone"]',
        1, $now, $now, $member, $member,
    ), array('id_page'), 1);

    if (!empty($id_page))
    {
        $id_row = $smcFunc['db_insert']('insert', '{db_prefix}betterportal_rows', array(
            'id_page' => 'int', 'row_order' => 'int', 'layout' => 'string-32',
            'css_class' => 'string-120', 'is_enabled' => 'int',
        ), array(
            $id_page, 1, '1', '', 1,
        ), array('id_row'), 1);

        if (!empty($id_row))
        {
            $seed_settings = json_encode(array(
                'body' => "[b]Welcome to Better Portal![/b]\n\nThis is the first Better Portal block. Edit it from Administration → Configuration → Better Portal → Page Builder.",
                'parse_bbc' => 1,
            ));

            $smcFunc['db_insert']('insert', '{db_prefix}betterportal_blocks', array(
                'id_page' => 'int', 'id_row' => 'int', 'column_key' => 'string-20',
                'block_type' => 'string-80', 'title' => 'string-160', 'block_order' => 'int',
                'is_enabled' => 'int', 'show_title' => 'int', 'allowed_groups' => 'string',
                'cache_ttl' => 'int', 'settings' => 'string', 'created_at' => 'int', 'updated_at' => 'int',
            ), array(
                $id_page, $id_row, 'col1', 'custom_text', 'Welcome', 1,
                1, 1, '["everyone"]', 0, $seed_settings, $now, $now,
            ), array('id_block'));
        }
    }
}

// dev2 migration: repair the untouched dev1 seed that stored literal \n sequences.
$request = $smcFunc['db_query']('', 'SELECT id_block, settings FROM {db_prefix}betterportal_blocks WHERE block_type={string:type}', array('type' => 'custom_text'));
while ($bp = $smcFunc['db_fetch_assoc']($request))
{
    $decoded = json_decode($bp['settings'], true);
    if (is_array($decoded) && isset($decoded['body']) && strpos($decoded['body'], '\\n') !== false)
    {
        $decoded['body'] = str_replace('\\n', "\n", $decoded['body']);
        $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_blocks SET settings={string:settings} WHERE id_block={int:id}', array('settings'=>json_encode($decoded), 'id'=>(int)$bp['id_block']));
    }
}
$smcFunc['db_free_result']($request);

// dev29 migration: rows are first-class again. Preserve every existing row,
// normalize its layout/column references, and only create a row when a page has none.
$layout_columns = array(
    '1' => array('col1'),
    '1-1' => array('col1', 'col2'),
    '2-1' => array('col1', 'col2'),
    '1-2' => array('col1', 'col2'),
    '1-1-1' => array('col1', 'col2', 'col3'),
);

$request = $smcFunc['db_query']('', 'SELECT id_page FROM {db_prefix}betterportal_pages ORDER BY id_page', array());
$page_ids = array();
while ($portal_page = $smcFunc['db_fetch_assoc']($request))
    $page_ids[] = (int) $portal_page['id_page'];
$smcFunc['db_free_result']($request);

foreach ($page_ids as $portal_page_id)
{
    $rows = array();
    $request = $smcFunc['db_query']('', '
        SELECT id_row, layout
        FROM {db_prefix}betterportal_rows
        WHERE id_page={int:id_page}
        ORDER BY row_order, id_row', array('id_page' => $portal_page_id));
    while ($portal_row = $smcFunc['db_fetch_assoc']($request))
        $rows[] = $portal_row;
    $smcFunc['db_free_result']($request);

    if (empty($rows))
    {
        $id_row = $smcFunc['db_insert']('insert', '{db_prefix}betterportal_rows', array(
            'id_page' => 'int', 'row_order' => 'int', 'layout' => 'string-32',
            'css_class' => 'string-120', 'is_enabled' => 'int',
        ), array($portal_page_id, 1, '1', '', 1), array('id_row'), 1);
        $rows[] = array('id_row' => $id_row, 'layout' => '1');
    }

    foreach ($rows as $index => $portal_row)
    {
        $layout = isset($layout_columns[$portal_row['layout']]) ? $portal_row['layout'] : '1';
        $valid_columns = $layout_columns[$layout];
        $fallback_column = $valid_columns[0];
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}betterportal_rows
            SET row_order={int:row_order}, layout={string:layout}, is_enabled={int:enabled}
            WHERE id_row={int:id_row}', array(
                'row_order' => $index + 1, 'layout' => $layout, 'enabled' => 1,
                'id_row' => (int) $portal_row['id_row'],
            ));
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}betterportal_blocks
            SET column_key={string:fallback}
            WHERE id_row={int:id_row} AND column_key NOT IN ({array_string:valid})', array(
                'fallback' => $fallback_column,
                'id_row' => (int) $portal_row['id_row'],
                'valid' => $valid_columns,
            ));
    }
}


// dev19 migration: every existing page gets a navigation record. Use the page
// id as a stable initial order so upgrades preserve the familiar page sequence.
$request = $smcFunc['db_query']('', 'SELECT id_page FROM {db_prefix}betterportal_pages ORDER BY id_page', array());
while ($portal_nav_page = $smcFunc['db_fetch_assoc']($request))
{
    $portal_nav_id = (int) $portal_nav_page['id_page'];
    $check = $smcFunc['db_query']('', 'SELECT id_page FROM {db_prefix}betterportal_page_nav WHERE id_page={int:id_page} LIMIT 1', array('id_page' => $portal_nav_id));
    $exists = $smcFunc['db_fetch_assoc']($check);
    $smcFunc['db_free_result']($check);
    if (empty($exists))
    {
        $smcFunc['db_insert']('insert', '{db_prefix}betterportal_page_nav', array(
            'id_page' => 'int', 'show_in_nav' => 'int', 'menu_label' => 'string-160', 'nav_order' => 'int',
        ), array($portal_nav_id, 1, '', min(65535, $portal_nav_id * 10)), array('id_page'));
    }
}
$smcFunc['db_free_result']($request);


// dev32k Better Media -> Better Gallery display-title migration.
// Only rename exact legacy defaults so administrator-customized block titles
// are never overwritten. Internal block_type keys intentionally remain
// better_media_* for backwards compatibility.
$betterportal_gallery_title_migrations = array(
    'better_media_latest' => array(
        'Latest Media Items' => 'Latest Gallery Items',
        'Latest Media' => 'Latest Gallery Items',
    ),
    'better_media_albums' => array(
        'Latest Media Albums' => 'Latest Gallery Albums',
        'Media Albums' => 'Latest Gallery Albums',
    ),
    'better_media_browser' => array(
        'Better Media Browser' => 'Better Gallery Browser',
    ),
);

foreach ($betterportal_gallery_title_migrations as $block_type => $title_map)
{
    foreach ($title_map as $old_title => $new_title)
    {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}betterportal_blocks
            SET title={string:new_title}
            WHERE block_type={string:block_type}
              AND title={string:old_title}', array(
                'new_title' => $new_title,
                'block_type' => $block_type,
                'old_title' => $old_title,
            ));
    }
}
