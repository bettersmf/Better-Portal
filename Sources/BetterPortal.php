<?php
/**
 * Better Portal core for SMF 2.1.x.
 *
 * @package BetterPortal
 * @version 0.1.0
 */

if (!defined('SMF'))
    die('No direct access...');

function BetterPortal_LoadLanguage()
{
    global $txt, $settings, $user_info;

    static $loaded = false;
    if (!$loaded)
    {
        loadLanguage('BetterPortal');

        // Theme Lab themes can contain snapshot copies of Better Portal's
        // language file. Preserve any theme-specific wording, but backfill
        // keys added by newer Better Portal releases from the default theme.
        $default_theme_dir = !empty($settings['default_theme_dir']) ? $settings['default_theme_dir'] : '';
        $language = !empty($user_info['language']) ? $user_info['language'] : 'english';
        $language_file = $default_theme_dir . '/languages/BetterPortal.' . $language . '.php';
        if ($default_theme_dir !== '' && !file_exists($language_file))
            $language_file = $default_theme_dir . '/languages/BetterPortal.english.php';

        if ($default_theme_dir !== '' && file_exists($language_file))
        {
            $default_txt = (static function ($file)
            {
                $txt = array();
                require $file;
                return $txt;
            })($language_file);

            foreach ($default_txt as $key => $value)
                if (!array_key_exists($key, $txt))
                    $txt[$key] = $value;

            // The Better Media -> Better Gallery rebrand changed existing keys,
            // not just added new ones. Theme Lab-generated themes may still
            // carry older snapshot values for those keys, so force the current
            // default-theme wording for the rebrand-specific labels.
            $gallery_rebrand_keys = array(
                'betterportal_block_better_media_latest',
                'betterportal_block_better_media_latest_desc',
                'betterportal_block_better_media_albums',
                'betterportal_block_better_media_albums_desc',
                'betterportal_no_better_media',
                'betterportal_no_better_media_albums',
                'betterportal_block_better_media_browser',
                'betterportal_block_better_media_browser_desc',
                'betterportal_better_media_browser_album',
                'betterportal_better_media_browser_needs_selection',
                'betterportal_better_media_browser_preview_hint',
            );

            foreach ($gallery_rebrand_keys as $key)
                if (array_key_exists($key, $default_txt))
                    $txt[$key] = $default_txt[$key];
        }

        $loaded = true;
    }
}

/**
 * Always load Better Portal's current public template from the SMF default
 * theme. Theme Lab-generated themes may contain snapshot copies from an older
 * Better Portal release; those stale templates can silently ignore newer shell
 * context such as Dynamic Forum Board native views.
 */
function BetterPortal_LoadPublicTemplate()
{
    global $settings;

    static $loaded = false;
    if ($loaded)
        return;

    $template = (!empty($settings['default_theme_dir']) ? $settings['default_theme_dir'] : $settings['theme_dir']) . '/BetterPortal.template.php';
    if (file_exists($template))
        require_once $template;
    else
        loadTemplate('BetterPortal');

    $loaded = true;
}

function BetterPortal_Actions(&$actionArray)
{
    $actionArray['portal'] = array('BetterPortal.php', 'BetterPortal_Main');
    $actionArray['portalblock'] = array('BetterPortal.php', 'BetterPortal_BlockRouter');
    $actionArray['forum'] = array('BetterPortal.php', 'BetterPortal_Forum');
}

/**
 * Keep native SMF poll voting inside the Portal page that owns the poll block.
 * The vote itself still runs through SMF's action=vote controller; this hook
 * changes only the successful redirect destination.
 */
function BetterPortal_Redirect(&$setLocation, &$refresh, &$permanent)
{
    global $smcFunc;

    if (empty($_REQUEST['action']) || $_REQUEST['action'] !== 'vote' || empty($_REQUEST['bp_page']) || empty($_REQUEST['bp_block']))
        return;

    $id_page = (int) $_REQUEST['bp_page'];
    $id_block = (int) $_REQUEST['bp_block'];
    if ($id_page <= 0 || $id_block <= 0)
        return;

    $request = $smcFunc['db_query']('', '
        SELECT p.*, b.block_type, b.is_enabled AS block_enabled, b.allowed_groups AS block_allowed_groups
        FROM {db_prefix}betterportal_pages AS p
            INNER JOIN {db_prefix}betterportal_blocks AS b ON (b.id_page = p.id_page)
        WHERE p.id_page = {int:id_page}
            AND b.id_block = {int:id_block}
        LIMIT 1', array('id_page' => $id_page, 'id_block' => $id_block));
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (empty($row) || empty($row['is_enabled']) || empty($row['block_enabled']) || $row['block_type'] !== 'dynamic_poll')
        return;
    if (!BetterPortal_CanView($row['allowed_groups']) || !BetterPortal_CanView($row['block_allowed_groups']))
        return;

    $setLocation = BetterPortal_PublicPageUrl($row) . '#betterportal-block-' . $id_block;
    $refresh = false;
    $permanent = false;
}

function BetterPortal_DefaultAction()
{
    return BetterPortal_Main();
}


/**
 * Deterministic native-view router for Dynamic Forum Board blocks.
 *
 * dev22 attempted to rely on SMF's implicit board/topic routes and then catch
 * them at a late integration hook. That preserves context in the URL, but on
 * some setups the native request completes before the block shell takes over.
 * This explicit action keeps SMF's normal board/topic/post controllers while
 * giving Better Portal a guaranteed entry point for an in-block native view.
 */
function BetterPortal_BlockRouter()
{
    global $sourcedir, $board, $topic;

    $view = isset($_REQUEST['bp_view']) ? (string) $_REQUEST['bp_view'] : '';
    if (!in_array($view, array('board', 'topic', 'post'), true))
        fatal_lang_error('betterportal_page_not_found', false);

    $shell = BetterPortal_GetBlockShellContext(false);
    if (empty($shell))
        fatal_lang_error('betterportal_page_not_found', false);

    // The selected Dynamic Forum Board owns exactly one board. Never let a
    // crafted block URL use this router to display another board in its shell.
    if (!empty($board) && (int) $board !== (int) $shell['id_board'])
        fatal_lang_error('betterportal_page_not_found', false);

    BetterPortal_RememberBlockShell($shell);

    if ($view === 'board')
    {
        if (empty($board))
            fatal_lang_error('betterportal_page_not_found', false);
        require_once($sourcedir . '/MessageIndex.php');
        return MessageIndex();
    }

    if ($view === 'topic')
    {
        if (empty($topic))
            fatal_lang_error('betterportal_page_not_found', false);
        require_once($sourcedir . '/Display.php');
        return Display();
    }

    // Post() remains SMF's real composer. It performs all native permission,
    // editor, attachment, draft and verification setup; Better Portal only
    // supplies the surrounding block shell via integrate_post_end.
    require_once($sourcedir . '/Post.php');
    return Post();
}

function BetterPortal_Forum()
{
    global $sourcedir, $modSettings, $scripturl;

    // When Better Portal owns the site home and an enabled, viewable portal
    // page is the site's Forum Mode destination, keep legacy/action=forum
    // links pointed at that portal page too. This gives us one canonical forum
    // destination and lays the groundwork for extending the portal shell around
    // board and topic views in later passes.
    if (!empty($modSettings['betterportal_mode']) && $modSettings['betterportal_mode'] === 'home')
    {
        $forum_page = BetterPortal_FindEmbeddedForumPage();
        if (!empty($forum_page))
        {
            $target = !empty($forum_page['is_home'])
                ? $scripturl
                : $scripturl . '?action=portal;page=' . urlencode($forum_page['slug']);

            redirectexit($target);
        }
    }

    require_once($sourcedir . '/BoardIndex.php');
    return BoardIndex();
}

function BetterPortal_MenuButtons(&$buttons)
{
    global $modSettings, $scripturl, $txt;

    BetterPortal_LoadLanguage();
    $mode = isset($modSettings['betterportal_mode']) ? $modSettings['betterportal_mode'] : 'separate';
    $show_button = !isset($modSettings['betterportal_menu_button']) || !empty($modSettings['betterportal_menu_button']);

    if ($mode === 'disabled')
        return;

    $portal_pages = BetterPortal_LoadPublicPages();
    $page_sub_buttons = array();
    $home_nav_label = '';
    foreach ($portal_pages as $page)
    {
        if (!empty($page['is_home']))
        {
            $home_nav_label = trim((string) ($page['menu_label'] ?? ''));
            continue;
        }

        if (empty($page['show_in_nav']))
            continue;

        $label = trim((string) ($page['menu_label'] ?? ''));
        if ($label === '')
            $label = $page['title'];

        $page_sub_buttons['betterportal_page_' . (int) $page['id_page']] = array(
            'title' => $label,
            'href' => $scripturl . '?action=portal;page=' . urlencode($page['slug']),
            'show' => true,
        );
    }

    if ($mode === 'home')
    {
        if (isset($buttons['home']))
        {
            $buttons['home']['href'] = $scripturl;
            if ($home_nav_label !== '')
                $buttons['home']['title'] = $home_nav_label;
            $buttons['home']['sub_buttons'] = $page_sub_buttons;
        }
        // Forum Mode is a site-level destination, not merely a property of the
        // current portal request. If an enabled, viewable portal page hosts the
        // native forum, suppress the redundant Forum button everywhere: Portal,
        // Admin, Members, Media, Moderation, boards, topics, profiles, etc.
        // If that destination disappears or becomes unavailable, the normal Forum
        // button automatically returns.
        $forum_page = BetterPortal_FindEmbeddedForumPage($portal_pages);
        $forum_has_menu_path = !empty($forum_page) && (!empty($forum_page['is_home']) || !empty($forum_page['show_in_nav']));
        if (!$forum_has_menu_path)
        {
            BetterPortal_InsertButtonAfter($buttons, 'home', 'forum', array(
                'title' => $txt['betterportal_forum_button'],
                'href' => $scripturl . '?action=forum',
                'show' => true,
                'sub_buttons' => array(),
            ));
        }
    }
    elseif ($show_button)
    {
        BetterPortal_InsertButtonAfter($buttons, 'home', 'portal', array(
            'title' => $txt['betterportal_portal_button'],
            'href' => $scripturl . '?action=portal',
            'show' => true,
            'icon' => 'better_portal_dev32l.png',
            'sub_buttons' => $page_sub_buttons,
        ));
    }
}

function BetterPortal_InsertButtonAfter(&$buttons, $after_key, $new_key, $button)
{
    $out = array();
    $inserted = false;
    foreach ($buttons as $key => $value)
    {
        $out[$key] = $value;
        if ($key === $after_key)
        {
            $out[$new_key] = $button;
            $inserted = true;
        }
    }
    if (!$inserted)
        $out[$new_key] = $button;
    $buttons = $out;
}

/**
 * Resolve a request back to an Interactive Forum Board block.
 *
 * Explicit bp_page/bp_block values are used for normal navigation from the
 * block. The short-lived session fallback is used only by the native SMF post
 * composer/post2 flow, whose form actions do not preserve arbitrary query
 * parameters. Every lookup is revalidated against the live page, block,
 * audience and selected board before the shell is allowed to render.
 */
function BetterPortal_GetBlockShellContext($allow_session = false)
{
    global $board, $topic, $smcFunc, $modSettings;

    $id_page = isset($_REQUEST['bp_page']) ? (int) $_REQUEST['bp_page'] : 0;
    $id_block = isset($_REQUEST['bp_block']) ? (int) $_REQUEST['bp_block'] : 0;

    if (($id_page <= 0 || $id_block <= 0) && $allow_session && !empty($_SESSION['betterportal_block_shell']) && is_array($_SESSION['betterportal_block_shell']))
    {
        $saved = $_SESSION['betterportal_block_shell'];
        if (!empty($saved['time']) && (int) $saved['time'] >= time() - 3600)
        {
            $id_page = (int) ($saved['id_page'] ?? 0);
            $id_block = (int) ($saved['id_block'] ?? 0);
        }
    }

    if ($id_page <= 0 || $id_block <= 0)
        return array();

    $page = BetterPortal_LoadPageRecord($id_page);
    if (empty($page) || empty($page['is_enabled']) || !BetterPortal_CanView($page['allowed_groups']))
        return array();

    $block = BetterPortal_LoadBlock($id_page, $id_block);
    if (empty($block) || empty($block['is_enabled']) || !in_array($block['block_type'], array('dynamic_forum_board', 'dynamic_topic'), true) || !BetterPortal_CanView($block['allowed_groups']))
        return array();

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $id_board = 0;
    $configured_topic = 0;

    if ($block['block_type'] === 'dynamic_forum_board')
        $id_board = (int) ($settings['board_id'] ?? 0);
    else
    {
        $configured_topic = (int) ($settings['topic_id'] ?? 0);
        if ($configured_topic <= 0)
            return array();

        $approval_where = !empty($modSettings['postmod_active']) ? ' AND t.approved = {int:approved}' : '';
        $request = $smcFunc['db_query']('', '
            SELECT t.id_board
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE t.id_topic = {int:id_topic}
                AND {query_see_board}' . $approval_where . '
            LIMIT 1', array('id_topic' => $configured_topic, 'approved' => 1));
        $row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        if (empty($row))
            return array();
        $id_board = (int) $row['id_board'];
    }

    if ($id_board <= 0)
        return array();

    // Once a native block is active it may move between its selected topic and
    // that topic's board using SMF's own breadcrumbs/navigation, but it may
    // never escape into a different board under the same Portal block shell.
    if (!empty($board) && (int) $board !== $id_board)
        return array();

    return array(
        'page' => $page,
        'block' => $block,
        'block_type' => $block['block_type'],
        'id_page' => $id_page,
        'id_block' => $id_block,
        'id_board' => $id_board,
        'id_topic' => $configured_topic,
    );
}

function BetterPortal_RememberBlockShell($shell)
{
    if (empty($shell['id_page']) || empty($shell['id_block']) || empty($shell['id_board']))
        return;

    $_SESSION['betterportal_block_shell'] = array(
        'id_page' => (int) $shell['id_page'],
        'id_block' => (int) $shell['id_block'],
        'id_board' => (int) $shell['id_board'],
        'time' => time(),
    );
}

/**
 * Build the canonical public URL for a Portal page.
 * Home uses the forum root when Better Portal owns Home; all other pages use
 * their stable Portal slug.
 */
function BetterPortal_PublicPageUrl($page)
{
    global $modSettings, $scripturl;

    if (!empty($page['is_home']) && !empty($modSettings['betterportal_mode']) && $modSettings['betterportal_mode'] === 'home')
        return $scripturl;

    return $scripturl . '?action=portal;page=' . urlencode((string) ($page['slug'] ?? ''));
}

/**
 * Render a native SMF view in the exact Dynamic Forum Board block that launched
 * it. This is the core of dev22's true "forum inside a block" behavior.
 */
function BetterPortal_PrepareEmbeddedBlockShell($mode, $allow_session = false)
{
    global $context, $txt;

    if (isset($_REQUEST['xml']))
        return false;

    $shell = BetterPortal_GetBlockShellContext($allow_session);
    if (empty($shell))
        return false;

    $rows = BetterPortal_BuildPageRows((int) $shell['id_page']);
    if (empty($rows))
        return false;

    $shell_page = $shell['page'];
    $shell_page['show_title'] = 0;

    $context['betterportal_page'] = $shell_page;
    $context['betterportal_pages'] = BetterPortal_LoadPublicPages();
    $context['betterportal_rows'] = $rows;
    $context['betterportal_embedded_block_id'] = (int) $shell['id_block'];
    $context['betterportal_embedded_block_shell'] = array('id_page'=>(int)$shell['id_page'],'id_block'=>(int)$shell['id_block'],'id_board'=>(int)$shell['id_board']);
    $context['betterportal_embedded_native_ready'] = true;
    $context['betterportal_shell_mode'] = 'block-' . preg_replace('~[^a-z0-9_-]~i', '', (string) $mode);

    // The live board/topic-list is already the Portal page's working surface,
    // so a Back button there is redundant. Only deeper native views need an
    // obvious return path: an opened topic or the native topic/reply composer.
    if (in_array((string) $mode, array('topic', 'composer'), true))
    {
        BetterPortal_LoadLanguage();
        $context['betterportal_block_return'] = array(
            'href' => BetterPortal_PublicPageUrl($shell_page),
            'label' => sprintf($txt['betterportal_block_return_to_page'], (string) $shell_page['title']),
        );
    }
    else
        unset($context['betterportal_block_return']);

    $context['sub_template'] = 'betterportal_page';

    BetterPortal_RememberBlockShell($shell);

    BetterPortal_LoadPublicTemplate();
    loadCSSFile('better_portal.css', array('default_theme' => true, 'minimize' => true, 'order' => 1500), 'better_portal');
    return true;
}

/**
 * Extend the active Forum Mode page around native board views.
 *
 * MessageIndex has already prepared all normal SMF board data by the time its
 * button hook fires. We only swap the final sub-template for Better Portal's
 * existing page shell, allowing the native MessageIndex template_main() to
 * render in the protected forum column while the page's normal Portal blocks
 * continue to render in the surrounding columns.
 */
function BetterPortal_MessageIndexButtons(&$buttons)
{
    global $context, $modSettings;

    // A Dynamic Forum Board can own this native board view directly inside
    // its original block, regardless of whether the page itself uses Forum Mode.
    if (BetterPortal_PrepareEmbeddedBlockShell('board'))
        return;

    if (empty($modSettings['betterportal_mode']) || $modSettings['betterportal_mode'] !== 'home')
        return;

    $forum_page = BetterPortal_FindEmbeddedForumPage();
    if (empty($forum_page) || empty($forum_page['id_page']))
        return;

    $rows = BetterPortal_BuildPageRows((int) $forum_page['id_page']);
    $has_forum = false;
    foreach ($rows as $row)
    {
        if (!empty($row['forum_mode']))
        {
            $has_forum = true;
            break;
        }
    }

    if (!$has_forum)
        return;

    // Preserve the native board title/linktree/context. The Portal page is only
    // being used as the surrounding shell, so its optional page heading is not
    // repeated above every board.
    $shell_page = $forum_page;
    $shell_page['show_title'] = 0;

    $context['betterportal_page'] = $shell_page;
    $context['betterportal_pages'] = BetterPortal_LoadPublicPages();
    $context['betterportal_rows'] = $rows;
    $context['betterportal_embedded_forum_ready'] = true;
    $context['betterportal_shell_mode'] = 'board';
    $context['sub_template'] = 'betterportal_page';

    BetterPortal_LoadPublicTemplate();
    loadCSSFile('better_portal.css', array('default_theme' => true, 'minimize' => true, 'order' => 1500), 'better_portal');
}

/**
 * Extend the active Forum Mode page around native topic views.
 *
 * SMF Display.php has already prepared the topic, messages, permissions,
 * pagination, polls, normal/moderation buttons, and any other integrations by
 * the time integrate_display_buttons runs. Better Portal only replaces the
 * outer sub-template so Display.template.php's native template_main() renders
 * in the protected forum column while the configured Portal rails remain
 * around it.
 */
function BetterPortal_DisplayButtons(&$buttons)
{
    global $context, $modSettings;

    // Topic links launched from an Interactive Forum Board remain inside the
    // exact block that launched them.
    if (BetterPortal_PrepareEmbeddedBlockShell('topic'))
        return;

    if (empty($modSettings['betterportal_mode']) || $modSettings['betterportal_mode'] !== 'home')
        return;

    $forum_page = BetterPortal_FindEmbeddedForumPage();
    if (empty($forum_page) || empty($forum_page['id_page']))
        return;

    $rows = BetterPortal_BuildPageRows((int) $forum_page['id_page']);
    $has_forum = false;
    foreach ($rows as $row)
    {
        if (!empty($row['forum_mode']))
        {
            $has_forum = true;
            break;
        }
    }

    if (!$has_forum)
        return;

    // The native topic subject, breadcrumb, pagination and controls remain
    // authoritative. Do not repeat the Portal page heading above the topic.
    $shell_page = $forum_page;
    $shell_page['show_title'] = 0;

    $context['betterportal_page'] = $shell_page;
    $context['betterportal_pages'] = BetterPortal_LoadPublicPages();
    $context['betterportal_rows'] = $rows;
    $context['betterportal_embedded_forum_ready'] = true;
    $context['betterportal_shell_mode'] = 'topic';
    $context['sub_template'] = 'betterportal_page';

    BetterPortal_LoadPublicTemplate();
    loadCSSFile('better_portal.css', array('default_theme' => true, 'minimize' => true, 'order' => 1500), 'better_portal');
}

/**
 * Keep SMF's native post composer inside an Interactive Forum Board block.
 * Post.php has already done all permission, editor, attachment, draft and
 * verification setup before integrate_post_end fires; Portal only changes the
 * outer presentation shell.
 */
function BetterPortal_PostEnd()
{
    BetterPortal_PrepareEmbeddedBlockShell('composer', true);
}

/**
 * After a successful native post/reply, return to the new/updated topic inside
 * the same Dynamic Forum Board block rather than letting Post2 drop the user
 * back onto an unshelled board page.
 */
function BetterPortal_Post2End()
{
    global $topic, $board;

    if (empty($_SESSION['betterportal_block_shell']) || !is_array($_SESSION['betterportal_block_shell']))
        return;

    // Let SMF's special announce/move redirects remain authoritative.
    if (!empty($_POST['announce_topic']) || !empty($_POST['move']))
        return;

    $saved = $_SESSION['betterportal_block_shell'];
    if (empty($saved['time']) || (int) $saved['time'] < time() - 3600 || empty($saved['id_page']) || empty($saved['id_block']) || empty($saved['id_board']))
    {
        unset($_SESSION['betterportal_block_shell']);
        return;
    }

    if (empty($topic) || empty($board) || (int) $board !== (int) $saved['id_board'])
        return;

    $shell = BetterPortal_GetBlockShellContext(true);
    if (empty($shell))
    {
        unset($_SESSION['betterportal_block_shell']);
        return;
    }

    $id_page = (int) $saved['id_page'];
    $id_block = (int) $saved['id_block'];
    unset($_SESSION['betterportal_block_shell']);

    redirectexit('action=portalblock;bp_view=topic;topic=' . (int) $topic . '.new;bp_page=' . $id_page . ';bp_block=' . $id_block . '#new');
}


/**
 * Keep native MessageIndex/Display navigation inside the Dynamic Forum Board.
 * Only URLs that represent the selected board, a topic, or SMF's post composer
 * are rewritten. Profiles, moderation, search, attachments, etc. remain normal
 * SMF destinations.
 */
function BetterPortal_RewriteNativeBlockHtml($html, $shell)
{
    global $scripturl;

    if (!is_string($html) || $html === '' || empty($shell['id_page']) || empty($shell['id_block']) || empty($shell['id_board']))
        return $html;

    $suffix = ';bp_page=' . (int) $shell['id_page'] . ';bp_block=' . (int) $shell['id_block'];
    $board_id = (int) $shell['id_board'];

    return preg_replace_callback('~href=(["\\\'])(.*?)\\1~i', function ($m) use ($scripturl, $suffix, $board_id)
    {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        $prefix = $scripturl . '?';
        if (strpos($url, $prefix) !== 0)
            return $m[0];

        $query = substr($url, strlen($prefix));
        $new = '';

        if (preg_match('~^topic=(\\d+)([^#]*)(#.*)?$~', $query, $parts))
            $new = $scripturl . '?action=portalblock;bp_view=topic;topic=' . $parts[1] . $parts[2] . $suffix . (!empty($parts[3]) ? $parts[3] : '');
        elseif (preg_match('~^board=' . $board_id . '([.;][^#]*)?(#.*)?$~', $query, $parts))
            $new = $scripturl . '?action=portalblock;bp_view=board;board=' . $board_id . (!empty($parts[1]) ? $parts[1] : '.0') . $suffix . (!empty($parts[2]) ? $parts[2] : '');
        elseif (strpos($query, 'action=post;') === 0)
            $new = $scripturl . '?action=portalblock;bp_view=post;' . substr($query, strlen('action=post;')) . $suffix;

        if ($new === '')
            return $m[0];

        return 'href=' . $m[1] . htmlspecialchars($new, ENT_QUOTES, 'UTF-8') . $m[1];
    }, $html);
}

function BetterPortal_AdminAreas(&$admin_areas)
{
    global $txt;
    BetterPortal_LoadLanguage();

    $admin_areas['config']['areas']['betterportal'] = array(
        'label' => $txt['betterportal_admin_title'],
        'file' => 'BetterPortal.php',
        'function' => 'BetterPortal_Admin',
        'icon' => 'better_portal_dev32g.png',
        'permission' => array('admin_forum'),
        'subsections' => array(
            'overview' => array($txt['betterportal_admin_overview']),
            'pages' => array($txt['betterportal_admin_pages']),
            'builder' => array($txt['betterportal_admin_builder']),
            'settings' => array($txt['betterportal_admin_settings']),
        ),
    );
}

function BetterPortal_Admin()
{
    global $context, $txt, $settings;

    isAllowedTo('admin_forum');
    BetterPortal_LoadLanguage();

    // Always use Better Portal's current admin template from the SMF default
    // theme. Theme Lab themes may contain older snapshot copies of this file,
    // which can otherwise hide newly-added controls after a Portal upgrade.
    $admin_template = (!empty($settings['default_theme_dir']) ? $settings['default_theme_dir'] : $settings['theme_dir']) . '/BetterPortalAdmin.template.php';
    if (file_exists($admin_template))
        require_once $admin_template;
    else
        loadTemplate('BetterPortalAdmin');
    loadCSSFile('better_portal.css', array('default_theme' => true, 'minimize' => true, 'order' => 1500), 'better_portal');

    $allowed = array('overview', 'pages', 'builder', 'settings');
    $sa = isset($_REQUEST['sa']) && in_array($_REQUEST['sa'], $allowed, true) ? $_REQUEST['sa'] : 'overview';
    $context['page_title'] = $txt['betterportal_admin_title'];

    if (!empty($context['admin_menu_name']))
    {
        $context[$context['admin_menu_name']]['tab_data'] = array(
            'title' => $txt['betterportal_admin_title'],
            'description' => $txt['betterportal_admin_desc'],
            'tabs' => array(
                'overview' => array('description' => $txt['betterportal_admin_overview_desc']),
                'pages' => array('description' => $txt['betterportal_admin_pages_desc']),
                'builder' => array('description' => $txt['betterportal_admin_builder_desc']),
                'settings' => array('description' => $txt['betterportal_admin_settings_desc']),
            ),
        );
    }

    if ($sa === 'settings') return BetterPortal_AdminSettings();
    if ($sa === 'builder') return BetterPortal_AdminBuilder();
    if ($sa === 'pages') return BetterPortal_AdminPages();
    return BetterPortal_AdminOverview();
}

function BetterPortal_AdminOverview()
{
    global $context, $smcFunc, $modSettings;

    foreach (array('pages', 'rows', 'blocks') as $kind)
    {
        $request = $smcFunc['db_query']('', 'SELECT COUNT(*) AS total FROM {db_prefix}betterportal_' . $kind, array());
        $row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        $context['betterportal_counts'][$kind] = (int) $row['total'];
    }

    $context['betterportal_mode'] = isset($modSettings['betterportal_mode']) ? $modSettings['betterportal_mode'] : 'separate';
    $context['sub_template'] = 'betterportal_admin_overview';
}

function BetterPortal_AdminPages()
{
    global $context, $smcFunc, $user_info, $scripturl;

    if (isset($_POST['create_page']))
    {
        checkSession('post');
        validateToken('betterportal-pages');
        $title = trim((string) ($_POST['new_title'] ?? ''));
        $slug = BetterPortal_Slug((string) ($_POST['new_slug'] ?? ''));
        if ($title === '') $title = 'New Page';
        $title = substr($title, 0, 160);
        if ($slug === '') $slug = BetterPortal_UniqueSlug(BetterPortal_Slug($title));
        else $slug = BetterPortal_UniqueSlug($slug);
        $layout = (string) ($_POST['new_layout'] ?? '1');
        if (!in_array($layout, array('1', '1-1', '2-1', '1-2', '1-1-1'), true)) $layout = '1';
        $now = time();
        $member = !empty($user_info['id']) ? (int) $user_info['id'] : 0;

        $id_page = $smcFunc['db_insert']('insert', '{db_prefix}betterportal_pages', array(
            'slug' => 'string-80', 'title' => 'string-160', 'page_type' => 'string-20',
            'is_home' => 'int', 'is_enabled' => 'int', 'show_title' => 'int', 'allowed_groups' => 'string',
            'layout_version' => 'int', 'created_at' => 'int', 'updated_at' => 'int', 'created_by' => 'int', 'updated_by' => 'int',
        ), array($slug, $title, 'portal', 0, 1, 0, '["everyone"]', 1, $now, $now, $member, $member), array('id_page'), 1);

        if (!empty($id_page))
        {
            BetterPortal_CreateRow((int) $id_page, $layout);
            BetterPortal_EnsurePageNavigation((int) $id_page, true);
            redirectexit('action=admin;area=betterportal;sa=builder;id=' . (int) $id_page . ';created=1');
        }
    }

    if (isset($_POST['set_home']))
    {
        checkSession('post');
        validateToken('betterportal-pages');
        $id_page = (int) ($_POST['id_page'] ?? 0);
        if ($id_page > 0 && BetterPortal_LoadPageRecord($id_page))
        {
            $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_pages SET is_home = {int:no}', array('no' => 0));
            $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_pages SET is_home = {int:yes} WHERE id_page = {int:id_page}', array('yes' => 1, 'id_page' => $id_page));
        }
    }

    if (isset($_POST['delete_page']))
    {
        checkSession('post');
        validateToken('betterportal-pages');
        $id_page = (int) ($_POST['id_page'] ?? 0);
        $page = BetterPortal_LoadPageRecord($id_page);
        if (!empty($page) && empty($page['is_home']))
        {
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}betterportal_blocks WHERE id_page = {int:id_page}', array('id_page' => $id_page));
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}betterportal_rows WHERE id_page = {int:id_page}', array('id_page' => $id_page));
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}betterportal_page_nav WHERE id_page = {int:id_page}', array('id_page' => $id_page));
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}betterportal_pages WHERE id_page = {int:id_page}', array('id_page' => $id_page));
        }
    }

    if (isset($_POST['reorder_pages']))
    {
        checkSession('post');
        validateToken('betterportal-pages');
        $page_order = json_decode((string) ($_POST['page_order'] ?? ''), true);
        $saved = is_array($page_order) && BetterPortal_SaveAdminPageOrder($page_order);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('success' => $saved));
        exit;
    }

    if (isset($_POST['move_page']))
    {
        checkSession('post');
        validateToken('betterportal-pages');
        $id_page = (int) ($_POST['id_page'] ?? 0);
        $direction = (string) ($_POST['move_page'] ?? '');
        BetterPortal_MoveAdminPage($id_page, $direction);
    }

    $context['betterportal_pages'] = array();
    $request = $smcFunc['db_query']('', '
        SELECT p.id_page, p.slug, p.title, p.is_home, p.is_enabled, p.show_title, p.allowed_groups, p.updated_at,
            COALESCE(n.show_in_nav, {int:nav_visible}) AS show_in_nav,
            COALESCE(n.menu_label, {string:empty_label}) AS menu_label,
            COALESCE(n.nav_order, p.id_page * {int:nav_step}) AS nav_order,
            COALESCE(r.layout, {string:default_layout}) AS layout, COUNT(b.id_block) AS block_count
        FROM {db_prefix}betterportal_pages AS p
            LEFT JOIN {db_prefix}betterportal_page_nav AS n ON (n.id_page = p.id_page)
            LEFT JOIN {db_prefix}betterportal_rows AS r ON (r.id_page = p.id_page AND r.row_order = {int:first_row})
            LEFT JOIN {db_prefix}betterportal_blocks AS b ON (b.id_page = p.id_page)
        GROUP BY p.id_page, p.slug, p.title, p.is_home, p.is_enabled, p.show_title, p.allowed_groups, p.updated_at,
            n.show_in_nav, n.menu_label, n.nav_order, r.layout
        ORDER BY p.is_home DESC, COALESCE(n.nav_order, p.id_page * {int:nav_step}) ASC, p.id_page ASC', array('default_layout' => '1', 'first_row' => 1, 'nav_visible' => 1, 'empty_label' => '', 'nav_step' => 10));
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $context['betterportal_pages'][] = $row;
    $smcFunc['db_free_result']($request);

    createToken('betterportal-pages');
    $context['sub_template'] = 'betterportal_admin_pages';
}

function BetterPortal_AdminSettings()
{
    global $context, $modSettings;

    if (isset($_POST['save_settings']))
    {
        checkSession('post');
        validateToken('betterportal-settings');
        $mode = isset($_POST['betterportal_mode']) ? (string) $_POST['betterportal_mode'] : 'separate';
        if (!in_array($mode, array('disabled', 'separate', 'home'), true)) $mode = 'separate';
        $menu = !empty($_POST['betterportal_menu_button']) ? 1 : 0;
        updateSettings(array('betterportal_mode' => $mode, 'betterportal_menu_button' => $menu, 'betterportal_version' => '0.1.0'));
        $modSettings['betterportal_mode'] = $mode;
        $modSettings['betterportal_menu_button'] = $menu;
        BetterPortal_SyncDefaultActionHook($mode);
        $context['betterportal_saved'] = true;
    }

    createToken('betterportal-settings');
    $context['betterportal_mode'] = isset($modSettings['betterportal_mode']) ? $modSettings['betterportal_mode'] : 'separate';
    $context['betterportal_menu_button'] = !isset($modSettings['betterportal_menu_button']) || !empty($modSettings['betterportal_menu_button']);
    $context['sub_template'] = 'betterportal_admin_settings';
}

function BetterPortal_SyncDefaultActionHook($mode)
{
    $file = '$sourcedir/BetterPortal.php';
    remove_integration_function('integrate_default_action', 'BetterPortal_DefaultAction', true, $file);
    remove_integration_function('integrate_default_action', 'BetterPortal_DefaultAction', true);
    if ($mode === 'home') add_integration_function('integrate_default_action', 'BetterPortal_DefaultAction', true, $file);
}

function BetterPortal_AdminBuilder()
{
    global $context, $smcFunc, $user_info;

    $id_page = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    if (empty($id_page))
    {
        $request = $smcFunc['db_query']('', 'SELECT id_page FROM {db_prefix}betterportal_pages ORDER BY is_home DESC, id_page ASC LIMIT 1', array());
        list ($id_page) = $smcFunc['db_fetch_row']($request);
        $smcFunc['db_free_result']($request);
        $id_page = (int) $id_page;
    }

    $page = BetterPortal_LoadPageRecord($id_page);
    if (empty($page))
        fatal_lang_error('betterportal_page_not_found', false);

    BetterPortal_EnsurePageLayoutRow($id_page);

    if (isset($_POST['save_page']))
    {
        checkSession('post');
        validateToken('betterportal-builder');

        $title = trim((string) ($_POST['page_title'] ?? ''));
        if ($title === '')
            $title = 'Untitled Page';
        $slug = BetterPortal_Slug((string) ($_POST['page_slug'] ?? ''));
        if ($slug === '')
            $slug = BetterPortal_Slug($title);
        $slug = BetterPortal_UniqueSlug($slug, $id_page);

        $now = time();
        $audience = BetterPortal_AudienceRulesFromPost($_POST);
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}betterportal_pages
            SET title={string:title}, slug={string:slug}, show_title={int:show_title}, is_enabled={int:is_enabled},
                allowed_groups={string:allowed_groups}, updated_at={int:updated_at}, updated_by={int:updated_by}
            WHERE id_page={int:id_page}', array(
                'title' => substr($title, 0, 160), 'slug' => $slug,
                'show_title' => !empty($_POST['page_show_title']) ? 1 : 0,
                'is_enabled' => !empty($_POST['page_enabled']) ? 1 : 0,
                'allowed_groups' => json_encode($audience), 'updated_at' => $now,
                'updated_by' => !empty($user_info['id']) ? (int) $user_info['id'] : 0,
                'id_page' => $id_page,
            ));

        $nav_label = substr(trim((string) ($_POST['page_menu_label'] ?? '')), 0, 160);
        $nav_order = max(1, min(65535, (int) ($_POST['page_nav_order'] ?? 1)));
        BetterPortal_SavePageNavigation($id_page, !empty($_POST['page_show_in_nav']), $nav_label, $nav_order);
        $context['betterportal_saved'] = true;
    }

    if (isset($_POST['add_row']))
    {
        checkSession('post');
        validateToken('betterportal-builder');
        $layout = (string) ($_POST['new_row_layout'] ?? '1');
        BetterPortal_CreateRow($id_page, $layout);
        $context['betterportal_saved'] = true;
    }

    if (isset($_POST['row_action']))
    {
        checkSession('post');
        validateToken('betterportal-builder');
        BetterPortal_HandleRowAction($id_page, (int) ($_POST['id_row'] ?? 0), (string) $_POST['row_action'], $_POST);
        $context['betterportal_saved'] = true;
    }

    if (isset($_POST['add_block']))
    {
        checkSession('post');
        validateToken('betterportal-builder');
        $id_row = (int) ($_POST['id_row'] ?? 0);
        $row = BetterPortal_LoadRow($id_page, $id_row);
        if (!empty($row))
        {
            $columns = BetterPortal_LayoutColumns($row['layout']);
            $column = (string) ($_POST['column_key'] ?? 'col1');
            if (!in_array($column, $columns, true))
                $column = $columns[0];

            $registry = BetterPortal_BlockRegistry();
            $block_type = (string) ($_POST['block_type'] ?? 'custom_text');
            if (!isset($registry[$block_type]))
                $block_type = 'custom_text';

            $forum_mode = BetterPortal_IsForumModeRow($row);
            $forum_main = BetterPortal_ForumMainColumn($row['layout']);
            if (!$forum_mode || $column !== $forum_main)
                BetterPortal_CreateBlock($id_page, $id_row, $column, $block_type, $registry[$block_type]);
        }
    }

    if (isset($_POST['reorder_blocks']))
    {
        checkSession('post');
        validateToken('betterportal-builder');
        $block_layout = json_decode((string) ($_POST['block_layout'] ?? ''), true);
        $saved = is_array($block_layout) && BetterPortal_SaveBlockLayout($id_page, $block_layout);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('success' => $saved));
        exit;
    }

    if (isset($_POST['reorder_rows']))
    {
        checkSession('post');
        validateToken('betterportal-builder');
        $row_order = json_decode((string) ($_POST['row_order'] ?? ''), true);
        $saved = is_array($row_order) && BetterPortal_SaveRowOrder($id_page, $row_order);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('success' => $saved));
        exit;
    }

    if (isset($_POST['block_action']))
    {
        checkSession('post');
        validateToken('betterportal-builder');
        BetterPortal_HandleBlockAction($id_page, (int) ($_POST['id_block'] ?? 0), (string) $_POST['block_action'], $_POST);
    }

    $page = BetterPortal_LoadPageRecord($id_page);
    $rows = BetterPortal_LoadBuilderRows($id_page);
    $builder_pages = array();
    $request = $smcFunc['db_query']('', '
        SELECT id_page, title, slug, is_home, is_enabled
        FROM {db_prefix}betterportal_pages
        ORDER BY is_home DESC, id_page ASC', array());
    while ($builder_page = $smcFunc['db_fetch_assoc']($request))
        $builder_pages[] = $builder_page;
    $smcFunc['db_free_result']($request);

    $page = BetterPortal_LoadPageRecord($id_page);
    $has_forum_mode = false;
    foreach ($rows as $builder_row)
        if (!empty($builder_row['forum_mode'])) { $has_forum_mode = true; break; }

    $context['betterportal_builder'] = array(
        'page' => $page,
        'rows' => $rows,
        'has_forum_mode' => $has_forum_mode,
        'themelab_info' => BetterPortal_ThemeLabInfo(),
        'block_registry' => BetterPortal_BlockRegistry(),
        'pages' => $builder_pages,
        'audience_mode' => BetterPortal_AudienceMode($page['allowed_groups']),
        'audience_groups' => BetterPortal_AudienceGroupIds($page['allowed_groups']),
        'audience_visible_to_current_user' => BetterPortal_CanView($page['allowed_groups']),
        'membergroups' => BetterPortal_LoadAudienceMembergroups(),
    );
    $context['betterportal_page_created'] = !empty($_REQUEST['created']);
    createToken('betterportal-builder');
    $context['sub_template'] = 'betterportal_admin_builder';
}
function BetterPortal_CreateRow($id_page, $layout, $row_order = 0)
{
    global $smcFunc;
    $layouts = array('1', '1-1', '2-1', '1-2', '1-1-1');
    if (!in_array($layout, $layouts, true))
        $layout = '1';

    if ($row_order <= 0)
    {
        $request = $smcFunc['db_query']('', 'SELECT COALESCE(MAX(row_order),0)+1 FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page}', array('id_page'=>(int)$id_page));
        list ($row_order) = $smcFunc['db_fetch_row']($request);
        $smcFunc['db_free_result']($request);
    }

    return (int) $smcFunc['db_insert']('insert', '{db_prefix}betterportal_rows', array(
        'id_page' => 'int', 'row_order' => 'int', 'layout' => 'string-32', 'css_class' => 'string-120', 'is_enabled' => 'int'
    ), array((int)$id_page, max(1,(int)$row_order), $layout, '', 1), array('id_row'), 1);
}

function BetterPortal_EnsurePageLayoutRow($id_page)
{
    global $smcFunc;
    $request = $smcFunc['db_query']('', '
        SELECT id_row, id_page, row_order, layout, css_class, is_enabled
        FROM {db_prefix}betterportal_rows
        WHERE id_page={int:id_page}
        ORDER BY row_order, id_row
        LIMIT 1', array('id_page'=>(int)$id_page));
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    if (!empty($row))
        return $row;
    $id_row = BetterPortal_CreateRow((int)$id_page, '1');
    return BetterPortal_LoadRow((int)$id_page, $id_row);
}

function BetterPortal_SetPageLayout($id_page, $layout)
{
    $row = BetterPortal_EnsurePageLayoutRow($id_page);
    if (!empty($row))
        BetterPortal_SetRowLayout((int)$id_page, (int)$row['id_row'], $layout);
}

function BetterPortal_SetRowLayout($id_page, $id_row, $layout)
{
    global $smcFunc;
    $layouts = array('1', '1-1', '2-1', '1-2', '1-1-1');
    if (!in_array($layout, $layouts, true))
        $layout = '1';
    $row = BetterPortal_LoadRow((int)$id_page, (int)$id_row);
    if (empty($row))
        return false;

    $valid = BetterPortal_LayoutColumns($layout);
    $fallback = $valid[0];
    $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_rows SET layout={string:layout} WHERE id_page={int:id_page} AND id_row={int:id_row}', array(
        'layout'=>$layout,'id_page'=>(int)$id_page,'id_row'=>(int)$id_row));

    // Preserve blocks when columns disappear by appending them to the first valid column.
    $request = $smcFunc['db_query']('', 'SELECT COALESCE(MAX(block_order),0) FROM {db_prefix}betterportal_blocks WHERE id_row={int:id_row} AND column_key={string:column}', array('id_row'=>(int)$id_row,'column'=>$fallback));
    list($next) = $smcFunc['db_fetch_row']($request); $smcFunc['db_free_result']($request); $next=(int)$next;
    $request = $smcFunc['db_query']('', 'SELECT id_block FROM {db_prefix}betterportal_blocks WHERE id_row={int:id_row} AND column_key NOT IN ({array_string:valid}) ORDER BY block_order,id_block', array('id_row'=>(int)$id_row,'valid'=>$valid));
    while ($block=$smcFunc['db_fetch_assoc']($request))
    {
        $next++;
        $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_blocks SET column_key={string:column},block_order={int:ord} WHERE id_block={int:id}', array('column'=>$fallback,'ord'=>$next,'id'=>(int)$block['id_block']));
    }
    $smcFunc['db_free_result']($request);
    return true;
}
function BetterPortal_DefaultColumnWidthPreset($layout)
{
    if ($layout === '2-1')
        return 'wide-left';
    if ($layout === '1-2')
        return 'wide-right';
    if ($layout === '1-1-1')
        return 'wide-center';
    return 'equal';
}

function BetterPortal_ColumnWidthClass($preset)
{
    $valid = array('equal', 'wide-left', 'wide-center', 'wide-right');
    if (!in_array($preset, $valid, true))
        $preset = 'equal';
    return 'betterportal-width-' . $preset;
}

function BetterPortal_NormalizeColumnWidthPreset($layout, $preset)
{
    if ($preset === 'auto' || $preset === '')
        return BetterPortal_DefaultColumnWidthPreset($layout);

    $columns = count(BetterPortal_LayoutColumns($layout));
    if ($columns <= 1)
        return 'equal';

    if ($columns === 2)
    {
        if ($preset === 'wide-left' || $preset === 'wide-right' || $preset === 'equal')
            return $preset;
        return BetterPortal_DefaultColumnWidthPreset($layout);
    }

    if (in_array($preset, array('equal', 'wide-left', 'wide-center', 'wide-right'), true))
        return $preset;

    return BetterPortal_DefaultColumnWidthPreset($layout);
}

function BetterPortal_GetColumnWidthPreset($row)
{
    if (empty($row) || !is_array($row))
        return 'auto';

    $css = !empty($row['css_class']) ? (string) $row['css_class'] : '';
    if (preg_match('~(?:^|\\s)betterportal-width-(equal|wide-left|wide-center|wide-right)(?:\\s|$)~', $css, $match))
        return $match[1];

    return 'auto';
}

function BetterPortal_EffectiveColumnWidthPreset($row)
{
    $layout = !empty($row['layout']) ? (string) $row['layout'] : '1';
    $preset = BetterPortal_GetColumnWidthPreset($row);
    return BetterPortal_NormalizeColumnWidthPreset($layout, $preset);
}

function BetterPortal_SetColumnWidthPreset($id_page, $preset, $layout = '')
{
    $row = BetterPortal_EnsurePageLayoutRow($id_page);
    if (!empty($row))
        BetterPortal_SetRowColumnWidthPreset((int)$id_page, (int)$row['id_row'], $preset, $layout);
}

function BetterPortal_SetRowColumnWidthPreset($id_page, $id_row, $preset, $layout = '')
{
    global $smcFunc;
    $row = BetterPortal_LoadRow((int)$id_page, (int)$id_row);
    if (empty($row)) return false;
    if ($layout === '') $layout = (string)$row['layout'];
    $css = (string)($row['css_class'] ?? '');
    $css = preg_replace('~(?:^|\\s)betterportal-width-(?:equal|wide-left|wide-center|wide-right)(?=\\s|$)~', ' ', $css);
    $preset = (string)$preset;
    if ($preset !== 'auto' && $preset !== '')
    {
        $preset = BetterPortal_NormalizeColumnWidthPreset($layout, $preset);
        $css .= ' betterportal-width-' . $preset;
    }
    $css = trim(preg_replace('~\\s+~', ' ', $css));
    $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_rows SET css_class={string:css} WHERE id_page={int:id_page} AND id_row={int:id_row}', array('css'=>$css,'id_page'=>(int)$id_page,'id_row'=>(int)$id_row));
    return true;
}

function BetterPortal_IsForumModeRow($row)
{
    if (empty($row) || !is_array($row)) return false;
    return preg_match('~(?:^|\\s)betterportal-forum-mode(?:\\s|$)~', (string)($row['css_class'] ?? '')) === 1;
}

function BetterPortal_SetForumMode($id_page, $enabled)
{
    $row = BetterPortal_EnsurePageLayoutRow($id_page);
    if (!empty($row)) BetterPortal_SetRowForumMode((int)$id_page,(int)$row['id_row'],$enabled);
}

function BetterPortal_SetRowForumMode($id_page, $id_row, $enabled)
{
    global $smcFunc;
    $row = BetterPortal_LoadRow((int)$id_page,(int)$id_row);
    if (empty($row)) return false;

    // Only one row per page may own SMF's native Forum Mode surface.
    if ($enabled)
    {
        $request=$smcFunc['db_query']('', 'SELECT id_row,css_class FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page} AND id_row != {int:id_row}', array('id_page'=>(int)$id_page,'id_row'=>(int)$id_row));
        while($other=$smcFunc['db_fetch_assoc']($request))
        {
            $css=trim(preg_replace('~(?:^|\\s)betterportal-forum-mode(?=\\s|$)~',' ',(string)$other['css_class']));
            $css=trim(preg_replace('~\\s+~',' ',$css));
            $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_rows SET css_class={string:css} WHERE id_row={int:id_row}', array('css'=>$css,'id_row'=>(int)$other['id_row']));
        }
        $smcFunc['db_free_result']($request);
    }

    $css=(string)($row['css_class'] ?? '');
    $css=trim(preg_replace('~(?:^|\\s)betterportal-forum-mode(?=\\s|$)~',' ',$css));
    if($enabled) $css .= ' betterportal-forum-mode';
    $css=trim(preg_replace('~\\s+~',' ',$css));
    $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_rows SET css_class={string:css} WHERE id_page={int:id_page} AND id_row={int:id_row}', array('css'=>$css,'id_page'=>(int)$id_page,'id_row'=>(int)$id_row));
    return true;
}
function BetterPortal_ForumMainColumn($layout)
{
    if ($layout === '1-2' || $layout === '1-1-1')
        return 'col2';
    return 'col1';
}

function BetterPortal_FirstNonForumColumn($layout)
{
    $main = BetterPortal_ForumMainColumn($layout);
    foreach (BetterPortal_LayoutColumns($layout) as $column)
        if ($column !== $main)
            return $column;

    return $main;
}

function BetterPortal_PublicPagesHaveEmbeddedForum($pages)
{
    return !empty(BetterPortal_FindEmbeddedForumPage($pages));
}

function BetterPortal_FindEmbeddedForumPage($pages = null)
{
    if ($pages === null)
        $pages = BetterPortal_LoadPublicPages();

    if (empty($pages) || !is_array($pages))
        return array();

    foreach ($pages as $page)
        if (!empty($page['id_page']) && BetterPortal_PageHasEmbeddedForum((int) $page['id_page']))
            return $page;

    return array();
}

function BetterPortal_CurrentRequestIsPortalExperience()
{
    global $modSettings;

    $mode = isset($modSettings['betterportal_mode']) ? $modSettings['betterportal_mode'] : 'separate';
    if ($mode !== 'home')
        return false;

    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    return $action === '' || $action === 'portal';
}

function BetterPortal_ThemeLabInfo()
{
    global $settings;

    $info = array('detected' => false, 'width_mode' => '', 'max_width' => 0);
    $theme_dir = !empty($settings['theme_dir']) ? rtrim((string) $settings['theme_dir'], '/\\') : '';
    if ($theme_dir === '')
        return $info;

    $workspace = $theme_dir . '/themelab-workspace.json';
    if (is_file($workspace) && is_readable($workspace))
    {
        $raw = @file_get_contents($workspace);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($data))
        {
            $info['detected'] = true;
            $layout = array();
            if (!empty($data['workspace']['project_data']['layout']) && is_array($data['workspace']['project_data']['layout']))
                $layout = $data['workspace']['project_data']['layout'];
            elseif (!empty($data['workspace']['settings']['layout']) && is_array($data['workspace']['settings']['layout']))
                $layout = $data['workspace']['settings']['layout'];
            elseif (!empty($data['settings']['layout']) && is_array($data['settings']['layout']))
                $layout = $data['settings']['layout'];
            elseif (!empty($data['layout']) && is_array($data['layout']))
                $layout = $data['layout'];

            if (!empty($layout['width_mode']))
                $info['width_mode'] = (string) $layout['width_mode'];
            if (!empty($layout['max_width']))
                $info['max_width'] = (int) $layout['max_width'];
        }
    }

    $generated_css = $theme_dir . '/css/themelab-generated.css';
    if (is_file($generated_css) && is_readable($generated_css))
    {
        $info['detected'] = true;
        if ($info['width_mode'] === '' || $info['max_width'] <= 0)
        {
            $css = @file_get_contents($generated_css, false, null, 0, 8192);
            if ($css !== false)
            {
                if ($info['width_mode'] === '' && preg_match('~--tl-width_mode:([^;]+)~', $css, $m))
                    $info['width_mode'] = trim($m[1]);
                if ($info['max_width'] <= 0 && preg_match('~--tl-max_width:([0-9]+)~', $css, $m))
                    $info['max_width'] = (int) $m[1];
            }
        }
    }

    return $info;
}

function BetterPortal_PageHasEmbeddedForum($id_page)
{
    global $smcFunc;

    $request = $smcFunc['db_query']('', '
        SELECT css_class
        FROM {db_prefix}betterportal_rows
        WHERE id_page={int:id_page} AND is_enabled={int:enabled}
        ORDER BY row_order, id_row
        LIMIT 1', array('id_page' => (int) $id_page, 'enabled' => 1));
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    return !empty($row) && BetterPortal_IsForumModeRow($row);
}

function BetterPortal_CurrentRequestShowsEmbeddedForum()
{
    global $modSettings;

    $mode = isset($modSettings['betterportal_mode']) ? $modSettings['betterportal_mode'] : 'separate';
    if ($mode !== 'home')
        return false;

    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    if ($action !== '' && $action !== 'portal')
        return false;

    $page = BetterPortal_ResolvePage();
    return !empty($page['id_page']) && BetterPortal_PageHasEmbeddedForum((int) $page['id_page']);
}

function BetterPortal_PrepareEmbeddedForum()
{
    global $context, $sourcedir;

    $saved_layers = isset($context['template_layers']) ? $context['template_layers'] : array();
    $saved_title = isset($context['page_title']) ? $context['page_title'] : null;
    $saved_canonical = isset($context['canonical_url']) ? $context['canonical_url'] : null;
    $had_robot = array_key_exists('robot_no_index', $context);
    $saved_robot = $had_robot ? $context['robot_no_index'] : null;

    require_once($sourcedir . '/BoardIndex.php');
    BoardIndex();

    // BoardIndex normally adds an outer layer containing its info center. In
    // Forum Mode we only embed the native board-index body inside the portal.
    $context['template_layers'] = $saved_layers;
    if ($saved_title !== null)
        $context['page_title'] = $saved_title;
    if ($saved_canonical !== null)
        $context['canonical_url'] = $saved_canonical;
    else
        unset($context['canonical_url']);
    if ($had_robot)
        $context['robot_no_index'] = $saved_robot;
    else
        unset($context['robot_no_index']);

    $context['betterportal_embedded_forum_ready'] = function_exists('template_main');
}

function BetterPortal_CreateBlock($id_page, $id_row, $column, $block_type, $definition = array())
{
    global $smcFunc;

    $request = $smcFunc['db_query']('', 'SELECT COALESCE(MAX(block_order),0)+1 FROM {db_prefix}betterportal_blocks WHERE id_row={int:id_row} AND column_key={string:column}', array('id_row' => $id_row, 'column' => $column));
    list ($order) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);

    $title = !empty($definition['default_title']) ? (string) $definition['default_title'] : (!empty($definition['label']) ? (string) $definition['label'] : 'Block');
    $settings = !empty($definition['default_settings']) && is_array($definition['default_settings']) ? $definition['default_settings'] : array();
    if ($block_type === 'custom_text' && empty($settings))
        $settings = array('body' => 'Edit this block content.', 'parse_bbc' => 1);

    $now = time();
    return (int) $smcFunc['db_insert']('insert', '{db_prefix}betterportal_blocks', array(
        'id_page'=>'int','id_row'=>'int','column_key'=>'string-20','block_type'=>'string-80','title'=>'string-160','block_order'=>'int',
        'is_enabled'=>'int','show_title'=>'int','allowed_groups'=>'string','cache_ttl'=>'int','settings'=>'string','created_at'=>'int','updated_at'=>'int'
    ), array($id_page,$id_row,$column,substr($block_type,0,80),substr($title,0,160),(int)$order,1,1,'["everyone"]',(int)($definition['default_ttl'] ?? 0),json_encode($settings),$now,$now), array('id_block'), 1);
}

function BetterPortal_CreateCustomTextBlock($id_page, $id_row, $column, $title, $body)
{
    global $smcFunc;
    $request = $smcFunc['db_query']('', 'SELECT COALESCE(MAX(block_order),0)+1 FROM {db_prefix}betterportal_blocks WHERE id_row={int:id_row} AND column_key={string:column}', array('id_row'=>$id_row,'column'=>$column));
    list ($order) = $smcFunc['db_fetch_row']($request); $smcFunc['db_free_result']($request);
    $now = time();
    $settings = json_encode(array('body' => $body, 'parse_bbc' => 1));
    return (int) $smcFunc['db_insert']('insert', '{db_prefix}betterportal_blocks', array(
        'id_page'=>'int','id_row'=>'int','column_key'=>'string-20','block_type'=>'string-80','title'=>'string-160','block_order'=>'int',
        'is_enabled'=>'int','show_title'=>'int','allowed_groups'=>'string','cache_ttl'=>'int','settings'=>'string','created_at'=>'int','updated_at'=>'int'
    ), array($id_page,$id_row,$column,'custom_text',substr($title,0,160),(int)$order,1,1,'["everyone"]',0,$settings,$now,$now), array('id_block'), 1);
}

function BetterPortal_HandleBlockAction($id_page,$id_block,$action,$post)
{
    global $smcFunc;
    $block=BetterPortal_LoadBlock($id_page,$id_block); if(empty($block)) return;
    if($action==='save')
    {
        $row=BetterPortal_LoadRow($id_page,(int)$block['id_row']); if(empty($row)) return;
        $columns=BetterPortal_LayoutColumns($row['layout']); $column=(string)($post['column_key']??$block['column_key']); if(!in_array($column,$columns,true)) $column=$columns[0];
        if (BetterPortal_IsForumModeRow($row) && $column === BetterPortal_ForumMainColumn($row['layout']))
            $column = $block['column_key'] === BetterPortal_ForumMainColumn($row['layout']) ? BetterPortal_FirstNonForumColumn($row['layout']) : $block['column_key'];
        $title=trim((string)($post['block_title']??'')); if($title==='') $title='Untitled Block';
        $settings=BetterPortal_DecodeSettings($block['settings']);
        if($block['block_type']==='custom_text')
        {
            $body=(string)($post['block_body']??''); if(strlen($body)>50000) $body=substr($body,0,50000);
            $settings=array('body'=>$body,'parse_bbc'=>1);
        }
        else
        {
            $registry = BetterPortal_BlockRegistry();
            if (isset($registry[$block['block_type']]))
                $settings = BetterPortal_NormalizeBlockSettings($registry[$block['block_type']], $settings, $post);
        }
        $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_blocks SET title={string:title},column_key={string:column},show_title={int:show},is_enabled={int:enabled},settings={string:settings},updated_at={int:now} WHERE id_block={int:id}', array(
            'title'=>substr($title,0,160),'column'=>$column,'show'=>!empty($post['block_show_title'])?1:0,'enabled'=>!empty($post['block_enabled'])?1:0,'settings'=>json_encode($settings),'now'=>time(),'id'=>$id_block));
    }
    elseif($action==='up'||$action==='down') BetterPortal_MoveBlock((int)$block['id_row'],$block['column_key'],$id_block,$action);
    elseif($action==='delete') $smcFunc['db_query']('', 'DELETE FROM {db_prefix}betterportal_blocks WHERE id_block={int:id}', array('id'=>$id_block));
}

/**
 * Resolve a settings field's choices. Dynamic blocks can supply an
 * options_callback so the builder always reflects live SMF content.
 */
function BetterPortal_GetSettingOptions($field)
{
    if (!is_array($field))
        return array();

    if (!empty($field['options_callback']) && is_callable($field['options_callback']))
    {
        $options = call_user_func($field['options_callback']);
        return is_array($options) ? $options : array();
    }

    return !empty($field['options']) && is_array($field['options']) ? $field['options'] : array();
}

/**
 * Build a live board selector for dynamic Forum Board blocks. Only boards the
 * current administrator can see are offered, and child boards are indented.
 */
function BetterPortal_GetBoardOptions()
{
    global $smcFunc, $txt;

    $options = array('0' => $txt['betterportal_dynamic_board_choose']);
    $request = $smcFunc['db_query']('', '
        SELECT b.id_board, b.name, b.child_level, c.name AS category_name, c.cat_order, b.board_order
        FROM {db_prefix}boards AS b
            INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
        WHERE {query_see_board}
        ORDER BY c.cat_order, b.board_order, b.id_board', array()
    );

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $indent = str_repeat('— ', max(0, (int) $row['child_level']));
        $options[(string) (int) $row['id_board']] = $row['category_name'] . ' — ' . $indent . $row['name'];
    }
    $smcFunc['db_free_result']($request);

    return $options;
}


/**
 * Build a live Better Chat Portal Room selector. Better Chat owns room
 * discovery and access; Better Portal stores only the selected room id.
 */
function BetterPortal_GetBetterChatPortalRoomOptions()
{
    global $txt;

    $options = array('0' => $txt['betterportal_better_chat_room_choose']);
    if (!BetterPortal_ProviderAvailable('chat_enabled', 'ChatHandler.php', 'better_chat_get_portal_rooms'))
        return $options;

    $rooms = better_chat_get_portal_rooms(false);
    if (!is_array($rooms))
        return $options;

    foreach ($rooms as $id_room => $room)
    {
        $name = trim((string) ($room['room_name'] ?? ''));
        if ($name === '')
            $name = sprintf($txt['betterportal_better_chat_room_fallback'], (int) $id_room);
        $options[(string) (int) $id_room] = $name;
    }

    return $options;
}


/**
 * Build an album selector for Better Gallery interactive blocks. Only albums the
 * current administrator can discover and access are offered. The block stores
 * only the album id; Better Gallery remains authoritative for content/access.
 */
function BetterPortal_GetBetterMediaAlbumOptions()
{
    global $smcFunc, $user_info, $txt;

    $options = array('0' => $txt['betterportal_better_media_browser_choose']);
    if (!BetterPortal_ProviderAvailable('bg_enabled', 'BetterGallery.php', 'bg_album_content_accessible') || !bg_allowed('bg_view_gallery'))
        return $options;

    $request = $smcFunc['db_query']('', '
        SELECT a.id_album, a.id_owner, a.title, a.discovery_visibility, a.access_mode, a.password_hash,
            mem.real_name AS owner_name
        FROM {db_prefix}bg_albums AS a
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_owner)
        WHERE a.approved = {int:approved}
        ORDER BY a.updated_at DESC, a.id_album DESC
        LIMIT 150', array('approved' => 1));

    $is_admin = !empty($user_info['is_admin']) || bg_allowed('bg_moderate_gallery');
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        if (!bg_portfolio_album_visible($row)
            || !bg_album_is_discoverable($row, (int) $user_info['id'], $is_admin)
            || !bg_album_content_accessible($row, (int) $user_info['id'], $is_admin))
            continue;

        $label = (string) $row['title'];
        if (!empty($row['owner_name']))
            $label .= ' — ' . $row['owner_name'];
        $options[(string) (int) $row['id_album']] = $label;
    }
    $smcFunc['db_free_result']($request);

    return $options;
}

/**
 * Build a live poll selector for Dynamic Poll blocks. The first option tracks
 * the newest active poll automatically; the remaining options point at a
 * specific poll topic. Only polls in boards the administrator can see appear.
 */
function BetterPortal_GetPollOptions()
{
    global $smcFunc, $txt, $modSettings;

    $options = array('latest' => $txt['betterportal_dynamic_poll_latest']);
    $approval_where = !empty($modSettings['postmod_active']) ? '
            AND t.approved = {int:approved}
            AND m.approved = {int:approved}' : '';

    $request = $smcFunc['db_query']('', '
        SELECT t.id_topic, b.name AS board_name, m.subject, p.question, p.expire_time, p.voting_locked
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
        WHERE t.id_poll != {int:no_poll}
            AND {query_see_board}' . $approval_where . '
        ORDER BY t.id_last_msg DESC
        LIMIT {int:limit}', array('no_poll' => 0, 'approved' => 1, 'limit' => 100));

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        censorText($row['subject']);
        censorText($row['question']);
        $question = trim(strip_tags(parse_bbc($row['question'])));
        if ($question === '')
            $question = $row['subject'];
        $suffix = (!empty($row['voting_locked']) || (!empty($row['expire_time']) && (int) $row['expire_time'] < time()))
            ? ' ' . $txt['betterportal_dynamic_poll_closed_suffix']
            : '';
        $options[(string) (int) $row['id_topic']] = $row['board_name'] . ' — ' . $question . $suffix;
    }
    $smcFunc['db_free_result']($request);

    return $options;
}


/**
 * Build a live topic selector for Dynamic Topic blocks. Keep the list practical
 * by offering the 100 most recently active topics the current administrator can
 * see. The stored value is only the topic id; all content remains live in SMF.
 */
function BetterPortal_GetTopicOptions()
{
    global $smcFunc, $txt, $modSettings;

    $options = array('0' => $txt['betterportal_dynamic_topic_choose']);
    $approval_where = !empty($modSettings['postmod_active']) ? '
            AND t.approved = {int:approved}
            AND m.approved = {int:approved}' : '';

    $request = $smcFunc['db_query']('', '
        SELECT t.id_topic, b.name AS board_name, m.subject
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
        WHERE {query_see_board}' . $approval_where . '
        ORDER BY t.id_last_msg DESC
        LIMIT {int:limit}', array('approved' => 1, 'limit' => 100));

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        censorText($row['subject']);
        $options[(string) (int) $row['id_topic']] = $row['board_name'] . ' — ' . $row['subject'];
    }
    $smcFunc['db_free_result']($request);

    return $options;
}

function BetterPortal_NormalizeBlockSettings($definition, $settings, $post)
{
    if (!is_array($settings))
        $settings = array();

    if (empty($definition['settings_fields']) || !is_array($definition['settings_fields']))
        return $settings;

    foreach ($definition['settings_fields'] as $key => $field)
    {
        $input = 'block_setting_' . $key;
        if (!array_key_exists($input, $post))
            continue;

        $type = !empty($field['type']) ? (string) $field['type'] : 'text';
        if ($type === 'number')
        {
            $value = (int) $post[$input];
            if (isset($field['min'])) $value = max((int) $field['min'], $value);
            if (isset($field['max'])) $value = min((int) $field['max'], $value);
            $settings[$key] = $value;
        }
        elseif ($type === 'select')
        {
            $value = (string) $post[$input];
            $options = BetterPortal_GetSettingOptions($field);
            if (array_key_exists($value, $options))
                $settings[$key] = $value;
        }
        else
        {
            $value = trim((string) $post[$input]);
            $max_length = isset($field['max_length']) ? max(1, (int) $field['max_length']) : 255;
            $settings[$key] = substr($value, 0, $max_length);
        }
    }

    return $settings;
}

function BetterPortal_MoveBlock($id_row,$column,$id_block,$direction)
{
    global $smcFunc;
    $ids=array(); $request=$smcFunc['db_query']('', 'SELECT id_block FROM {db_prefix}betterportal_blocks WHERE id_row={int:id_row} AND column_key={string:column} ORDER BY block_order,id_block', array('id_row'=>$id_row,'column'=>$column)); while($r=$smcFunc['db_fetch_assoc']($request)) $ids[]=(int)$r['id_block']; $smcFunc['db_free_result']($request);
    $i=array_search($id_block,$ids,true); if($i===false) return; $j=$direction==='up'?$i-1:$i+1; if(!isset($ids[$j])) return; $tmp=$ids[$i];$ids[$i]=$ids[$j];$ids[$j]=$tmp;
    foreach($ids as $n=>$bid) $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_blocks SET block_order={int:o} WHERE id_block={int:id}', array('o'=>$n+1,'id'=>$bid));
}

/**
 * Persist the complete drag-and-drop arrangement for all movable columns.
 * The submitted id set must exactly match the blocks that are currently
 * movable on this page, so a stale or tampered request cannot lose blocks.
 */
function BetterPortal_SaveBlockLayout($id_page, $layout)
{
    global $smcFunc;
    if (!is_array($layout)) return false;

    $rows = array();
    $request=$smcFunc['db_query']('', 'SELECT id_row,layout,css_class FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page} AND is_enabled={int:enabled} ORDER BY row_order,id_row', array('id_page'=>(int)$id_page,'enabled'=>1));
    while($row=$smcFunc['db_fetch_assoc']($request)) $rows[(int)$row['id_row']]=$row;
    $smcFunc['db_free_result']($request);
    if(empty($rows)) return false;

    $existing=array();
    foreach($rows as $rid=>$row)
    {
        $existing[$rid]=array();
        $columns=BetterPortal_LayoutColumns($row['layout']);
        $forum_main=BetterPortal_IsForumModeRow($row)?BetterPortal_ForumMainColumn($row['layout']):'';
        foreach($columns as $column) if($column!==$forum_main) $existing[$rid][$column]=array();
    }

    $request=$smcFunc['db_query']('', 'SELECT id_block,id_row,column_key FROM {db_prefix}betterportal_blocks WHERE id_page={int:id_page} ORDER BY id_block', array('id_page'=>(int)$id_page));
    $movable_ids=array();
    while($block=$smcFunc['db_fetch_assoc']($request))
    {
        $rid=(int)$block['id_row']; $col=(string)$block['column_key'];
        if(isset($existing[$rid][$col])) $movable_ids[(int)$block['id_block']]=true;
    }
    $smcFunc['db_free_result']($request);

    $submitted=array(); $normalized=array();
    foreach($rows as $rid=>$row)
    {
        $normalized[$rid]=array();
        $row_data = isset($layout[(string)$rid]) && is_array($layout[(string)$rid]) ? $layout[(string)$rid] : (isset($layout[$rid]) && is_array($layout[$rid]) ? $layout[$rid] : array());
        foreach(array_keys($existing[$rid]) as $column)
        {
            $normalized[$rid][$column]=array();
            $ids=isset($row_data[$column])&&is_array($row_data[$column])?$row_data[$column]:array();
            foreach($ids as $id_block)
            {
                $id_block=(int)$id_block;
                if($id_block<=0||isset($submitted[$id_block])||!isset($movable_ids[$id_block])) return false;
                $submitted[$id_block]=true;
                $normalized[$rid][$column][]=$id_block;
            }
        }
    }
    $a=array_keys($movable_ids); $b=array_keys($submitted); sort($a,SORT_NUMERIC); sort($b,SORT_NUMERIC); if($a!==$b) return false;

    $now=time();
    foreach($normalized as $rid=>$columns)
        foreach($columns as $column=>$ids)
            foreach($ids as $index=>$id_block)
                $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_blocks SET id_row={int:id_row},column_key={string:column},block_order={int:ord},updated_at={int:now} WHERE id_block={int:id_block} AND id_page={int:id_page}', array('id_row'=>(int)$rid,'column'=>$column,'ord'=>$index+1,'now'=>$now,'id_block'=>$id_block,'id_page'=>(int)$id_page));
    return true;
}

function BetterPortal_SaveRowOrder($id_page, $row_order)
{
    global $smcFunc;
    $existing=array();
    $request=$smcFunc['db_query']('', 'SELECT id_row FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page} ORDER BY row_order,id_row', array('id_page'=>(int)$id_page));
    while($r=$smcFunc['db_fetch_assoc']($request)) $existing[]=(int)$r['id_row'];
    $smcFunc['db_free_result']($request);
    $submitted=array_values(array_map('intval',$row_order));
    $a=$existing;$b=$submitted;sort($a,SORT_NUMERIC);sort($b,SORT_NUMERIC);if($a!==$b||count($submitted)!==count(array_unique($submitted))) return false;
    foreach($submitted as $i=>$rid) $smcFunc['db_query']('', 'UPDATE {db_prefix}betterportal_rows SET row_order={int:ord} WHERE id_page={int:id_page} AND id_row={int:id_row}', array('ord'=>$i+1,'id_page'=>(int)$id_page,'id_row'=>$rid));
    return true;
}

function BetterPortal_HandleRowAction($id_page,$id_row,$action,$post)
{
    global $smcFunc;
    $row=BetterPortal_LoadRow((int)$id_page,(int)$id_row); if(empty($row)) return false;
    if($action==='save')
    {
        $layout=(string)($post['row_layout']??$row['layout']);
        BetterPortal_SetRowLayout((int)$id_page,(int)$id_row,$layout);
        BetterPortal_SetRowColumnWidthPreset((int)$id_page,(int)$id_row,(string)($post['row_widths']??'auto'),$layout);
        BetterPortal_SetRowForumMode((int)$id_page,(int)$id_row,!empty($post['row_forum_mode']));
        return true;
    }
    if($action==='up'||$action==='down')
    {
        $ids=array();$request=$smcFunc['db_query']('', 'SELECT id_row FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page} ORDER BY row_order,id_row', array('id_page'=>(int)$id_page));while($r=$smcFunc['db_fetch_assoc']($request))$ids[]=(int)$r['id_row'];$smcFunc['db_free_result']($request);
        $i=array_search((int)$id_row,$ids,true);$j=$action==='up'?$i-1:$i+1;if($i===false||!isset($ids[$j]))return false;$tmp=$ids[$i];$ids[$i]=$ids[$j];$ids[$j]=$tmp;return BetterPortal_SaveRowOrder((int)$id_page,$ids);
    }
    if($action==='delete')
    {
        $request=$smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page}', array('id_page'=>(int)$id_page));list($count)=$smcFunc['db_fetch_row']($request);$smcFunc['db_free_result']($request);if((int)$count<=1)return false;
        $request=$smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}betterportal_blocks WHERE id_row={int:id_row}', array('id_row'=>(int)$id_row));list($blocks)=$smcFunc['db_fetch_row']($request);$smcFunc['db_free_result']($request);if((int)$blocks>0)return false;
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page} AND id_row={int:id_row}', array('id_page'=>(int)$id_page,'id_row'=>(int)$id_row));
        BetterPortal_SaveRowOrder((int)$id_page, BetterPortal_RowIds((int)$id_page)); return true;
    }
    return false;
}

function BetterPortal_RowIds($id_page)
{
    global $smcFunc;$ids=array();$request=$smcFunc['db_query']('', 'SELECT id_row FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page} ORDER BY row_order,id_row', array('id_page'=>(int)$id_page));while($r=$smcFunc['db_fetch_assoc']($request))$ids[]=(int)$r['id_row'];$smcFunc['db_free_result']($request);return $ids;
}

function BetterPortal_LoadBuilderRows($id_page)
{
    global $smcFunc;
    BetterPortal_EnsurePageLayoutRow($id_page);
    $rows=array();
    $request=$smcFunc['db_query']('', 'SELECT id_row,id_page,row_order,layout,css_class,is_enabled FROM {db_prefix}betterportal_rows WHERE id_page={int:id_page} ORDER BY row_order,id_row', array('id_page'=>(int)$id_page));
    while($row=$smcFunc['db_fetch_assoc']($request))
    {
        $row['blocks']=array();
        $row['columns']=BetterPortal_LayoutColumns($row['layout']);
        $row['column_widths']=BetterPortal_GetColumnWidthPreset($row);
        $row['effective_width']=BetterPortal_EffectiveColumnWidthPreset($row);
        $row['forum_mode']=BetterPortal_IsForumModeRow($row);
        $row['forum_main_column']=BetterPortal_ForumMainColumn($row['layout']);
        $rows[(int)$row['id_row']]=$row;
    }
    $smcFunc['db_free_result']($request);
    if(empty($rows)) return array();
    $request=$smcFunc['db_query']('', 'SELECT * FROM {db_prefix}betterportal_blocks WHERE id_page={int:id_page} ORDER BY id_row,column_key,block_order,id_block', array('id_page'=>(int)$id_page));
    while($block=$smcFunc['db_fetch_assoc']($request))
    {
        $rid=(int)$block['id_row']; if(!isset($rows[$rid])) continue;
        $settings=BetterPortal_DecodeSettings($block['settings']);$block['body']=$settings['body']??'';$block['settings_decoded']=$settings;$rows[$rid]['blocks'][]=$block;
    }
    $smcFunc['db_free_result']($request);
    return array_values($rows);
}
function BetterPortal_LoadPublicPages()
{
    global $smcFunc;

    $pages = array();
    $request = $smcFunc['db_query']('', '
        SELECT p.id_page, p.slug, p.title, p.is_home, p.is_enabled, p.allowed_groups,
            COALESCE(n.show_in_nav, {int:nav_visible}) AS show_in_nav,
            COALESCE(n.menu_label, {string:empty_label}) AS menu_label,
            COALESCE(n.nav_order, p.id_page * {int:nav_step}) AS nav_order
        FROM {db_prefix}betterportal_pages AS p
            LEFT JOIN {db_prefix}betterportal_page_nav AS n ON (n.id_page = p.id_page)
        WHERE p.is_enabled={int:enabled}
        ORDER BY p.is_home DESC, COALESCE(n.nav_order, p.id_page * {int:nav_step}) ASC, p.id_page ASC', array(
            'enabled' => 1, 'nav_visible' => 1, 'empty_label' => '', 'nav_step' => 10,
        ));
    while ($page = $smcFunc['db_fetch_assoc']($request))
    {
        if (BetterPortal_CanView($page['allowed_groups']))
            $pages[] = $page;
    }
    $smcFunc['db_free_result']($request);

    return $pages;
}

/**
 * Whether a Portal column is wide enough to host an SMF native board/topic view.
 *
 * Native MessageIndex/Display templates were designed for main-content width.
 * Letting them take ownership from an equal or sidebar-sized column crushes the
 * post profile, controls and composer. Compact/preview blocks remain valid in
 * every column; only the request-wide native takeover is restricted here.
 */
function BetterPortal_BlockColumnSupportsNative($block)
{
    global $smcFunc;

    if (empty($block) || !is_array($block))
        return false;

    $row = array(
        'layout' => (string) ($block['row_layout'] ?? ''),
        'css_class' => (string) ($block['row_css_class'] ?? ''),
    );

    // Normal public page rendering now passes row metadata with each block.
    // Keep a database fallback for any other renderer/integration that calls a
    // block directly with only id_row/column_key.
    if ($row['layout'] === '' && !empty($block['id_row']))
    {
        $request = $smcFunc['db_query']('', '
            SELECT layout, css_class
            FROM {db_prefix}betterportal_rows
            WHERE id_row = {int:id_row}
            LIMIT 1', array('id_row' => (int) $block['id_row']));
        $db_row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        if (!empty($db_row))
            $row = $db_row;
    }

    return BetterPortal_IsNativeInteractiveColumnSuitable($row, (string) ($block['column_key'] ?? ''));
}

function BetterPortal_IsNativeInteractiveColumnSuitable($row, $column)
{
    if (empty($row) || !is_array($row))
        return false;

    $layout = (string) ($row['layout'] ?? '1');
    $columns = BetterPortal_LayoutColumns($layout);
    if (!in_array((string) $column, $columns, true))
        return false;

    if (count($columns) === 1)
        return true;

    $preset = BetterPortal_EffectiveColumnWidthPreset($row);
    if ($preset === 'wide-left')
        return (string) $column === 'col1';
    if ($preset === 'wide-right')
        return (string) $column === $columns[count($columns) - 1];
    if ($preset === 'wide-center' && count($columns) === 3)
        return (string) $column === 'col2';

    // Equal multi-column layouts intentionally have no native owner. A topic or
    // board placed there stays a normal preview/list instead of being squeezed.
    return false;
}

/**
 * Find the native interactive content block that should own this request.
 *
 * SMF exposes one request-wide board/topic context, so only one native view can
 * own a Portal page. dev26a only considers blocks in a suitable main/wide
 * column. This prevents a narrow Topic block from stealing ownership from an
 * Interactive Forum Board in the page's real content column.
 */
function BetterPortal_FindInteractiveNativeBlock($id_page)
{
    global $smcFunc, $modSettings;

    $request = $smcFunc['db_query']('', '
        SELECT b.*, r.layout AS row_layout, r.css_class AS row_css_class
        FROM {db_prefix}betterportal_blocks AS b
            INNER JOIN {db_prefix}betterportal_rows AS r ON (r.id_row = b.id_row AND r.id_page = b.id_page)
        WHERE b.id_page = {int:id_page}
            AND b.block_type IN ({string:board_type}, {string:topic_type})
            AND b.is_enabled = {int:enabled}
            AND r.is_enabled = {int:enabled}
        ORDER BY r.row_order, b.column_key, b.block_order, b.id_block', array(
            'id_page' => (int) $id_page,
            'board_type' => 'dynamic_forum_board',
            'topic_type' => 'dynamic_topic',
            'enabled' => 1,
        ));

    $target = array();
    while ($block = $smcFunc['db_fetch_assoc']($request))
    {
        if (!BetterPortal_CanView($block['allowed_groups']))
            continue;

        $row = array(
            'layout' => (string) ($block['row_layout'] ?? '1'),
            'css_class' => (string) ($block['row_css_class'] ?? ''),
        );
        if (!BetterPortal_IsNativeInteractiveColumnSuitable($row, (string) $block['column_key']))
            continue;

        $settings = BetterPortal_DecodeSettings($block['settings']);
        if ($block['block_type'] === 'dynamic_forum_board')
        {
            if (($settings['display_style'] ?? '') !== 'interactive')
                continue;

            $id_board = (int) ($settings['board_id'] ?? 0);
            if ($id_board <= 0)
                continue;

            $check = $smcFunc['db_query']('', '
                SELECT b.id_board
                FROM {db_prefix}boards AS b
                WHERE b.id_board = {int:id_board}
                    AND {query_see_board}
                LIMIT 1', array('id_board' => $id_board));
            $visible = $smcFunc['db_fetch_row']($check);
            $smcFunc['db_free_result']($check);
            if (empty($visible))
                continue;

            $block['settings_decoded'] = $settings;
            $block['interactive_board_id'] = $id_board;
            $block['native_kind'] = 'board';
            $target = $block;
            break;
        }

        $id_topic = (int) ($settings['topic_id'] ?? 0);
        if ($id_topic <= 0)
            continue;

        $approval_where = !empty($modSettings['postmod_active']) ? ' AND t.approved = {int:approved}' : '';
        $check = $smcFunc['db_query']('', '
            SELECT t.id_topic, t.id_board
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE t.id_topic = {int:id_topic}
                AND {query_see_board}' . $approval_where . '
            LIMIT 1', array('id_topic' => $id_topic, 'approved' => 1));
        $visible = $smcFunc['db_fetch_assoc']($check);
        $smcFunc['db_free_result']($check);
        if (empty($visible))
            continue;

        $block['settings_decoded'] = $settings;
        $block['interactive_topic_id'] = (int) $visible['id_topic'];
        $block['interactive_board_id'] = (int) $visible['id_board'];
        $block['native_kind'] = 'topic';
        $target = $block;
        break;
    }
    $smcFunc['db_free_result']($request);

    return $target;
}

/** Boot a native MessageIndex directly into an Interactive Forum Board block. */
function BetterPortal_BootInteractiveBoard($page, $block)
{
    global $sourcedir, $board, $topic;

    $id_page = (int) ($page['id_page'] ?? 0);
    $id_block = (int) ($block['id_block'] ?? 0);
    $id_board = (int) ($block['interactive_board_id'] ?? 0);
    if ($id_page <= 0 || $id_block <= 0 || $id_board <= 0)
        return false;

    $_REQUEST['bp_page'] = $id_page;
    $_REQUEST['bp_block'] = $id_block;
    $_REQUEST['bp_view'] = 'board';
    $_REQUEST['board'] = $id_board . '.0';
    $_GET['board'] = $id_board . '.0';
    $board = $id_board;
    $topic = 0;

    loadBoard();
    loadPermissions();
    if (empty($board) || (int) $board !== $id_board)
        return false;

    BetterPortal_RememberBlockShell(array('id_page'=>$id_page,'id_block'=>$id_block,'id_board'=>$id_board));
    require_once($sourcedir . '/MessageIndex.php');
    MessageIndex();
    return true;
}

/** Boot SMF's native Display view directly into a Dynamic Topic block. */
function BetterPortal_BootInteractiveTopic($page, $block)
{
    global $sourcedir, $board, $topic;

    $id_page = (int) ($page['id_page'] ?? 0);
    $id_block = (int) ($block['id_block'] ?? 0);
    $id_topic = (int) ($block['interactive_topic_id'] ?? 0);
    $id_board = (int) ($block['interactive_board_id'] ?? 0);
    if ($id_page <= 0 || $id_block <= 0 || $id_topic <= 0 || $id_board <= 0)
        return false;

    $_REQUEST['bp_page'] = $id_page;
    $_REQUEST['bp_block'] = $id_block;
    $_REQUEST['bp_view'] = 'topic';
    $_REQUEST['topic'] = $id_topic . '.0';
    $_GET['topic'] = $id_topic . '.0';
    unset($_REQUEST['board'], $_GET['board']);
    $topic = $id_topic;
    $board = 0;

    loadBoard();
    loadPermissions();
    if (empty($topic) || (int) $topic !== $id_topic || empty($board) || (int) $board !== $id_board)
        return false;

    BetterPortal_RememberBlockShell(array('id_page'=>$id_page,'id_block'=>$id_block,'id_board'=>$id_board));
    require_once($sourcedir . '/Display.php');
    Display();
    return true;
}


function BetterPortal_Main()
{
    global $context, $modSettings;
    BetterPortal_LoadLanguage();
    if (!empty($modSettings['betterportal_mode']) && $modSettings['betterportal_mode'] === 'disabled') fatal_lang_error('betterportal_disabled', false);
    $page = BetterPortal_ResolvePage();
    if (empty($page) || empty($page['is_enabled']) || !BetterPortal_CanView($page['allowed_groups'])) fatal_lang_error('betterportal_page_not_found', false);

    $rows = BetterPortal_BuildPageRows((int) $page['id_page']);
    $has_forum = false;
    foreach ($rows as $row)
        if (!empty($row['forum_mode'])) { $has_forum = true; break; }

    if ($has_forum)
        BetterPortal_PrepareEmbeddedForum();
    else
    {
        // The first native-capable interactive block owns SMF's request-wide
        // board/topic context. Forum Board starts in MessageIndex; Dynamic Topic
        // starts directly in Display. The surrounding Portal page stays put.
        $interactive_block = BetterPortal_FindInteractiveNativeBlock((int) $page['id_page']);
        if (!empty($interactive_block))
        {
            if (($interactive_block['native_kind'] ?? '') === 'topic' && BetterPortal_BootInteractiveTopic($page, $interactive_block))
                return;
            if (($interactive_block['native_kind'] ?? '') === 'board' && BetterPortal_BootInteractiveBoard($page, $interactive_block))
                return;
        }
    }

    $context['betterportal_page']=$page;
    $context['betterportal_pages']=BetterPortal_LoadPublicPages();
    $context['betterportal_rows']=$rows;
    $context['page_title']=$page['title'];
    $context['sub_template']='betterportal_page';
    BetterPortal_LoadPublicTemplate();
    loadCSSFile('better_portal.css', array('default_theme'=>true,'minimize'=>true,'order'=>1500), 'better_portal');
}

function BetterPortal_ResolvePage()
{
    global $smcFunc;

    $slug = isset($_REQUEST['page']) ? BetterPortal_Slug((string) $_REQUEST['page']) : '';
    if ($slug !== '')
    {
        $request = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}betterportal_pages WHERE slug={string:slug} LIMIT 1', array('slug' => $slug));
        $page = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        return $page ?: array();
    }

    // If the configured Home page is not in this visitor's audience, fall back
    // to the first enabled page they can view. This keeps audience-aware Home
    // setups from turning the site root into an access error for other groups.
    $public_pages = BetterPortal_LoadPublicPages();
    if (empty($public_pages))
        return array();

    $chosen = $public_pages[0];
    foreach ($public_pages as $candidate)
        if (!empty($candidate['is_home'])) { $chosen = $candidate; break; }

    return BetterPortal_LoadPageRecord((int) $chosen['id_page']);
}

function BetterPortal_BuildPageRows($id_page)
{
    global $smcFunc;

    $registry = BetterPortal_BlockRegistry();
    $rows = array();
    $request = $smcFunc['db_query']('', '
        SELECT id_row,row_order,layout,css_class
        FROM {db_prefix}betterportal_rows
        WHERE id_page={int:id_page} AND is_enabled={int:enabled}
        ORDER BY row_order,id_row', array('id_page'=>$id_page,'enabled'=>1));
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $row['columns'] = array();
        foreach (BetterPortal_LayoutColumns($row['layout']) as $column)
            $row['columns'][$column] = array();
        $row['forum_mode'] = BetterPortal_IsForumModeRow($row);
        $row['forum_main_column'] = BetterPortal_ForumMainColumn($row['layout']);
        $rows[(int) $row['id_row']] = $row;
    }
    $smcFunc['db_free_result']($request);
    if (empty($rows))
        return array();

    $request = $smcFunc['db_query']('', '
        SELECT *
        FROM {db_prefix}betterportal_blocks
        WHERE id_page={int:id_page} AND is_enabled={int:enabled}
        ORDER BY id_row,column_key,block_order,id_block', array('id_page'=>$id_page,'enabled'=>1));
    while ($block = $smcFunc['db_fetch_assoc']($request))
    {
        $rid = (int) $block['id_row'];
        if (!isset($rows[$rid]) || !BetterPortal_CanView($block['allowed_groups']) || !isset($registry[$block['block_type']]) || empty($registry[$block['block_type']]['render']))
            continue;

        $column = $block['column_key'];
        if (!isset($rows[$rid]['columns'][$column]))
            $column = array_key_first($rows[$rid]['columns']);
        if ($column === null)
            continue;

        // Forum Mode reserves only the main/native-forum column. Side columns
        // remain fully configurable and render any normal registered block.
        if (!empty($rows[$rid]['forum_mode']) && $column === $rows[$rid]['forum_main_column'])
            continue;

        // Give block renderers the owning row's layout metadata so dynamic
        // content can decide whether it is in a native-capable wide column
        // without re-querying the row.
        $block['row_layout'] = (string) $rows[$rid]['layout'];
        $block['row_css_class'] = (string) $rows[$rid]['css_class'];

        $rendered = call_user_func($registry[$block['block_type']]['render'], $block);
        if ($rendered === false)
            continue;
        $block['rendered'] = $rendered;
        $rows[$rid]['columns'][$column][] = $block;
    }
    $smcFunc['db_free_result']($request);
    return array_values($rows);
}

function BetterPortal_BlockRegistry()
{
    global $txt; BetterPortal_LoadLanguage();
    $blocks=array(
        'custom_text'=>array(
            'label'=>$txt['betterportal_block_custom_text'], 'description'=>$txt['betterportal_block_custom_text_desc'],
            'category'=>'content', 'provider'=>'Better Portal', 'render'=>'BetterPortal_RenderCustomText', 'cacheable'=>true, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_custom_text'], 'default_settings'=>array('body'=>'Edit this block content.','parse_bbc'=>1),
        ),
        'recent_posts'=>array(
            'label'=>$txt['betterportal_block_recent_posts'], 'description'=>$txt['betterportal_block_recent_posts_desc'],
            'category'=>'forum', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderRecentPosts', 'cacheable'=>true, 'default_ttl'=>60,
            'default_title'=>$txt['betterportal_block_recent_posts'], 'default_settings'=>array('limit'=>5),
            'settings_fields'=>array('limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20)),
        ),
        'recent_topics'=>array(
            'label'=>$txt['betterportal_block_recent_topics'], 'description'=>$txt['betterportal_block_recent_topics_desc'],
            'category'=>'forum', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderRecentTopics', 'cacheable'=>true, 'default_ttl'=>60,
            'default_title'=>$txt['betterportal_block_recent_topics'], 'default_settings'=>array('limit'=>5),
            'settings_fields'=>array('limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20)),
        ),
        'dynamic_forum_board'=>array(
            'label'=>$txt['betterportal_block_dynamic_forum_board'], 'description'=>$txt['betterportal_block_dynamic_forum_board_desc'],
            'category'=>'dynamic', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderDynamicForumBoard', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_dynamic_forum_board'],
            'default_settings'=>array('board_id'=>0,'limit'=>10,'display_style'=>'full'),
            'settings_fields'=>array(
                'board_id'=>array('type'=>'select','label'=>$txt['betterportal_dynamic_board_select'],'options_callback'=>'BetterPortal_GetBoardOptions'),
                'limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>25),
                'display_style'=>array('type'=>'select','label'=>$txt['betterportal_block_display_style'],'options'=>array('interactive'=>$txt['betterportal_dynamic_board_interactive'],'full'=>$txt['betterportal_dynamic_board_full'],'compact'=>$txt['betterportal_dynamic_board_compact'])),
            ),
        ),
        'dynamic_poll'=>array(
            'label'=>$txt['betterportal_block_dynamic_poll'], 'description'=>$txt['betterportal_block_dynamic_poll_desc'],
            'category'=>'dynamic', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderDynamicPoll', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_dynamic_poll'],
            'default_settings'=>array('poll_topic'=>'latest'),
            'settings_fields'=>array(
                'poll_topic'=>array('type'=>'select','label'=>$txt['betterportal_dynamic_poll_select'],'options_callback'=>'BetterPortal_GetPollOptions'),
            ),
        ),
        'dynamic_topic'=>array(
            'label'=>$txt['betterportal_block_dynamic_topic'], 'description'=>$txt['betterportal_block_dynamic_topic_desc'],
            'category'=>'dynamic', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderDynamicTopic', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_dynamic_topic'],
            'default_settings'=>array('topic_id'=>0),
            'settings_fields'=>array(
                'topic_id'=>array('type'=>'select','label'=>$txt['betterportal_dynamic_topic_select'],'options_callback'=>'BetterPortal_GetTopicOptions'),
            ),
        ),
        'member_welcome'=>array(
            'label'=>$txt['betterportal_block_member_welcome'], 'description'=>$txt['betterportal_block_member_welcome_desc'],
            'category'=>'content', 'provider'=>'Better Portal', 'render'=>'BetterPortal_RenderMemberWelcome', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_member_welcome'], 'default_settings'=>array(),
        ),
        'forum_search'=>array(
            'label'=>$txt['betterportal_block_forum_search'], 'description'=>$txt['betterportal_block_forum_search_desc'],
            'category'=>'forum', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderForumSearch', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_forum_search'], 'default_settings'=>array(),
        ),
        'top_posters'=>array(
            'label'=>$txt['betterportal_block_top_posters'], 'description'=>$txt['betterportal_block_top_posters_desc'],
            'category'=>'forum', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderTopPosters', 'cacheable'=>true, 'default_ttl'=>300,
            'default_title'=>$txt['betterportal_block_top_posters'], 'default_settings'=>array('limit'=>5),
            'settings_fields'=>array('limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20)),
        ),
        'whos_online'=>array(
            'label'=>$txt['betterportal_block_whos_online'], 'description'=>$txt['betterportal_block_whos_online_desc'],
            'category'=>'forum', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderWhosOnline', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_whos_online'], 'default_settings'=>array(),
        ),
        'forum_stats'=>array(
            'label'=>$txt['betterportal_block_forum_stats'], 'description'=>$txt['betterportal_block_forum_stats_desc'],
            'category'=>'forum', 'provider'=>'SMF', 'render'=>'BetterPortal_RenderForumStats', 'cacheable'=>true, 'default_ttl'=>120,
            'default_title'=>$txt['betterportal_block_forum_stats'], 'default_settings'=>array(),
        ),
    );
    // Better SMF integrations are discovered at runtime. The provider source file
    // and its installed setting must both be present, so preserved settings alone
    // never create a dead Page Builder option after a MOD has been removed.
    BetterPortal_RegisterBetterSmfBlocks($blocks);

    // Keep the public registry hook for third-party providers and future Better SMF
    // MODs that register their own blocks without Better Portal knowing about them.
    call_integration_hook('integrate_betterportal_blocks', array(&$blocks));
    return $blocks;
}

function BetterPortal_ProviderAvailable($setting, $source_file, $probe_function = '')
{
    global $modSettings, $sourcedir;

    if ($setting !== '' && empty($modSettings[$setting]))
        return false;

    $file = $sourcedir . '/' . $source_file;
    if (!is_file($file))
        return false;

    if ($probe_function !== '' && !function_exists($probe_function))
        require_once($file);

    return $probe_function === '' || function_exists($probe_function);
}

function BetterPortal_RegisterBetterSmfBlocks(&$blocks)
{
    global $txt;

    if (BetterPortal_ProviderAvailable('bg_enabled', 'BetterGallery.php', 'bg_album_content_accessible'))
    {
        $blocks['better_media_latest'] = array(
            'label'=>$txt['betterportal_block_better_media_latest'], 'description'=>$txt['betterportal_block_better_media_latest_desc'],
            'category'=>'better_smf', 'provider'=>'Better Gallery', 'render'=>'BetterPortal_RenderBetterMediaLatest', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_media_latest'], 'default_settings'=>array('limit'=>5,'display_style'=>'standard'),
            'settings_fields'=>array(
                'limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20),
                'display_style'=>array('type'=>'select','label'=>$txt['betterportal_block_display_style'],'options'=>array('standard'=>$txt['betterportal_display_standard'],'compact'=>$txt['betterportal_display_compact'])),
            ),
        );
        $blocks['better_media_albums'] = array(
            'label'=>$txt['betterportal_block_better_media_albums'], 'description'=>$txt['betterportal_block_better_media_albums_desc'],
            'category'=>'better_smf', 'provider'=>'Better Gallery', 'render'=>'BetterPortal_RenderBetterMediaAlbums', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_media_albums'], 'default_settings'=>array('limit'=>5,'display_style'=>'standard'),
            'settings_fields'=>array(
                'limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20),
                'display_style'=>array('type'=>'select','label'=>$txt['betterportal_block_display_style'],'options'=>array('standard'=>$txt['betterportal_display_standard'],'compact'=>$txt['betterportal_display_compact'])),
            ),
        );

        $blocks['better_media_browser'] = array(
            'label'=>$txt['betterportal_block_better_media_browser'], 'description'=>$txt['betterportal_block_better_media_browser_desc'],
            'category'=>'better_smf', 'provider'=>'Better Gallery', 'render'=>'BetterPortal_RenderBetterMediaBrowser', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_media_browser'],
            'default_settings'=>array('album_id'=>0,'limit'=>12,'display_style'=>'auto'),
            'settings_fields'=>array(
                'album_id'=>array('type'=>'select','label'=>$txt['betterportal_better_media_browser_album'],'options_callback'=>'BetterPortal_GetBetterMediaAlbumOptions'),
                'limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>40),
                'display_style'=>array('type'=>'select','label'=>$txt['betterportal_block_display_style'],'options'=>array(
                    'auto'=>$txt['betterportal_better_media_browser_auto'],
                    'interactive'=>$txt['betterportal_better_media_browser_interactive'],
                    'compact'=>$txt['betterportal_better_media_browser_compact'],
                )),
            ),
        );

        $blocks['better_gallery_selected_album'] = array(
            'label'=>$txt['betterportal_block_better_gallery_selected_album'], 'description'=>$txt['betterportal_block_better_gallery_selected_album_desc'],
            'category'=>'better_smf', 'provider'=>'Better Gallery', 'render'=>'BetterPortal_RenderBetterGallerySelectedAlbum', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_gallery_selected_album'],
            'default_settings'=>array('album_id'=>0,'limit'=>8),
            'settings_fields'=>array(
                'album_id'=>array('type'=>'select','label'=>$txt['betterportal_better_media_browser_album'],'options_callback'=>'BetterPortal_GetBetterMediaAlbumOptions'),
                'limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>24),
            ),
        );
    }

    if (BetterPortal_ProviderAvailable('better_reactions_enabled', 'BetterReactions.php', 'BetterReactions_GetMessageSummary'))
    {
        $blocks['better_reactions_popular'] = array(
            'label'=>$txt['betterportal_block_better_reactions_popular'], 'description'=>$txt['betterportal_block_better_reactions_popular_desc'],
            'category'=>'better_smf', 'provider'=>'Better Reactions', 'render'=>'BetterPortal_RenderBetterReactionsPopular', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_reactions_popular'], 'default_settings'=>array('limit'=>5),
            'settings_fields'=>array('limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20)),
        );
    }

    if (BetterPortal_ProviderAvailable('chat_enabled', 'ChatHandler.php', 'better_chat_support_is_staff'))
    {
        $blocks['better_chat_online_staff'] = array(
            'label'=>$txt['betterportal_block_better_chat_online_staff'], 'description'=>$txt['betterportal_block_better_chat_online_staff_desc'],
            'category'=>'better_smf', 'provider'=>'Better Chat', 'render'=>'BetterPortal_RenderBetterChatOnlineStaff', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_chat_online_staff'], 'default_settings'=>array('limit'=>8),
            'settings_fields'=>array('limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20)),
        );
    }

    // test33g+ Portal Rooms are deliberately separate from Better Chat's
    // floating Community/private chat surfaces. Better Portal only stores a
    // room reference and asks Better Chat to render and police the live room.
    if (BetterPortal_ProviderAvailable('chat_enabled', 'ChatHandler.php', 'better_chat_render_portal_room')
        && function_exists('better_chat_get_portal_rooms'))
    {
        $blocks['better_chat_portal_room'] = array(
            'label'=>$txt['betterportal_block_better_chat_portal_room'], 'description'=>$txt['betterportal_block_better_chat_portal_room_desc'],
            'category'=>'better_smf', 'provider'=>'Better Chat', 'render'=>'BetterPortal_RenderBetterChatPortalRoom', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_chat_portal_room'], 'default_settings'=>array('room_id'=>0),
            'settings_fields'=>array(
                'room_id'=>array('type'=>'select','label'=>$txt['betterportal_better_chat_room_select'],'options_callback'=>'BetterPortal_GetBetterChatPortalRoomOptions'),
            ),
        );
    }


    // dev30: Better Downloads provider blocks. Better Downloads remains the
    // authority for category visibility, download permission, files and counts.
    if (BetterPortal_ProviderAvailable('bd_enabled', 'BetterDownloads.php', 'better_downloads_get_latest'))
    {
        $common_count = array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>20);
        $blocks['better_downloads_latest'] = array(
            'label'=>$txt['betterportal_block_better_downloads_latest'], 'description'=>$txt['betterportal_block_better_downloads_latest_desc'],
            'category'=>'better_smf', 'provider'=>'Better Downloads', 'render'=>'BetterPortal_RenderBetterDownloadsLatest', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_downloads_latest'], 'default_settings'=>array('limit'=>5),
            'settings_fields'=>array('limit'=>$common_count),
        );
        $blocks['better_downloads_updated'] = array(
            'label'=>$txt['betterportal_block_better_downloads_updated'], 'description'=>$txt['betterportal_block_better_downloads_updated_desc'],
            'category'=>'better_smf', 'provider'=>'Better Downloads', 'render'=>'BetterPortal_RenderBetterDownloadsUpdated', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_downloads_updated'], 'default_settings'=>array('limit'=>5),
            'settings_fields'=>array('limit'=>$common_count),
        );
        $blocks['better_downloads_popular'] = array(
            'label'=>$txt['betterportal_block_better_downloads_popular'], 'description'=>$txt['betterportal_block_better_downloads_popular_desc'],
            'category'=>'better_smf', 'provider'=>'Better Downloads', 'render'=>'BetterPortal_RenderBetterDownloadsPopular', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_downloads_popular'], 'default_settings'=>array('limit'=>5),
            'settings_fields'=>array('limit'=>$common_count),
        );
        $blocks['better_downloads_categories'] = array(
            'label'=>$txt['betterportal_block_better_downloads_categories'], 'description'=>$txt['betterportal_block_better_downloads_categories_desc'],
            'category'=>'better_smf', 'provider'=>'Better Downloads', 'render'=>'BetterPortal_RenderBetterDownloadsCategories', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_downloads_categories'], 'default_settings'=>array(),
        );
        $blocks['better_downloads_featured'] = array(
            'label'=>$txt['betterportal_block_better_downloads_featured'], 'description'=>$txt['betterportal_block_better_downloads_featured_desc'],
            'category'=>'better_smf', 'provider'=>'Better Downloads', 'render'=>'BetterPortal_RenderBetterDownloadsFeatured', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_downloads_featured'], 'default_settings'=>array('download_id'=>0),
            'settings_fields'=>array('download_id'=>array('type'=>'select','label'=>$txt['betterportal_better_downloads_select'],'options_callback'=>'BetterPortal_GetBetterDownloadsOptions')),
        );
        $blocks['better_downloads_browser'] = array(
            'label'=>$txt['betterportal_block_better_downloads_browser'], 'description'=>$txt['betterportal_block_better_downloads_browser_desc'],
            'category'=>'better_smf', 'provider'=>'Better Downloads', 'render'=>'BetterPortal_RenderBetterDownloadsBrowser', 'cacheable'=>false, 'default_ttl'=>0,
            'default_title'=>$txt['betterportal_block_better_downloads_browser'], 'default_settings'=>array('category_id'=>0,'limit'=>24),
            'settings_fields'=>array(
                'category_id'=>array('type'=>'select','label'=>$txt['betterportal_better_downloads_category'],'options_callback'=>'BetterPortal_GetBetterDownloadsCategoryOptions'),
                'limit'=>array('type'=>'number','label'=>$txt['betterportal_block_item_count'],'min'=>1,'max'=>60),
            ),
        );
    }
}

function BetterPortal_RenderBetterMediaLatest($block)
{
    global $smcFunc, $scripturl, $user_info, $txt;

    if (!BetterPortal_ProviderAvailable('bg_enabled', 'BetterGallery.php', 'bg_album_content_accessible') || !bg_allowed('bg_view_gallery'))
        return false;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));
    $candidate_limit = min(80, max(20, $limit * 8));

    $request = $smcFunc['db_query']('', '
        SELECT m.id_media, m.id_album, m.media_type, m.title, m.created_at,
            a.title AS album_title, a.id_owner AS album_owner,
            a.discovery_visibility, a.access_mode, a.password_hash
        FROM {db_prefix}bg_media AS m
            INNER JOIN {db_prefix}bg_albums AS a ON (a.id_album = m.id_album)
        WHERE m.status = {string:ready}
            AND m.approved = {int:approved}
            AND a.approved = {int:approved}
        ORDER BY m.created_at DESC, m.id_media DESC
        LIMIT {int:limit}',
        array('ready'=>'ready', 'approved'=>1, 'limit'=>$candidate_limit)
    );

    $items = array();
    $is_admin = !empty($user_info['is_admin']) || bg_allowed('bg_moderate_gallery');
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $album = array(
            'id_album'=>(int)$row['id_album'], 'id_owner'=>(int)$row['album_owner'],
            'discovery_visibility'=>(int)$row['discovery_visibility'], 'access_mode'=>(int)$row['access_mode'],
            'password_hash'=>(string)$row['password_hash'],
        );
        if (!bg_portfolio_album_visible($album) || !bg_album_is_discoverable($album, (int)$user_info['id'], $is_admin) || !bg_album_content_accessible($album, (int)$user_info['id'], $is_admin))
            continue;
        $items[] = $row;
        if (count($items) >= $limit) break;
    }
    $smcFunc['db_free_result']($request);

    if (empty($items))
        return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_no_better_media'], ENT_QUOTES, 'UTF-8').'</p>');

    $display_style = (($settings['display_style'] ?? 'standard') === 'compact') ? ' compact' : '';
    $html = '<ul class="betterportal-list betterportal-media-list'.$display_style.'">';
    foreach ($items as $item)
    {
        $title = $item['title'] !== '' ? $item['title'] : $item['album_title'];
        $href = $scripturl . '?action=gallery;sa=album;album=' . (int)$item['id_album'];
        $thumb = $scripturl . '?action=gallery;sa=file;media=' . (int)$item['id_media'] . ';thumb=1';
        $html .= '<li><a class="betterportal-media-thumb" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'"><img src="'.htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8').'" loading="lazy" alt=""></a><div><a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</a><span class="smalltext">'.htmlspecialchars($item['album_title'], ENT_QUOTES, 'UTF-8').' &middot; '.htmlspecialchars(timeformat((int)$item['created_at']), ENT_QUOTES, 'UTF-8').'</span></div></li>';
    }
    $html .= '</ul>';
    return array('body'=>$html);
}

function BetterPortal_RenderBetterMediaAlbums($block)
{
    global $smcFunc, $scripturl, $user_info, $txt;

    if (!BetterPortal_ProviderAvailable('bg_enabled', 'BetterGallery.php', 'bg_album_content_accessible') || !bg_allowed('bg_view_gallery'))
        return false;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));
    $candidate_limit = min(80, max(20, $limit * 8));

    $request = $smcFunc['db_query']('', '
        SELECT a.id_album, a.id_owner, a.id_cover_media, a.title, a.discovery_visibility,
            a.access_mode, a.password_hash, a.num_items, a.updated_at, mem.real_name AS owner_name
        FROM {db_prefix}bg_albums AS a
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_owner)
        WHERE a.approved = {int:approved}
        ORDER BY a.updated_at DESC, a.id_album DESC
        LIMIT {int:limit}',
        array('approved'=>1, 'limit'=>$candidate_limit)
    );

    $items = array();
    $is_admin = !empty($user_info['is_admin']) || bg_allowed('bg_moderate_gallery');
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        if (!bg_portfolio_album_visible($row) || !bg_album_is_discoverable($row, (int)$user_info['id'], $is_admin) || !bg_album_content_accessible($row, (int)$user_info['id'], $is_admin))
            continue;
        $items[] = $row;
        if (count($items) >= $limit) break;
    }
    $smcFunc['db_free_result']($request);

    if (empty($items))
        return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_no_better_media_albums'], ENT_QUOTES, 'UTF-8').'</p>');

    $display_style = (($settings['display_style'] ?? 'standard') === 'compact') ? ' compact' : '';
    $html = '<ul class="betterportal-list betterportal-album-list'.$display_style.'">';
    foreach ($items as $item)
    {
        $href = $scripturl . '?action=gallery;sa=album;album=' . (int)$item['id_album'];
        $html .= '<li>';
        if (!empty($item['id_cover_media']))
        {
            $thumb = $scripturl . '?action=gallery;sa=file;media=' . (int)$item['id_cover_media'] . ';thumb=1';
            $html .= '<a class="betterportal-media-thumb" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'"><img src="'.htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8').'" loading="lazy" alt=""></a>';
        }
        $meta = array();
        if (!empty($item['owner_name'])) $meta[] = (string)$item['owner_name'];
        $meta[] = sprintf($txt['betterportal_media_items'], (int)$item['num_items']);
        $html .= '<div><a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8').'</a><span class="smalltext">'.htmlspecialchars(implode(' · ', $meta), ENT_QUOTES, 'UTF-8').'</span></div></li>';
    }
    $html .= '</ul>';
    return array('body'=>$html);
}


/**
 * Selected Better Gallery album.
 *
 * A focused landing-page/gallery block: the administrator chooses one album,
 * Portal renders its newest approved items as a compact responsive grid, and
 * each item opens through Better Gallery's native viewer/lightbox. Better
 * Gallery remains authoritative for album/media visibility and access.
 */
function BetterPortal_RenderBetterGallerySelectedAlbum($block)
{
    global $smcFunc, $scripturl, $user_info, $txt;

    if (!BetterPortal_ProviderAvailable('bg_enabled', 'BetterGallery.php', 'bg_album_content_accessible') || !bg_allowed('bg_view_gallery'))
        return false;

    $config = BetterPortal_DecodeSettings($block['settings']);
    $id_album = max(0, (int) ($config['album_id'] ?? 0));
    if ($id_album <= 0)
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_better_gallery_selected_album_needs_selection'], ENT_QUOTES, 'UTF-8') . '</p>');

    $request = $smcFunc['db_query']('', '
        SELECT a.id_album, a.id_owner, a.title, a.discovery_visibility, a.access_mode,
            a.password_hash, a.num_items
        FROM {db_prefix}bg_albums AS a
        WHERE a.id_album = {int:id_album} AND a.approved = {int:approved}
        LIMIT 1', array('id_album'=>$id_album, 'approved'=>1));
    $album = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (empty($album))
        return false;

    $is_admin = !empty($user_info['is_admin']) || bg_allowed('bg_moderate_gallery');
    if (!bg_portfolio_album_visible($album)
        || !bg_album_is_discoverable($album, (int) $user_info['id'], $is_admin)
        || !bg_album_content_accessible($album, (int) $user_info['id'], $is_admin))
        return false;

    $limit = max(1, min(24, (int) ($config['limit'] ?? 8)));
    $request = $smcFunc['db_query']('', '
        SELECT m.id_media, m.id_album, m.media_type, m.title, m.thumb_key, m.poster_key,
            m.created_at
        FROM {db_prefix}bg_media AS m
        WHERE m.id_album = {int:id_album}
            AND m.approved = {int:approved}
            AND m.status = {string:ready}
        ORDER BY m.created_at DESC, m.id_media DESC
        LIMIT {int:limit}', array('id_album'=>$id_album, 'approved'=>1, 'ready'=>'ready', 'limit'=>$limit));
    $items = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $items[] = $row;
    $smcFunc['db_free_result']($request);

    $album_url = $scripturl . '?action=gallery;sa=album;album=' . $id_album;
    $html = '<div class="betterportal-selected-gallery-album">';

    if (empty($items))
        $html .= '<p class="smalltext">' . htmlspecialchars($txt['betterportal_better_media_browser_empty'], ENT_QUOTES, 'UTF-8') . '</p>';
    else
    {
        $html .= '<div class="betterportal-selected-gallery-grid">';
        foreach ($items as $item)
        {
            $title = trim((string) $item['title']) !== '' ? (string) $item['title'] : (string) $album['title'];
            $file = $scripturl . '?action=gallery;sa=file;media=' . (int) $item['id_media'];
            $thumb = $file . ';thumb=1';
            $html .= '<a class="bg_media_open betterportal-selected-gallery-card" href="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '" data-media-id="' . (int) $item['id_media'] . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
            if ($item['media_type'] === 'image' || !empty($item['thumb_key']) || !empty($item['poster_key']))
                $html .= '<img src="' . htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') . '" loading="lazy" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
            else
                $html .= '<span class="betterportal-media-browser-placeholder">' . htmlspecialchars(strtoupper((string) $item['media_type']), ENT_QUOTES, 'UTF-8') . '</span>';
            $html .= '</a>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="betterportal-selected-gallery-actions"><a class="button" href="' . htmlspecialchars($album_url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($txt['betterportal_better_gallery_selected_album_open'], ENT_QUOTES, 'UTF-8') . '</a></div>';
    $html .= '</div>';

    BetterPortal_LoadBetterMediaViewerAssets();
    $html .= BetterPortal_BetterMediaLightboxMarkup();

    return array('body'=>$html);
}

/**
 * Interactive Better Gallery album browser.
 *
 * Narrow placement automatically becomes a compact preview. Wide placement can
 * expose Better Gallery's native viewer/lightbox, while album/media access stays
 * authoritative in Better Gallery. Portal stores only album/display settings.
 */
function BetterPortal_RenderBetterMediaBrowser($block)
{
    global $smcFunc, $scripturl, $user_info, $txt, $context, $settings;

    if (!BetterPortal_ProviderAvailable('bg_enabled', 'BetterGallery.php', 'bg_album_content_accessible') || !bg_allowed('bg_view_gallery'))
        return false;

    $config = BetterPortal_DecodeSettings($block['settings']);
    $id_album = max(0, (int) ($config['album_id'] ?? 0));
    if ($id_album <= 0)
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_better_media_browser_needs_selection'], ENT_QUOTES, 'UTF-8') . '</p>');

    $request = $smcFunc['db_query']('', '
        SELECT a.id_album, a.id_owner, a.title, a.description, a.discovery_visibility, a.access_mode,
            a.password_hash, a.num_items, a.allow_downloads, a.allow_comments, a.allow_ratings,
            mem.real_name AS owner_name
        FROM {db_prefix}bg_albums AS a
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_owner)
        WHERE a.id_album = {int:id_album} AND a.approved = {int:approved}
        LIMIT 1', array('id_album'=>$id_album, 'approved'=>1));
    $album = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (empty($album))
        return false;

    $is_admin = !empty($user_info['is_admin']) || bg_allowed('bg_moderate_gallery');
    if (!bg_portfolio_album_visible($album)
        || !bg_album_is_discoverable($album, (int) $user_info['id'], $is_admin)
        || !bg_album_content_accessible($album, (int) $user_info['id'], $is_admin))
        return false;

    $limit = max(1, min(40, (int) ($config['limit'] ?? 12)));
    $requested_style = (string) ($config['display_style'] ?? 'auto');
    $wide = BetterPortal_BlockColumnSupportsNative($block);
    $interactive = $requested_style === 'interactive' || ($requested_style === 'auto' && $wide);
    if (!$wide && $requested_style === 'interactive')
        $interactive = false;
    $compact = !$interactive;

    $request = $smcFunc['db_query']('', '
        SELECT m.id_media, m.id_album, m.media_type, m.title, m.description, m.mime_type,
            m.file_size, m.width, m.height, m.duration_ms, m.poster_key, m.thumb_key,
            m.num_views, m.num_downloads, m.created_at
        FROM {db_prefix}bg_media AS m
        WHERE m.id_album = {int:id_album}
            AND m.approved = {int:approved}
            AND m.status = {string:ready}
        ORDER BY m.created_at DESC, m.id_media DESC
        LIMIT {int:limit}', array('id_album'=>$id_album, 'approved'=>1, 'ready'=>'ready', 'limit'=>$limit));
    $items = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $items[] = $row;
    $smcFunc['db_free_result']($request);

    $album_url = $scripturl . '?action=gallery;sa=album;album=' . $id_album;
    $html = '<div class="betterportal-media-browser' . ($compact ? ' is-compact' : ' is-interactive') . '">';
    $html .= '<div class="betterportal-media-browser-head"><div><strong>' . htmlspecialchars($album['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
    $meta = array();
    if (!empty($album['owner_name'])) $meta[] = $album['owner_name'];
    $meta[] = sprintf($txt['betterportal_media_items'], (int) $album['num_items']);
    $html .= '<span class="smalltext">' . htmlspecialchars(implode(' · ', $meta), ENT_QUOTES, 'UTF-8') . '</span></div>';
    $html .= '<a class="button" href="' . htmlspecialchars($album_url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($txt['betterportal_better_media_browser_open_album'], ENT_QUOTES, 'UTF-8') . '</a></div>';

    if (empty($items))
        $html .= '<p class="smalltext">' . htmlspecialchars($txt['betterportal_better_media_browser_empty'], ENT_QUOTES, 'UTF-8') . '</p>';
    else
    {
        $html .= '<div class="betterportal-media-browser-grid">';
        foreach ($items as $item)
        {
            $title = trim((string) $item['title']) !== '' ? (string) $item['title'] : $album['title'];
            $file = $scripturl . '?action=gallery;sa=file;media=' . (int) $item['id_media'];
            $thumb = $file . ';thumb=1';
            $class = $interactive ? 'bg_media_open betterportal-media-browser-card' : 'betterportal-media-browser-card';
            $attrs = $interactive ? ' data-media-id="' . (int) $item['id_media'] . '"' : '';
            $href = $interactive ? $file : $album_url;
            $html .= '<a class="' . $class . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '>';
            if ($item['media_type'] === 'image' || !empty($item['thumb_key']) || !empty($item['poster_key']))
                $html .= '<img src="' . htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') . '" loading="lazy" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
            else
                $html .= '<span class="betterportal-media-browser-placeholder">' . htmlspecialchars(strtoupper((string) $item['media_type']), ENT_QUOTES, 'UTF-8') . '</span>';
            $html .= '<span class="betterportal-media-browser-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></a>';
        }
        $html .= '</div>';
    }

    if ($interactive)
    {
        BetterPortal_LoadBetterMediaViewerAssets();
        $html .= BetterPortal_BetterMediaLightboxMarkup();
    }
    elseif (!$wide)
        $html .= '<p class="smalltext betterportal-media-browser-hint">' . htmlspecialchars($txt['betterportal_better_media_browser_preview_hint'], ENT_QUOTES, 'UTF-8') . '</p>';

    $html .= '</div>';
    return array('body'=>$html);
}

function BetterPortal_LoadBetterMediaViewerAssets()
{
    global $context, $settings;
    if (function_exists('bg_load_better_media_language'))
        bg_load_better_media_language();
    if (empty($settings['default_theme_url']))
        return;
    if (!isset($context['html_headers'])) $context['html_headers'] = '';
    $css = $settings['default_theme_url'] . '/css/BetterGallery.css?bg=dev51m';
    $js = $settings['default_theme_url'] . '/scripts/BetterGalleryViewer.js?bg=dev51m';
    if (strpos($context['html_headers'], 'BetterGallery.css') === false)
        $context['html_headers'] .= "\n<link rel=\"stylesheet\" href=\"" . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . "\">";
    if (strpos($context['html_headers'], 'BetterGalleryViewer.js') === false)
        $context['html_headers'] .= "\n<script defer src=\"" . htmlspecialchars($js, ENT_QUOTES, 'UTF-8') . "\"></script>";
}

function BetterPortal_BetterMediaLightboxMarkup()
{
    global $context, $txt;
    static $rendered = false;
    if ($rendered) return '';
    $rendered = true;

    if (function_exists('bg_load_better_media_language'))
        bg_load_better_media_language();

    $e = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    $sv = $e($context['session_var'] ?? '');
    $sid = $e($context['session_id'] ?? '');
    return '<div id="bg_lightbox" class="bg_lightbox" hidden data-session-var="'.$sv.'" data-session-id="'.$sid.'">'
        . '<div class="bg_lightbox_backdrop" data-bg-close></div><div class="bg_lightbox_dialog" role="dialog" aria-modal="true" aria-labelledby="bg_lightbox_title">'
        . '<button class="bg_lightbox_close" type="button" data-bg-close aria-label="'.$e($txt['bg_close_viewer'] ?? 'Close').'">×</button>'
        . '<button class="bg_lightbox_nav bg_lightbox_prev" type="button" data-bg-prev aria-label="'.$e($txt['bg_previous_media'] ?? 'Previous').'">‹</button>'
        . '<div class="bg_lightbox_stage" id="bg_lightbox_stage"></div>'
        . '<button class="bg_lightbox_nav bg_lightbox_next" type="button" data-bg-next aria-label="'.$e($txt['bg_next_media'] ?? 'Next').'">›</button>'
        . '<aside class="bg_lightbox_info"><h3 id="bg_lightbox_title"></h3><p id="bg_lightbox_description"></p>'
        . '<div class="bg_lightbox_toolbar"><div class="bg_lightbox_actions">'
        . '<a id="bg_full_size_link" class="button bg_lightbox_action bg_icon_button" href="#" target="_blank" rel="noopener" hidden aria-label="'.$e($txt['bg_view_full_size'] ?? 'Full size').'">↗</a>'
        . '<a id="bg_download_link" class="button bg_lightbox_action bg_icon_button" href="#" hidden aria-label="'.$e($txt['bg_download_media'] ?? 'Download').'">↓</a></div>'
        . '<div class="bg_lightbox_member_actions"><button id="bg_lightbox_favorite" class="button bg_favorite_button bg_icon_button" type="button" aria-pressed="false" hidden>★</button>'
        . '<button id="bg_lightbox_report" class="button bg_report_button bg_icon_button" type="button" hidden>⚑</button></div>'
        . '<div id="bg_lightbox_like_wrap" class="bg_lightbox_like_wrap" hidden><button id="bg_lightbox_like" class="button bg_like_button bg_icon_button bg_icon_button_with_badge" type="button" aria-pressed="false"><span>♥</span><strong id="bg_like_count" class="bg_icon_badge">0</strong></button></div></div>'
        . '<div id="bg_lightbox_reaction_summary" class="bg_lightbox_reaction_summary" hidden></div>'
        . '<div id="bg_report_panel" class="bg_report_panel" hidden><form id="bg_report_form"><input id="bg_report_target_type" type="hidden" name="target_type" value="media"><input id="bg_report_comment_id" type="hidden" name="comment" value="0">'
        . '<select id="bg_report_reason" name="reason"><option value="spam">Spam</option><option value="abuse">Abuse</option><option value="copyright">Copyright</option><option value="inappropriate">Inappropriate</option><option value="other">Other</option></select><textarea id="bg_report_note" name="note" rows="3" maxlength="2000"></textarea><button class="button" type="submit">Submit</button><button id="bg_report_cancel" class="button" type="button">Cancel</button></form></div>'
        . '<div id="bg_comments_panel" class="bg_comments_panel" hidden><div id="bg_comments_list" class="bg_comments_list"></div><form id="bg_comment_form" class="bg_comment_form"><textarea id="bg_comment_body" name="body" rows="3" maxlength="2000"></textarea><button class="button" type="submit">'. $e($txt['bg_post_comment'] ?? 'Post comment') .'</button></form></div>'
        . '<div id="bg_lightbox_message" class="bg_lightbox_message" role="status" aria-live="polite"></div></aside></div></div>';
}

function BetterPortal_RenderBetterReactionsPopular($block)
{
    global $smcFunc, $scripturl, $txt;

    if (!BetterPortal_ProviderAvailable('better_reactions_enabled', 'BetterReactions.php', 'BetterReactions_GetMessageSummary'))
        return false;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));

    // Count Better Reactions and SMF native likes together, but keep SMF's
    // {query_see_board} filter in the ranking query so private-board activity
    // can never leak into a public Portal block.
    $request = $smcFunc['db_query']('', '
        SELECT m.id_msg, m.id_topic, m.subject, mem.real_name,
            COALESCE(br.total, 0) + COALESCE(ul.total, 0) AS reaction_total
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS total
                FROM {db_prefix}better_reactions
                WHERE content_type = {string:content_type}
                GROUP BY content_id
            ) AS br ON (br.content_id = m.id_msg)
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS total
                FROM {db_prefix}user_likes
                WHERE content_type = {string:content_type}
                GROUP BY content_id
            ) AS ul ON (ul.content_id = m.id_msg)
        WHERE {query_see_board}
            AND (COALESCE(br.total, 0) + COALESCE(ul.total, 0)) > 0
        ORDER BY reaction_total DESC, m.id_msg DESC
        LIMIT {int:limit}',
        array('content_type'=>'msg', 'limit'=>$limit)
    );

    $items = array();
    while ($row = $smcFunc['db_fetch_assoc']($request)) $items[] = $row;
    $smcFunc['db_free_result']($request);

    if (empty($items))
        return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_no_reacted_posts'], ENT_QUOTES, 'UTF-8').'</p>');

    $html = '<ul class="betterportal-list betterportal-reaction-list">';
    foreach ($items as $item)
    {
        $href = $scripturl . '?topic=' . (int)$item['id_topic'] . '.msg' . (int)$item['id_msg'] . '#msg' . (int)$item['id_msg'];
        $subject = $item['subject'] !== '' ? $item['subject'] : $txt['betterportal_untitled_topic'];
        $who = !empty($item['real_name']) ? (string)$item['real_name'] : $txt['betterportal_unknown_member'];
        $html .= '<li><a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($subject, ENT_QUOTES, 'UTF-8').'</a><span class="smalltext">'.htmlspecialchars($who, ENT_QUOTES, 'UTF-8').' &middot; '.sprintf(htmlspecialchars($txt['betterportal_reaction_count'], ENT_QUOTES, 'UTF-8'), (int)$item['reaction_total']).'</span></li>';
    }
    $html .= '</ul>';
    return array('body'=>$html);
}


/** Better Downloads selectors and renderers (dev30). */
function BetterPortal_GetBetterDownloadsCategoryOptions()
{
    global $txt;
    if (!BetterPortal_ProviderAvailable('bd_enabled', 'BetterDownloads.php', 'better_downloads_get_categories')) return array(0=>$txt['betterportal_better_downloads_all']);
    $out=array(0=>$txt['betterportal_better_downloads_all']);
    foreach ((array)better_downloads_get_categories() as $id=>$cat) $out[(int)$id]=(string)$cat['name'];
    return $out;
}
function BetterPortal_GetBetterDownloadsOptions()
{
    global $txt;
    if (!BetterPortal_ProviderAvailable('bd_enabled', 'BetterDownloads.php', 'better_downloads_get_latest')) return array(0=>$txt['betterportal_better_downloads_choose']);
    $out=array(0=>$txt['betterportal_better_downloads_choose']);
    foreach ((array)better_downloads_get_latest(100,0) as $item) $out[(int)$item['id_download']]=(string)$item['title'].(!empty($item['version'])?' — v'.$item['version']:'');
    return $out;
}
function BetterPortal_BetterDownloadsScreenshot($item)
{
    global $scripturl;
    $raw=(string)($item['screenshot_url']??'');
    if ($raw==='') return '';
    return strpos($raw,'local:')===0 ? $scripturl.'?action=downloads;sa=screenshot;id='.(int)$item['id_download'] : $raw;
}
function BetterPortal_BetterDownloadsCard($item,$featured=false,$stacked=false,$interactive_uid='')
{
    global $scripturl,$txt;
    $id=(int)$item['id_download'];
    $shot=BetterPortal_BetterDownloadsScreenshot($item);
    $details=$scripturl.'?action=downloads;sa=item;id='.$id;
    $download=$scripturl.'?action=downloads;sa=download;id='.$id;
    $open_attr=$interactive_uid!==''?' data-bpdl-open="'.$id.'"':'';
    $html='<article class="betterportal-download-card'.($featured?' featured':'').($stacked?' stacked':'').'">';
    if ($shot!=='') $html.='<a class="betterportal-download-cover" href="'.htmlspecialchars($details,ENT_QUOTES,'UTF-8').'"'.$open_attr.'><img src="'.htmlspecialchars($shot,ENT_QUOTES,'UTF-8').'" loading="lazy" alt=""></a>';
    $html.='<div class="betterportal-download-copy"><div class="betterportal-download-heading"><a href="'.htmlspecialchars($details,ENT_QUOTES,'UTF-8').'"'.$open_attr.'>'.htmlspecialchars((string)$item['title'],ENT_QUOTES,'UTF-8').'</a>';
    if (!empty($item['version'])) $html.='<span class="betterportal-download-badge">v'.htmlspecialchars((string)$item['version'],ENT_QUOTES,'UTF-8').'</span>';
    $html.='</div><div class="smalltext betterportal-download-meta">'.htmlspecialchars((string)($item['category_name']??''),ENT_QUOTES,'UTF-8');
    if (!empty($item['smf_compatibility'])) $html.=' &middot; SMF '.htmlspecialchars((string)$item['smf_compatibility'],ENT_QUOTES,'UTF-8');
    $html.=' &middot; '.(int)($item['num_downloads']??0).' '.htmlspecialchars($txt['betterportal_better_downloads_downloads'],ENT_QUOTES,'UTF-8').'</div>';
    if ($featured && !empty($item['description'])) $html.='<p>'.nl2br(htmlspecialchars((string)$item['description'],ENT_QUOTES,'UTF-8')).'</p>';
    $html.='<div class="betterportal-download-actions"><a class="button" href="'.htmlspecialchars($details,ENT_QUOTES,'UTF-8').'"'.$open_attr.'>'.htmlspecialchars($txt['betterportal_better_downloads_details'],ENT_QUOTES,'UTF-8').'</a>';
    if (function_exists('better_downloads_can_download') && better_downloads_can_download($id)) $html.='<a class="button" href="'.htmlspecialchars($download,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($txt['betterportal_better_downloads_download'],ENT_QUOTES,'UTF-8').'</a>';
    $html.='</div></div></article>';
    return $html;
}

function BetterPortal_BetterDownloadsDetail($item,$uid)
{
    global $scripturl,$txt;
    $id=(int)$item['id_download'];
    if (function_exists('better_downloads_get_download')) {
        $full=better_downloads_get_download($id);
        if ($full) $item=$full;
    }
    $shots=function_exists('better_downloads_get_screenshots')?(array)better_downloads_get_screenshots($id):array();
    if (!$shots) {
        $fallback=BetterPortal_BetterDownloadsScreenshot($item);
        if ($fallback!=='') $shots[]=array('src'=>$fallback,'is_primary'=>1,'id_screenshot'=>0);
    }
    $details=$scripturl.'?action=downloads;sa=item;id='.$id;
    $download=$scripturl.'?action=downloads;sa=download;id='.$id;
    $hero='';
    if ($shots) {
        foreach ($shots as $shot) if (!empty($shot['is_primary']) && !empty($shot['src'])) {$hero=(string)$shot['src'];break;}
        if ($hero==='' && !empty($shots[0]['src'])) $hero=(string)$shots[0]['src'];
    }
    $html='<section class="betterportal-download-detail" data-bpdl-detail="'.$id.'" hidden>';
    $html.='<div class="betterportal-download-detail-layout">';
    if ($hero!=='') {
        $html.='<div class="betterportal-download-gallery"><div class="betterportal-download-hero"><img data-bpdl-hero src="'.htmlspecialchars($hero,ENT_QUOTES,'UTF-8').'" alt=""></div>';
        if (count($shots)>1) {
            $html.='<div class="betterportal-download-thumbs" aria-label="'.htmlspecialchars($txt['betterportal_better_downloads_screenshots'],ENT_QUOTES,'UTF-8').'">';
            foreach($shots as $shot) if(!empty($shot['src'])) $html.='<button type="button" data-bpdl-shot="'.htmlspecialchars((string)$shot['src'],ENT_QUOTES,'UTF-8').'" aria-label="'.htmlspecialchars($txt['betterportal_better_downloads_show_screenshot'],ENT_QUOTES,'UTF-8').'"><img src="'.htmlspecialchars((string)$shot['src'],ENT_QUOTES,'UTF-8').'" loading="lazy" alt=""></button>';
            $html.='</div>';
        }
        $html.='</div>';
    }
    $html.='<div class="betterportal-download-detail-copy"><div class="betterportal-download-detail-heading"><h3>'.htmlspecialchars((string)$item['title'],ENT_QUOTES,'UTF-8').'</h3>';
    if (!empty($item['version'])) $html.='<span class="betterportal-download-badge">v'.htmlspecialchars((string)$item['version'],ENT_QUOTES,'UTF-8').'</span>';
    $html.='</div><div class="smalltext betterportal-download-meta">'.htmlspecialchars((string)($item['category_name']??''),ENT_QUOTES,'UTF-8');
    if (!empty($item['package_type'])) $html.=' &middot; '.htmlspecialchars(ucwords(str_replace('_',' ',(string)$item['package_type'])),ENT_QUOTES,'UTF-8');
    if (!empty($item['smf_compatibility'])) $html.=' &middot; SMF '.htmlspecialchars((string)$item['smf_compatibility'],ENT_QUOTES,'UTF-8');
    $html.=' &middot; '.(int)($item['num_downloads']??0).' '.htmlspecialchars($txt['betterportal_better_downloads_downloads'],ENT_QUOTES,'UTF-8').'</div>';
    if (!empty($item['description'])) $html.='<div class="betterportal-download-description">'.nl2br(htmlspecialchars((string)$item['description'],ENT_QUOTES,'UTF-8')).'</div>';
    if (!empty($item['changelog'])) $html.='<div class="betterportal-download-section"><strong>'.htmlspecialchars($txt['betterportal_better_downloads_changelog'],ENT_QUOTES,'UTF-8').'</strong><div>'.nl2br(htmlspecialchars((string)$item['changelog'],ENT_QUOTES,'UTF-8')).'</div></div>';
    $html.='<div class="betterportal-download-actions">';
    if (function_exists('better_downloads_can_download') && better_downloads_can_download($id)) $html.='<a class="button" href="'.htmlspecialchars($download,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($txt['betterportal_better_downloads_download'],ENT_QUOTES,'UTF-8').'</a>';
    if (!empty($item['support_url']) && filter_var($item['support_url'],FILTER_VALIDATE_URL)) $html.='<a class="button" href="'.htmlspecialchars((string)$item['support_url'],ENT_QUOTES,'UTF-8').'" target="_blank" rel="noopener">'.htmlspecialchars($txt['betterportal_better_downloads_support'],ENT_QUOTES,'UTF-8').'</a>';
    $html.='<a class="button" href="'.htmlspecialchars($details,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($txt['betterportal_better_downloads_open_page'],ENT_QUOTES,'UTF-8').'</a></div>';
    $html.='</div></div></section>';
    return $html;
}

function BetterPortal_RenderBetterDownloadsCollection($block,$mode)
{
    global $txt;
    if (!BetterPortal_ProviderAvailable('bd_enabled','BetterDownloads.php','better_downloads_get_latest')) return false;
    $settings=BetterPortal_DecodeSettings($block['settings']); $limit=max(1,min(20,(int)($settings['limit']??5)));
    if ($mode==='popular') $items=better_downloads_get_popular($limit,0); elseif ($mode==='updated') $items=better_downloads_get_recently_updated($limit,0); else $items=better_downloads_get_latest($limit,0);
    if (!$items) return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_better_downloads_empty'],ENT_QUOTES,'UTF-8').'</p>');
    $stacked = !BetterPortal_BlockColumnSupportsNative($block);
    $html='<div class="betterportal-download-list'.($stacked?' stacked':'').'">'; foreach($items as $item) $html.=BetterPortal_BetterDownloadsCard($item,false,$stacked); $html.='</div>'; return array('body'=>$html);
}
function BetterPortal_RenderBetterDownloadsLatest($block){return BetterPortal_RenderBetterDownloadsCollection($block,'latest');}
function BetterPortal_RenderBetterDownloadsUpdated($block){return BetterPortal_RenderBetterDownloadsCollection($block,'updated');}
function BetterPortal_RenderBetterDownloadsPopular($block){return BetterPortal_RenderBetterDownloadsCollection($block,'popular');}
function BetterPortal_RenderBetterDownloadsCategories($block)
{
    global $scripturl,$txt;
    if (!BetterPortal_ProviderAvailable('bd_enabled','BetterDownloads.php','better_downloads_get_categories')) return false;
    $cats=(array)better_downloads_get_categories(); if(!$cats) return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_better_downloads_no_categories'],ENT_QUOTES,'UTF-8').'</p>');
    $counts=function_exists('better_downloads_get_category_counts')?(array)better_downloads_get_category_counts():array();
    if(!$counts){$all=function_exists('better_downloads_get_latest')?better_downloads_get_latest(250,0):array();foreach($all as $d){$c=(int)$d['id_category'];$counts[$c]=($counts[$c]??0)+1;}}
    $html='<div class="betterportal-download-categories">'; foreach($cats as $id=>$cat){$html.='<a href="'.$scripturl.'?action=downloads;cat='.(int)$id.'"><strong>'.htmlspecialchars($cat['name'],ENT_QUOTES,'UTF-8').'</strong>'; if(!empty($cat['description']))$html.='<span>'.htmlspecialchars($cat['description'],ENT_QUOTES,'UTF-8').'</span>'; $html.='<small>'.(int)($counts[(int)$id]??0).' '.htmlspecialchars($txt['betterportal_better_downloads_downloads'],ENT_QUOTES,'UTF-8').'</small></a>'; } $html.='</div>'; return array('body'=>$html);
}
function BetterPortal_RenderBetterDownloadsFeatured($block)
{
    global $txt;
    if (!BetterPortal_ProviderAvailable('bd_enabled','BetterDownloads.php','better_downloads_get_latest')) return false;
    $settings=BetterPortal_DecodeSettings($block['settings']); $id=(int)($settings['download_id']??0); if($id<=0)return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_better_downloads_needs_selection'],ENT_QUOTES,'UTF-8').'</p>');
    $item=false; if(function_exists('better_downloads_get_download'))$item=better_downloads_get_download($id); if(!$item)foreach((array)better_downloads_get_latest(100,0) as $candidate)if((int)$candidate['id_download']===$id){$item=$candidate;break;} if(!$item)return false;
    $stacked = !BetterPortal_BlockColumnSupportsNative($block);
    return array('body'=>BetterPortal_BetterDownloadsCard($item,true,$stacked));
}
function BetterPortal_BetterDownloadsSubmitForm($block,$uid,$options,$message='')
{
    global $context,$txt;
    $h=function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $cats=!empty($options['categories'])?(array)$options['categories']:array();
    $types=!empty($options['package_types'])?(array)$options['package_types']:array('other'=>'Other');
    $html='<section class="betterportal-download-submit" data-bpdl-submit hidden>';
    if($message!=='')$html.='<div class="betterportal-download-submit-result">'.$h($message).'</div>';
    $html.='<div class="betterportal-download-submit-intro"><strong>'.$h($txt['betterportal_better_downloads_add']).'</strong><span>'.$h(!empty($options['require_approval'])?$txt['betterportal_better_downloads_submit_pending_help']:$txt['betterportal_better_downloads_submit_live_help']).'</span></div>';
    $html.='<form method="post" enctype="multipart/form-data" class="betterportal-download-submit-form">';
    $html.='<input type="hidden" name="bpdl_submit_download" value="1"><input type="hidden" name="bpdl_submit_block" value="'.(int)$block['id_block'].'">';
    if(!empty($context['session_var']))$html.='<input type="hidden" name="'.$h($context['session_var']).'" value="'.$h($context['session_id']).'">';
    $html.='<div class="betterportal-download-submit-grid">';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_title']).'</span><input type="text" name="title" required maxlength="255"></label>';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_category']).'</span><select name="id_category" required><option value="">'.$h($txt['betterportal_better_downloads_submit_choose_category']).'</option>'; foreach($cats as $id=>$name)$html.='<option value="'.(int)$id.'">'.$h($name).'</option>'; $html.='</select></label>';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_version']).'</span><input type="text" name="version" maxlength="80" placeholder="1.0.0"></label>';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_type']).'</span><select name="package_type">'; foreach($types as $key=>$name)$html.='<option value="'.$h($key).'">'.$h($name).'</option>'; $html.='</select></label>';
    $html.='<label class="wide"><span>'.$h($txt['betterportal_better_downloads_submit_description']).'</span><textarea name="description" rows="5" required></textarea></label>';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_compat']).'</span><input type="text" name="smf_compatibility" placeholder="SMF 2.1.x"></label>';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_support']).'</span><input type="url" name="support_url" placeholder="https://"></label>';
    $html.='<label class="wide"><span>'.$h($txt['betterportal_better_downloads_submit_changelog']).'</span><textarea name="changelog" rows="4"></textarea></label>';
    $html.='<div class="betterportal-download-submit-section wide"><strong>'.$h($txt['betterportal_better_downloads_submit_file']).'</strong><div class="betterportal-download-submit-grid">';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_upload']).'</span><input type="file" name="download_file"><small>'.$h(sprintf($txt['betterportal_better_downloads_submit_file_help'],(string)($options['allowed_extensions']??''),(int)($options['max_file_mb']??10))).'</small></label>';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_external']).'</span><input type="url" name="external_url" placeholder="https://"><small>'.$h($txt['betterportal_better_downloads_submit_either']).'</small></label></div></div>';
    $html.='<div class="betterportal-download-submit-section wide"><strong>'.$h($txt['betterportal_better_downloads_submit_screenshots']).'</strong><div class="betterportal-download-submit-grid">';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_screenshot_upload']).'</span><input type="file" name="screenshot_files[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple></label>';
    $html.='<label><span>'.$h($txt['betterportal_better_downloads_submit_screenshot_urls']).'</span><textarea name="screenshot_urls" rows="3" placeholder="https://example.com/image.jpg"></textarea></label></div></div>';
    $html.='</div><div class="betterportal-download-submit-actions"><button class="button" type="submit">'.$h($txt['betterportal_better_downloads_submit_button']).'</button><button class="button" type="button" data-bpdl-cancel>'.$h($txt['betterportal_better_downloads_cancel']).'</button></div></form></section>';
    return $html;
}

function BetterPortal_RenderBetterDownloadsBrowser($block)
{
    global $txt;
    if (!BetterPortal_ProviderAvailable('bd_enabled','BetterDownloads.php','better_downloads_get_latest')) return false;
    $settings=BetterPortal_DecodeSettings($block['settings']); $cat=(int)($settings['category_id']??0); $limit=max(1,min(60,(int)($settings['limit']??24)));
    $cats=(array)better_downloads_get_categories();
    $interactive=function_exists('better_downloads_get_download')&&function_exists('better_downloads_get_screenshots');
    $can_submit=function_exists('better_downloads_can_submit')&&better_downloads_can_submit();
    $submit_options=$can_submit&&function_exists('better_downloads_get_submission_options')?better_downloads_get_submission_options():false;
    $submit_result='';
    if($can_submit && !empty($_POST['bpdl_submit_download']) && (int)($_POST['bpdl_submit_block']??0)===(int)$block['id_block'] && function_exists('better_downloads_submit_download')){
        $result=better_downloads_submit_download($_POST,$_FILES);
        if(!empty($result['pending']))$submit_result=$txt['betterportal_better_downloads_submit_success_pending'];
        else $submit_result=$txt['betterportal_better_downloads_submit_success_live'];
    }
    $items=better_downloads_get_recently_updated($limit,$cat);
    $uid='bpdl'.(int)$block['id_block']; $stacked=!BetterPortal_BlockColumnSupportsNative($block);
    $html='<div class="betterportal-download-browser'.($interactive?' interactive':' legacy').($stacked?' narrow':' wide').'" id="'.$uid.'">';
    if($submit_result!=='')$html.='<div class="betterportal-download-submit-result">'.htmlspecialchars($submit_result,ENT_QUOTES,'UTF-8').'</div>';
    $html.='<div data-bpdl-list>';
    if($cat===0 && $cats){$html.='<div class="betterportal-download-filter" role="tablist" aria-label="'.htmlspecialchars($txt['betterportal_better_downloads_categories_label'],ENT_QUOTES,'UTF-8').'"><button type="button" class="active" data-cat="0">'.htmlspecialchars($txt['betterportal_better_downloads_all'],ENT_QUOTES,'UTF-8').'</button>';foreach($cats as $id=>$c)$html.='<button type="button" data-cat="'.(int)$id.'">'.htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8').'</button>';$html.='</div>';}
    if($items){$html.='<div class="betterportal-download-grid'.($stacked?' stacked':'').'">'; foreach($items as $item){$html.='<div data-download-cat="'.(int)$item['id_category'].'">'.BetterPortal_BetterDownloadsCard($item,false,$stacked,$interactive?$uid:'').'</div>';} $html.='</div>';}
    else $html.='<div class="betterportal-download-empty">'.htmlspecialchars($txt['betterportal_better_downloads_empty'],ENT_QUOTES,'UTF-8').'</div>';
    $html.='</div>';
    if($interactive) foreach($items as $item)$html.=BetterPortal_BetterDownloadsDetail($item,$uid);
    if($can_submit && $submit_options)$html.=BetterPortal_BetterDownloadsSubmitForm($block,$uid,$submit_options);
    if(!$interactive)$html.='<p class="smalltext betterportal-download-browser-upgrade">'.htmlspecialchars($txt['betterportal_better_downloads_browser_upgrade'],ENT_QUOTES,'UTF-8').'</p>';
    $html.='</div>';
    $html.='<script>(function(){var r=document.getElementById('.json_encode($uid).');if(!r||r.getAttribute("data-ready")==="1")return;r.setAttribute("data-ready","1");var list=r.querySelector("[data-bpdl-list]"),form=r.querySelector("[data-bpdl-submit]"),filter=r.querySelectorAll(".betterportal-download-filter button"),cards=r.querySelectorAll("[data-download-cat]"),details=r.querySelectorAll("[data-bpdl-detail]"),section=r.closest?r.closest(".betterportal-block"):null,back=section?section.querySelector("[data-bpdl-back]"):null,add=section?section.querySelector("[data-bpdl-add]"):null,cancel=r.querySelector("[data-bpdl-cancel]");function resetViews(){for(var i=0;i<details.length;i++)details[i].hidden=true;if(form)form.hidden=true;}function showList(){resetViews();if(list)list.hidden=false;if(back)back.hidden=true;if(add)add.hidden=false;}function showForm(){resetViews();if(list)list.hidden=true;if(form)form.hidden=false;if(back)back.hidden=false;if(add)add.hidden=true;var top=section||r;if(top&&top.scrollIntoView)top.scrollIntoView({block:"start",behavior:"smooth"});}function openDetail(id){resetViews();var found=false;for(var i=0;i<details.length;i++){var yes=details[i].getAttribute("data-bpdl-detail")===String(id);details[i].hidden=!yes;if(yes)found=true;}if(found){if(list)list.hidden=true;if(back)back.hidden=false;if(add)add.hidden=true;var top=section||r;if(top&&top.scrollIntoView)top.scrollIntoView({block:"start",behavior:"smooth"});}return found;}for(var i=0;i<filter.length;i++)filter[i].addEventListener("click",function(){var x=this.getAttribute("data-cat");for(var j=0;j<filter.length;j++)filter[j].classList.remove("active");this.classList.add("active");for(var k=0;k<cards.length;k++)cards[k].style.display=(x==="0"||cards[k].getAttribute("data-download-cat")===x)?"":"none";});var openers=r.querySelectorAll("[data-bpdl-open]");for(var i=0;i<openers.length;i++)openers[i].addEventListener("click",function(e){if(openDetail(this.getAttribute("data-bpdl-open")))e.preventDefault();});var shots=r.querySelectorAll("[data-bpdl-shot]");for(var i=0;i<shots.length;i++)shots[i].addEventListener("click",function(){var detail=this.closest("[data-bpdl-detail]"),hero=detail?detail.querySelector("[data-bpdl-hero]"):null;if(hero)hero.src=this.getAttribute("data-bpdl-shot");});if(back)back.addEventListener("click",showList);if(add)add.addEventListener("click",showForm);if(cancel)cancel.addEventListener("click",showList);showList();})();</script>';
    // dev32b: contextual browser controls belong in the content area, not the
    // blue title bar. Keep them compact and icon-only while preserving
    // accessible labels/tooltips for mouse, keyboard and assistive-tech users.
    $body_action='';
    if($interactive){
        $back_label=htmlspecialchars($txt['betterportal_better_downloads_back'],ENT_QUOTES,'UTF-8');
        $body_action.='<button type="button" class="betterportal-download-context-action betterportal-download-back" data-bpdl-back hidden title="'.$back_label.'" aria-label="'.$back_label.'"><span aria-hidden="true">&#8249;</span></button>';
    }
    if($can_submit){
        $add_label=htmlspecialchars($txt['betterportal_better_downloads_add'],ENT_QUOTES,'UTF-8');
        $body_action.='<button type="button" class="betterportal-download-context-action betterportal-download-add" data-bpdl-add title="'.$add_label.'" aria-label="'.$add_label.'"><span aria-hidden="true">&#43;</span></button>';
    }
    return array('body'=>$html,'body_action'=>$body_action);
}

/**
 * Render a Better Chat Portal Room inside a normal Better Portal block.
 * Better Chat remains authoritative for room access, messages, restrictions,
 * AJAX refresh and sending. A removed/inaccessible room simply disappears.
 */
function BetterPortal_RenderBetterChatPortalRoom($block)
{
    global $txt;

    if (!BetterPortal_ProviderAvailable('chat_enabled', 'ChatHandler.php', 'better_chat_render_portal_room')
        || !function_exists('better_chat_portal_room_get')
        || !function_exists('better_chat_portal_room_can_access'))
        return false;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $id_room = max(0, (int) ($settings['room_id'] ?? 0));
    if ($id_room <= 0)
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_better_chat_room_needs_selection'], ENT_QUOTES, 'UTF-8') . '</p>');

    $room = better_chat_portal_room_get($id_room);
    if (!$room || !better_chat_portal_room_can_access($room))
        return false;

    $html = better_chat_render_portal_room($id_room, array('limit' => 50));
    if ($html === '')
        return false;

    return array('body' => $html);
}

function BetterPortal_RenderBetterChatOnlineStaff($block)
{
    global $smcFunc, $scripturl, $modSettings, $txt;

    if (!BetterPortal_ProviderAvailable('chat_enabled', 'ChatHandler.php', 'better_chat_support_is_staff'))
        return false;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $limit = max(1, min(20, (int) ($settings['limit'] ?? 8)));
    $allowed = !empty($modSettings['chat_support_staff_groups']) ? preg_split('/\s*,\s*/', (string)$modSettings['chat_support_staff_groups']) : array('1');
    $allowed = array_values(array_unique(array_filter(array_map('intval', $allowed))));
    if (!in_array(1, $allowed, true)) $allowed[] = 1;

    $request = $smcFunc['db_query']('', '
        SELECT mem.id_member, mem.real_name, mem.id_group, mem.additional_groups, MAX(lo.log_time) AS last_seen
        FROM {db_prefix}log_online AS lo
            INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
            INNER JOIN {db_prefix}chat_support_staff AS css ON (css.id_member = lo.id_member AND css.on_duty = {int:on_duty})
        WHERE lo.id_member > 0
            AND lo.log_time >= {int:cutoff}
        GROUP BY mem.id_member, mem.real_name, mem.id_group, mem.additional_groups
        ORDER BY last_seen DESC
        LIMIT 100', array('cutoff'=>time()-900, 'on_duty'=>1)
    );

    $staff = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $groups = array((int)$row['id_group']);
        if (!empty($row['additional_groups']))
            $groups = array_merge($groups, array_map('intval', explode(',', (string)$row['additional_groups'])));
        if (empty(array_intersect($allowed, $groups))) continue;
        $staff[] = $row;
        if (count($staff) >= $limit) break;
    }
    $smcFunc['db_free_result']($request);

    if (empty($staff))
        return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_no_chat_staff_online'], ENT_QUOTES, 'UTF-8').'</p>');

    $html = '<ul class="betterportal-chat-staff">';
    foreach ($staff as $member)
        $html .= '<li><span class="betterportal-online-dot" aria-hidden="true"></span><a href="'.$scripturl.'?action=profile;u='.(int)$member['id_member'].'">'.htmlspecialchars($member['real_name'], ENT_QUOTES, 'UTF-8').'</a></li>';
    $html .= '</ul>';
    return array('body'=>$html);
}

/**
 * Dynamic Poll block.
 *
 * Polls remain native SMF polls attached to topics. Better Portal stores only
 * the selected topic reference (or "latest") and renders a live voting form
 * against SMF's action=vote controller. Permissions, guest-vote rules, result
 * visibility, expiration, locking and the actual vote write stay authoritative
 * in SMF.
 */
function BetterPortal_RenderDynamicPoll($block)
{
    global $smcFunc, $scripturl, $modSettings, $txt, $user_info, $sourcedir, $context;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $selection = isset($settings['poll_topic']) ? (string) $settings['poll_topic'] : 'latest';
    $topic_id = $selection === 'latest' ? 0 : max(0, (int) $selection);
    $approval_where = !empty($modSettings['postmod_active']) ? '
            AND t.approved = {int:approved}
            AND m.approved = {int:approved}' : '';

    $where = $topic_id > 0 ? 't.id_topic = {int:id_topic}' : '(p.expire_time = {int:no_expire} OR p.expire_time >= {int:now}) AND p.voting_locked = {int:unlocked}';
    $params = array('id_topic' => $topic_id, 'approved' => 1, 'no_expire' => 0, 'now' => time(), 'unlocked' => 0, 'no_poll' => 0);
    $request = $smcFunc['db_query']('', '
        SELECT t.id_topic, t.id_board, t.id_poll, m.subject, b.name AS board_name,
            p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes,
            p.change_vote, p.guest_vote, p.num_guest_voters, p.reset_poll
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
        WHERE t.id_poll != {int:no_poll}
            AND ' . $where . '
            AND {query_see_board}' . $approval_where . '
        ORDER BY t.id_last_msg DESC
        LIMIT 1', $params);
    $poll = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (empty($poll))
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_dynamic_poll_none'], ENT_QUOTES, 'UTF-8') . '</p>');

    censorText($poll['subject']);
    censorText($poll['question']);

    // Match SMF's poll-vote permission as closely as possible without changing
    // the request-wide current board that the rest of the Portal page relies on.
    if (!function_exists('groupsAllowedTo'))
        require_once($sourcedir . '/Subs-Members.php');
    $permission = groupsAllowedTo('poll_vote', (int) $poll['id_board']);
    $effective_groups = !empty($user_info['is_guest']) ? array(-1) : BetterPortal_EffectiveMembergroupIds();
    $can_poll_vote = !empty(array_intersect($effective_groups, array_map('intval', $permission['allowed'])))
        && empty(array_intersect($effective_groups, array_map('intval', $permission['denied'])));

    $request = $smcFunc['db_query']('', '
        SELECT pc.id_choice, pc.label, pc.votes,
            COALESCE(lp.id_choice, -1) AS voted_this
        FROM {db_prefix}poll_choices AS pc
            LEFT JOIN {db_prefix}log_polls AS lp ON (
                lp.id_choice = pc.id_choice
                AND lp.id_poll = pc.id_poll
                AND lp.id_member = {int:current_member}
                AND lp.id_member != {int:guest_member})
        WHERE pc.id_poll = {int:id_poll}
        ORDER BY pc.id_choice', array(
            'current_member' => (int) $user_info['id'],
            'guest_member' => 0,
            'id_poll' => (int) $poll['id_poll'],
        ));

    $choices = array();
    $total_option_votes = 0;
    $has_voted = false;
    while ($choice = $smcFunc['db_fetch_assoc']($request))
    {
        censorText($choice['label']);
        $choice['id_choice'] = (int) $choice['id_choice'];
        $choice['votes'] = (int) $choice['votes'];
        $choice['voted_this'] = (int) $choice['voted_this'] !== -1;
        $total_option_votes += $choice['votes'];
        $has_voted = $has_voted || $choice['voted_this'];
        $choices[] = $choice;
    }
    $smcFunc['db_free_result']($request);

    // Guest vote history is stored in the same SMF cookie used by native polls.
    if (!empty($user_info['is_guest']) && !empty($poll['guest_vote']) && !empty($_COOKIE['guest_poll_vote'])
        && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote'])
        && strpos($_COOKIE['guest_poll_vote'], ';' . (int) $poll['id_poll'] . ',') !== false)
    {
        foreach (explode(';', $_COOKIE['guest_poll_vote']) as $guest_vote)
        {
            if ($guest_vote === '') continue;
            $parts = explode(',', $guest_vote);
            if ((int) ($parts[0] ?? 0) !== (int) $poll['id_poll']) continue;
            if ((int) ($parts[1] ?? 0) < (int) $poll['reset_poll']) break;
            $voted_ids = array_map('intval', array_slice($parts, 2));
            foreach ($choices as &$choice)
                $choice['voted_this'] = in_array($choice['id_choice'], $voted_ids, true);
            unset($choice);
            $has_voted = !empty($voted_ids);
            break;
        }
    }

    $request = $smcFunc['db_query']('', '
        SELECT COUNT(DISTINCT id_member)
        FROM {db_prefix}log_polls
        WHERE id_poll = {int:id_poll}
            AND id_member != {int:guest_member}', array('id_poll' => (int) $poll['id_poll'], 'guest_member' => 0));
    list ($member_voters) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);
    $total_voters = (int) $member_voters + (int) $poll['num_guest_voters'];

    $expired = !empty($poll['expire_time']) && (int) $poll['expire_time'] < time();
    $locked = !empty($poll['voting_locked']);
    $guest_allowed = empty($user_info['is_guest']) || !empty($poll['guest_vote']);
    $allow_vote = !$expired && !$locked && $guest_allowed && $can_poll_vote && !$has_voted;
    $allow_results = !empty($user_info['is_admin']) || (int) $poll['hide_results'] === 0
        || ((int) $poll['hide_results'] === 1 && $has_voted) || $expired;
    $show_results = !$allow_vote && $allow_results;

    $question = parse_bbc($poll['question']);
    $topic_url = $scripturl . '?topic=' . (int) $poll['id_topic'] . '.0';
    $body = '<div class="betterportal-poll">';
    $body .= '<div class="betterportal-poll-question">' . $question . '</div>';
    $body .= '<div class="smalltext betterportal-poll-source">' . htmlspecialchars($poll['board_name'], ENT_QUOTES, 'UTF-8') . ' · <a href="' . $topic_url . '">' . htmlspecialchars($poll['subject'], ENT_QUOTES, 'UTF-8') . '</a></div>';

    if ($allow_vote)
    {
        $action = $scripturl . '?action=vote;topic=' . (int) $poll['id_topic'] . '.0;start=0;bp_page=' . (int) ($block['id_page'] ?? 0) . ';bp_block=' . (int) ($block['id_block'] ?? 0);
        $body .= '<form class="betterportal-poll-form" action="' . $action . '" method="post">';
        $type = (int) $poll['max_votes'] > 1 ? 'checkbox' : 'radio';
        foreach ($choices as $choice)
            $body .= '<label class="betterportal-poll-choice"><input type="' . $type . '" name="options[]" value="' . $choice['id_choice'] . '"> <span>' . parse_bbc($choice['label']) . '</span></label>';
        if ((int) $poll['max_votes'] > 1)
            $body .= '<div class="smalltext">' . sprintf($txt['betterportal_dynamic_poll_pick_limit'], min(count($choices), (int) $poll['max_votes'])) . '</div>';
        $body .= '<div class="betterportal-poll-actions"><button type="submit" class="button">' . htmlspecialchars($txt['betterportal_dynamic_poll_vote'], ENT_QUOTES, 'UTF-8') . '</button></div>';
        $body .= '<input type="hidden" name="' . htmlspecialchars($context['session_var'], ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($context['session_id'], ENT_QUOTES, 'UTF-8') . '">';
        $body .= '</form>';
    }
    elseif ($show_results)
    {
        $divisor = max(1, (int) ((int) $poll['max_votes'] > 1 ? $total_voters : $total_option_votes));
        $body .= '<div class="betterportal-poll-results">';
        foreach ($choices as $choice)
        {
            $percent = round(($choice['votes'] * 100) / $divisor, 1);
            $body .= '<div class="betterportal-poll-result' . ($choice['voted_this'] ? ' betterportal-poll-result-voted' : '') . '">';
            $body .= '<div class="betterportal-poll-result-label"><span>' . parse_bbc($choice['label']) . '</span><span>' . $choice['votes'] . ' · ' . $percent . '%</span></div>';
            $body .= '<div class="betterportal-poll-bar"><span style="width:' . max(0, min(100, $percent)) . '%"></span></div>';
            $body .= '</div>';
        }
        $body .= '<div class="smalltext betterportal-poll-total">' . sprintf($txt['betterportal_dynamic_poll_total_voters'], $total_voters) . '</div>';
        if ($has_voted && !empty($poll['change_vote']) && !$expired && !$locked && empty($user_info['is_guest']) && $can_poll_vote)
        {
            $change_url = $scripturl . '?action=vote;topic=' . (int) $poll['id_topic'] . '.0;start=0;bp_page=' . (int) ($block['id_page'] ?? 0) . ';bp_block=' . (int) ($block['id_block'] ?? 0) . ';' . $context['session_var'] . '=' . $context['session_id'];
            $body .= '<div class="betterportal-poll-actions"><a class="button" href="' . $change_url . '">' . htmlspecialchars($txt['betterportal_dynamic_poll_change_vote'], ENT_QUOTES, 'UTF-8') . '</a></div>';
        }
        $body .= '</div>';
    }
    else
        $body .= '<p class="smalltext">' . htmlspecialchars($txt['betterportal_dynamic_poll_results_hidden'], ENT_QUOTES, 'UTF-8') . '</p>';

    if ($expired)
        $body .= '<div class="smalltext betterportal-poll-status">' . htmlspecialchars($txt['betterportal_dynamic_poll_expired'], ENT_QUOTES, 'UTF-8') . '</div>';
    elseif ($locked)
        $body .= '<div class="smalltext betterportal-poll-status">' . htmlspecialchars($txt['betterportal_dynamic_poll_locked'], ENT_QUOTES, 'UTF-8') . '</div>';

    $body .= '</div>';
    return array('body' => $body);
}


/**
 * Dynamic Topic block.
 *
 * The first eligible Topic block on a Portal page becomes SMF's native Display
 * view directly inside that block. This lightweight renderer is a safe fallback
 * when another native interactive block already owns the request.
 */
function BetterPortal_RenderDynamicTopic($block)
{
    global $smcFunc, $scripturl, $modSettings, $txt;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $id_topic = (int) ($settings['topic_id'] ?? 0);
    if ($id_topic <= 0)
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_dynamic_topic_needs_selection'], ENT_QUOTES, 'UTF-8') . '</p>');

    $approval_where = !empty($modSettings['postmod_active']) ? '
            AND t.approved = {int:approved}
            AND m.approved = {int:approved}' : '';
    $request = $smcFunc['db_query']('', '
        SELECT t.id_topic, t.id_board, t.num_replies, t.num_views,
            b.name AS board_name, m.subject, m.body, m.id_member, m.poster_name, m.poster_time
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
        WHERE t.id_topic = {int:id_topic}
            AND {query_see_board}' . $approval_where . '
        LIMIT 1', array('id_topic' => $id_topic, 'approved' => 1));
    $topic_row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (empty($topic_row))
        return false;

    censorText($topic_row['subject']);
    censorText($topic_row['body']);

    // A Topic block sitting in a narrow rail is preview-only. If this page also
    // has a native-capable Interactive Forum Board in the main/wide column and
    // the selected topic belongs to that board, opening the preview must hand
    // the topic to that board block instead of forcing native Display into the
    // narrow Topic block. This keeps the preview in place while the page's real
    // interactive host becomes the live topic surface.
    $target_block_id = (int) ($block['id_block'] ?? 0);
    $id_page = (int) ($block['id_page'] ?? 0);
    if ($id_page > 0)
    {
        $native_owner = BetterPortal_FindInteractiveNativeBlock($id_page);
        if (!empty($native_owner)
            && ($native_owner['native_kind'] ?? '') === 'board'
            && (int) ($native_owner['id_block'] ?? 0) !== $target_block_id
            && (int) ($native_owner['interactive_board_id'] ?? 0) === (int) $topic_row['id_board'])
        {
            $target_block_id = (int) $native_owner['id_block'];
        }
    }

    $href = $scripturl . '?action=portalblock;bp_view=topic;topic=' . $id_topic . '.0;bp_page=' . $id_page . ';bp_block=' . $target_block_id;
    $who = trim((string) $topic_row['poster_name']) !== '' ? (string) $topic_row['poster_name'] : $txt['betterportal_unknown_member'];

    $body = '<div class="betterportal-topic-preview">';
    $body .= '<div class="betterportal-topic-preview-title"><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($topic_row['subject'], ENT_QUOTES, 'UTF-8') . '</a></div>';
    $body .= '<div class="smalltext betterportal-topic-preview-meta">' . htmlspecialchars($topic_row['board_name'], ENT_QUOTES, 'UTF-8') . ' · ' . sprintf($txt['betterportal_dynamic_topic_started_by'], htmlspecialchars($who, ENT_QUOTES, 'UTF-8')) . ' · ' . sprintf($txt['betterportal_dynamic_topic_stats'], (int) $topic_row['num_replies'], (int) $topic_row['num_views']) . '</div>';
    $body .= '<div class="betterportal-topic-preview-body">' . parse_bbc($topic_row['body']) . '</div>';
    $body .= '<div class="betterportal-topic-preview-actions"><a class="button" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($txt['betterportal_dynamic_topic_open'], ENT_QUOTES, 'UTF-8') . '</a></div>';
    $body .= '</div>';
    return array('body' => $body);
}

/**
 * Dynamic Forum Board block.
 *
 * Stores only the selected board id and presentation settings. All board and
 * topic content is queried live, with SMF's board-visibility placeholder and
 * post-moderation approval rules applied at render time.
 */
function BetterPortal_RenderDynamicForumBoard($block)
{
    global $smcFunc, $scripturl, $modSettings, $txt, $user_info;

    $settings = BetterPortal_DecodeSettings($block['settings']);
    $id_board = max(0, (int) ($settings['board_id'] ?? 0));
    $limit = max(1, min(25, (int) ($settings['limit'] ?? 10)));
    $style = (string) ($settings['display_style'] ?? 'full');
    if (!in_array($style, array('compact', 'full', 'interactive'), true))
        $style = 'full';

    if ($id_board <= 0)
    {
        if (function_exists('allowedTo') && allowedTo('admin_forum'))
            return array('body'=>'<p class="smalltext">'.htmlspecialchars($txt['betterportal_dynamic_board_needs_selection'], ENT_QUOTES, 'UTF-8').'</p>');
        return false;
    }

    // Resolve the selected board through SMF's normal board visibility query.
    // If the visitor cannot see it, omit the entire block without revealing
    // the board name or any topic metadata.
    $request = $smcFunc['db_query']('', '
        SELECT b.id_board, b.name, b.description, b.num_topics, b.num_posts
        FROM {db_prefix}boards AS b
        WHERE b.id_board = {int:id_board}
            AND {query_see_board}
        LIMIT 1', array('id_board'=>$id_board)
    );
    $board = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    if (empty($board))
        return false;

    $approval_where = !empty($modSettings['postmod_active']) ? '
            AND t.approved = {int:approved}
            AND fm.approved = {int:approved}
            AND lm.approved = {int:approved}' : '';

    $request = $smcFunc['db_query']('', '
        SELECT t.id_topic, t.num_replies, t.num_views, t.is_sticky, t.locked,
            fm.subject, fm.id_member AS first_member, COALESCE(first_mem.real_name, fm.poster_name) AS first_poster,
            lm.id_msg AS id_last_msg, lm.poster_time AS last_time,
            COALESCE(last_mem.real_name, lm.poster_name) AS last_poster
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            INNER JOIN {db_prefix}messages AS lm ON (lm.id_msg = t.id_last_msg)
            LEFT JOIN {db_prefix}members AS first_mem ON (first_mem.id_member = fm.id_member)
            LEFT JOIN {db_prefix}members AS last_mem ON (last_mem.id_member = lm.id_member)
        WHERE t.id_board = {int:id_board}
            AND {query_see_board}' . $approval_where . '
        ORDER BY t.is_sticky DESC, lm.id_msg DESC
        LIMIT {int:limit}', array('id_board'=>$id_board, 'approved'=>1, 'limit'=>$limit)
    );

    $topics = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        censorText($row['subject']);
        $topics[] = $row;
    }
    $smcFunc['db_free_result']($request);

    // Interactive links carry the originating page/block so Board, Topic and
    // Composer views can render back inside this exact block.
    $shell_suffix = $style === 'interactive'
        ? ';bp_page=' . (int) ($block['id_page'] ?? 0) . ';bp_block=' . (int) ($block['id_block'] ?? 0)
        : '';
    $board_href = $style === 'interactive'
        ? $scripturl . '?action=portalblock;bp_view=board;board=' . $id_board . '.0' . $shell_suffix
        : $scripturl . '?board=' . $id_board . '.0';
    $board_name = htmlspecialchars($board['name'], ENT_QUOTES, 'UTF-8');

    // Interactive mode never implements SMF posting itself. It exposes native
    // SMF actions only when the visitor has the corresponding board permission;
    // SMF's post controller remains the final authority for every submission.
    $can_new_topic = false;
    $can_reply_any = false;
    $can_reply_own = false;
    if ($style === 'interactive' && function_exists('boardsAllowedTo'))
    {
        $new_boards = array_map('intval', (array) boardsAllowedTo('post_new'));
        $reply_any_boards = array_map('intval', (array) boardsAllowedTo('post_reply_any'));
        $reply_own_boards = array_map('intval', (array) boardsAllowedTo('post_reply_own'));
        $can_new_topic = in_array(0, $new_boards, true) || in_array($id_board, $new_boards, true);
        $can_reply_any = in_array(0, $reply_any_boards, true) || in_array($id_board, $reply_any_boards, true);
        $can_reply_own = in_array(0, $reply_own_boards, true) || in_array($id_board, $reply_own_boards, true);
    }

    $html = '<div class="betterportal-dynamic-board betterportal-dynamic-board-'.$style.'">';
    $html .= '<div class="betterportal-dynamic-board-summary"><a class="betterportal-dynamic-board-name" href="'.htmlspecialchars($board_href, ENT_QUOTES, 'UTF-8').'">'.$board_name.'</a>';
    $html .= '<span class="smalltext">'.sprintf(htmlspecialchars($txt['betterportal_dynamic_board_counts'], ENT_QUOTES, 'UTF-8'), (int)$board['num_topics'], (int)$board['num_posts']).'</span></div>';

    if ($style === 'interactive')
    {
        $html .= '<div class="betterportal-dynamic-board-actions">';
        if ($can_new_topic)
            $html .= '<a class="button" href="'.htmlspecialchars($scripturl . '?action=portalblock;bp_view=post;board=' . $id_board . '.0' . $shell_suffix, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($txt['betterportal_dynamic_board_new_topic'], ENT_QUOTES, 'UTF-8').'</a>';
        $html .= '</div>';
    }

    if (!empty($board['description']) && $style !== 'compact')
        $html .= '<div class="smalltext betterportal-dynamic-board-description">'.parse_bbc($board['description'], false).'</div>';

    if (empty($topics))
        $html .= '<p class="smalltext">'.htmlspecialchars($txt['betterportal_dynamic_board_no_topics'], ENT_QUOTES, 'UTF-8').'</p>';
    elseif ($style === 'compact')
    {
        $html .= '<ul class="betterportal-list betterportal-dynamic-board-topic-list">';
        foreach ($topics as $topic)
        {
            $href = ($style === 'interactive' ? $scripturl . '?action=portalblock;bp_view=topic;topic=' . (int)$topic['id_topic'] . '.0' . $shell_suffix : $scripturl . '?topic=' . (int)$topic['id_topic'] . '.0');
            $last_href = ($style === 'interactive' ? $scripturl . '?action=portalblock;bp_view=topic;topic=' . (int)$topic['id_topic'] . '.msg' . (int)$topic['id_last_msg'] . $shell_suffix : $scripturl . '?topic=' . (int)$topic['id_topic'] . '.msg' . (int)$topic['id_last_msg']) . '#msg' . (int)$topic['id_last_msg'];
            $html .= '<li><a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($topic['subject'], ENT_QUOTES, 'UTF-8').'</a><span class="smalltext">'.htmlspecialchars($topic['last_poster'], ENT_QUOTES, 'UTF-8').' · <a href="'.htmlspecialchars($last_href, ENT_QUOTES, 'UTF-8').'">'.timeformat((int)$topic['last_time']).'</a></span></li>';
        }
        $html .= '</ul>';
    }
    else
    {
        $html .= '<div class="betterportal-dynamic-board-table" role="table">';
        foreach ($topics as $topic)
        {
            $href = ($style === 'interactive' ? $scripturl . '?action=portalblock;bp_view=topic;topic=' . (int)$topic['id_topic'] . '.0' . $shell_suffix : $scripturl . '?topic=' . (int)$topic['id_topic'] . '.0');
            $last_href = ($style === 'interactive' ? $scripturl . '?action=portalblock;bp_view=topic;topic=' . (int)$topic['id_topic'] . '.msg' . (int)$topic['id_last_msg'] . $shell_suffix : $scripturl . '?topic=' . (int)$topic['id_topic'] . '.msg' . (int)$topic['id_last_msg']) . '#msg' . (int)$topic['id_last_msg'];
            $flags = '';
            if (!empty($topic['is_sticky'])) $flags .= '<span class="betterportal-topic-flag" title="'.htmlspecialchars($txt['betterportal_dynamic_board_sticky'], ENT_QUOTES, 'UTF-8').'">★</span>';
            if (!empty($topic['locked'])) $flags .= '<span class="betterportal-topic-flag" title="'.htmlspecialchars($txt['betterportal_dynamic_board_locked'], ENT_QUOTES, 'UTF-8').'">🔒</span>';
            $html .= '<div class="betterportal-dynamic-board-row" role="row">';
            $html .= '<div class="betterportal-dynamic-board-topic" role="cell">'.$flags.'<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($topic['subject'], ENT_QUOTES, 'UTF-8').'</a><span class="smalltext">'.sprintf(htmlspecialchars($txt['betterportal_dynamic_board_started_by'], ENT_QUOTES, 'UTF-8'), htmlspecialchars($topic['first_poster'], ENT_QUOTES, 'UTF-8')).'</span>';
            if ($style === 'interactive')
            {
                $owns_topic = !empty($user_info['id']) && (int) $topic['first_member'] === (int) $user_info['id'];
                if (empty($topic['locked']) && ($can_reply_any || ($owns_topic && $can_reply_own)))
                    $html .= '<a class="betterportal-dynamic-board-reply" href="'.htmlspecialchars($scripturl . '?action=portalblock;bp_view=post;topic=' . (int)$topic['id_topic'] . '.0' . $shell_suffix, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($txt['betterportal_dynamic_board_reply'], ENT_QUOTES, 'UTF-8').'</a>';
            }
            $html .= '</div>';
            $html .= '<div class="betterportal-dynamic-board-stats smalltext" role="cell">'.sprintf(htmlspecialchars($txt['betterportal_dynamic_board_topic_stats'], ENT_QUOTES, 'UTF-8'), (int)$topic['num_replies'], (int)$topic['num_views']).'</div>';
            $html .= '<div class="betterportal-dynamic-board-last smalltext" role="cell"><a href="'.htmlspecialchars($last_href, ENT_QUOTES, 'UTF-8').'">'.timeformat((int)$topic['last_time']).'</a><br>'.htmlspecialchars($topic['last_poster'], ENT_QUOTES, 'UTF-8').'</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    if ($style !== 'interactive')
        $html .= '<div class="betterportal-dynamic-board-footer"><a href="'.htmlspecialchars($board_href, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($txt['betterportal_dynamic_board_view_board'], ENT_QUOTES, 'UTF-8').' →</a></div>';
    $html .= '</div>';
    return array('body'=>$html);
}

function BetterPortal_RenderCustomText($block)
{
    $s=BetterPortal_DecodeSettings($block['settings']); $body=isset($s['body'])?(string)$s['body']:''; return array('body'=>!empty($s['parse_bbc'])?parse_bbc($body,true):nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8')));
}

function BetterPortal_RenderRecentPosts($block)
{
    global $sourcedir, $txt;
    require_once($sourcedir . '/Subs-Recent.php');
    $settings = BetterPortal_DecodeSettings($block['settings']);
    $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));
    $posts = getLastPosts(array('number_posts' => $limit));
    if (empty($posts))
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_no_recent_posts'], ENT_QUOTES, 'UTF-8') . '</p>');

    $html = '<ul class="betterportal-list">';
    foreach ($posts as $post)
        // SMF's getLastPosts() already returns a formatted time string that may
        // intentionally contain safe markup such as <strong>Today</strong>.
        // Escape user-controlled fields, but preserve SMF's formatted time HTML.
        $html .= '<li><a href="' . htmlspecialchars($post['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($post['subject'], ENT_QUOTES, 'UTF-8') . '</a><span class="smalltext">' . htmlspecialchars($post['poster']['name'], ENT_QUOTES, 'UTF-8') . ' &middot; ' . (string) $post['time'] . '</span></li>';
    $html .= '</ul>';
    return array('body' => $html);
}

function BetterPortal_RenderRecentTopics($block)
{
    global $sourcedir, $scripturl, $txt;
    require_once($sourcedir . '/Subs-Recent.php');
    $settings = BetterPortal_DecodeSettings($block['settings']);
    $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));

    // Reuse SMF's own recent-post helper so board visibility is handled by SMF.
    // Pull extra posts, then collapse them to one entry per topic.
    $posts = getLastPosts(array('number_posts' => min(50, max($limit * 5, 20))));
    $topics = array();
    $seen = array();
    foreach ($posts as $post)
    {
        $topic_id = 0;
        if (isset($post['topic']) && is_numeric($post['topic']))
            $topic_id = (int) $post['topic'];
        elseif (isset($post['id_topic']) && is_numeric($post['id_topic']))
            $topic_id = (int) $post['id_topic'];

        $key = $topic_id > 0 ? 't' . $topic_id : 'u' . (string) ($post['href'] ?? '') . '|' . (string) ($post['subject'] ?? '');
        if (isset($seen[$key]))
            continue;
        $seen[$key] = true;

        $href = $topic_id > 0 ? $scripturl . '?topic=' . $topic_id . '.0' : (string) ($post['href'] ?? $scripturl);
        $topics[] = array(
            'href' => $href,
            'subject' => (string) ($post['subject'] ?? $txt['betterportal_untitled_topic']),
            'poster' => (string) ($post['poster']['name'] ?? ''),
            'time' => (string) ($post['time'] ?? ''),
        );
        if (count($topics) >= $limit)
            break;
    }

    if (empty($topics))
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_no_recent_topics'], ENT_QUOTES, 'UTF-8') . '</p>');

    $html = '<ul class="betterportal-list">';
    foreach ($topics as $topic)
    {
        $html .= '<li><a href="' . htmlspecialchars($topic['href'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($topic['subject'], ENT_QUOTES, 'UTF-8') . '</a>';

        $meta = '';
        if ($topic['poster'] !== '')
            $meta .= htmlspecialchars($topic['poster'], ENT_QUOTES, 'UTF-8');
        if ($topic['time'] !== '')
            $meta .= ($meta !== '' ? ' &middot; ' : '') . (string) $topic['time'];

        if ($meta !== '')
            $html .= '<span class="smalltext">' . $meta . '</span>';
        $html .= '</li>';
    }
    $html .= '</ul>';
    return array('body' => $html);
}

function BetterPortal_RenderMemberWelcome($block)
{
    global $user_info, $scripturl, $txt, $modSettings;

    if (!empty($user_info['is_guest']))
    {
        $html = '<p class="betterportal-welcome-name">' . htmlspecialchars($txt['betterportal_welcome_guest'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div class="betterportal-quick-links"><a href="' . $scripturl . '?action=login">' . htmlspecialchars($txt['login'], ENT_QUOTES, 'UTF-8') . '</a>';
        if (!isset($modSettings['registration_method']) || (int) $modSettings['registration_method'] !== 3)
            $html .= '<a href="' . $scripturl . '?action=signup">' . htmlspecialchars($txt['register'], ENT_QUOTES, 'UTF-8') . '</a>';
        $html .= '</div>';
        return array('body' => $html);
    }

    $name = !empty($user_info['name']) ? $user_info['name'] : (!empty($user_info['username']) ? $user_info['username'] : '');
    $html = '<p class="betterportal-welcome-name">' . sprintf(htmlspecialchars($txt['betterportal_welcome_member'], ENT_QUOTES, 'UTF-8'), htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</p>'
        . '<div class="betterportal-quick-links">'
        . '<a href="' . $scripturl . '?action=profile">' . htmlspecialchars($txt['profile'], ENT_QUOTES, 'UTF-8') . '</a>'
        . '<a href="' . $scripturl . '?action=unread">' . htmlspecialchars($txt['betterportal_unread_posts'], ENT_QUOTES, 'UTF-8') . '</a>'
        . '<a href="' . $scripturl . '?action=unreadreplies">' . htmlspecialchars($txt['betterportal_updated_topics'], ENT_QUOTES, 'UTF-8') . '</a>';
    if (allowedTo('pm_read'))
        $html .= '<a href="' . $scripturl . '?action=pm">' . htmlspecialchars($txt['betterportal_messages'], ENT_QUOTES, 'UTF-8') . '</a>';
    $html .= '</div>';
    return array('body' => $html);
}

function BetterPortal_RenderForumSearch($block)
{
    global $scripturl, $txt;
    $html = '<form class="betterportal-search-form" action="' . htmlspecialchars($scripturl, ENT_QUOTES, 'UTF-8') . '" method="get">'
        . '<input type="hidden" name="action" value="search2">'
        . '<input type="text" name="search" value="" placeholder="' . htmlspecialchars($txt['betterportal_search_placeholder'], ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($txt['betterportal_search_placeholder'], ENT_QUOTES, 'UTF-8') . '">'
        . '<button class="button" type="submit">' . htmlspecialchars($txt['search'], ENT_QUOTES, 'UTF-8') . '</button>'
        . '</form>';
    return array('body' => $html);
}

function BetterPortal_RenderTopPosters($block)
{
    global $smcFunc, $scripturl, $txt;
    $settings = BetterPortal_DecodeSettings($block['settings']);
    $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));

    $request = $smcFunc['db_query']('', '
        SELECT id_member, real_name, posts
        FROM {db_prefix}members
        WHERE is_activated = {int:activated}
            AND posts > {int:no_posts}
        ORDER BY posts DESC, real_name ASC
        LIMIT {int:limit}', array(
            'activated' => 1,
            'no_posts' => 0,
            'limit' => $limit,
        ));
    $posters = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $posters[] = $row;
    $smcFunc['db_free_result']($request);

    if (empty($posters))
        return array('body' => '<p class="smalltext">' . htmlspecialchars($txt['betterportal_no_top_posters'], ENT_QUOTES, 'UTF-8') . '</p>');

    $html = '<ol class="betterportal-top-posters">';
    foreach ($posters as $poster)
        $html .= '<li><a href="' . $scripturl . '?action=profile;u=' . (int) $poster['id_member'] . '">' . htmlspecialchars($poster['real_name'], ENT_QUOTES, 'UTF-8') . '</a><span>' . comma_format((int) $poster['posts']) . '</span></li>';
    $html .= '</ol>';
    return array('body' => $html);
}

function BetterPortal_RenderWhosOnline($block)
{
    global $sourcedir, $txt;
    require_once($sourcedir . '/Subs-MembersOnline.php');
    $stats = getMembersOnlineStats(array('show_hidden' => allowedTo('moderate_forum')));
    $parts = array();
    if (!empty($stats['num_users_online'])) $parts[] = sprintf($txt['betterportal_online_members'], (int) $stats['num_users_online']);
    if (!empty($stats['num_guests'])) $parts[] = sprintf($txt['betterportal_online_guests'], (int) $stats['num_guests']);
    if (empty($parts)) $parts[] = $txt['betterportal_online_none'];
    $html = '<p class="betterportal-online-summary">' . implode(' &middot; ', $parts) . '</p>';
    if (!empty($stats['list_users_online']))
        $html .= '<div class="betterportal-online-users">' . implode(', ', $stats['list_users_online']) . '</div>';
    return array('body' => $html);
}

function BetterPortal_RenderForumStats($block)
{
    global $modSettings, $scripturl, $txt;
    $posts = comma_format((int) ($modSettings['totalMessages'] ?? 0));
    $topics = comma_format((int) ($modSettings['totalTopics'] ?? 0));
    $members = comma_format((int) ($modSettings['totalMembers'] ?? 0));
    $html = '<dl class="betterportal-stats">'
        . '<dt>' . htmlspecialchars($txt['betterportal_stats_posts'], ENT_QUOTES, 'UTF-8') . '</dt><dd>' . $posts . '</dd>'
        . '<dt>' . htmlspecialchars($txt['betterportal_stats_topics'], ENT_QUOTES, 'UTF-8') . '</dt><dd>' . $topics . '</dd>'
        . '<dt>' . htmlspecialchars($txt['betterportal_stats_members'], ENT_QUOTES, 'UTF-8') . '</dt><dd>' . $members . '</dd>';
    if (!empty($modSettings['latestMember']) && !empty($modSettings['latestRealName']))
        $html .= '<dt>' . htmlspecialchars($txt['betterportal_stats_latest_member'], ENT_QUOTES, 'UTF-8') . '</dt><dd><a href="' . $scripturl . '?action=profile;u=' . (int) $modSettings['latestMember'] . '">' . htmlspecialchars($modSettings['latestRealName'], ENT_QUOTES, 'UTF-8') . '</a></dd>';
    $html .= '</dl>';
    return array('body' => $html);
}

function BetterPortal_DecodeSettings($settings){$d=json_decode((string)$settings,true);return is_array($d)?$d:array();}
function BetterPortal_LayoutColumns($layout){$m=array('1'=>array('col1'),'1-1'=>array('col1','col2'),'2-1'=>array('col1','col2'),'1-2'=>array('col1','col2'),'1-1-1'=>array('col1','col2','col3'));return isset($m[$layout])?$m[$layout]:$m['1'];}
function BetterPortal_ColumnLabel($layout,$column){global $txt;BetterPortal_LoadLanguage();$map=array('1'=>array('col1'=>'betterportal_column_content'),'1-1'=>array('col1'=>'betterportal_column_left','col2'=>'betterportal_column_right'),'2-1'=>array('col1'=>'betterportal_column_main','col2'=>'betterportal_column_sidebar'),'1-2'=>array('col1'=>'betterportal_column_sidebar','col2'=>'betterportal_column_main'),'1-1-1'=>array('col1'=>'betterportal_column_left','col2'=>'betterportal_column_center','col3'=>'betterportal_column_right'));$key=$map[$layout][$column]??'betterportal_column_content';return $txt[$key]??$column;}
function BetterPortal_CanView($allowed_groups)
{
    global $user_info;

    $rules = json_decode((string) $allowed_groups, true);
    if (!is_array($rules) || empty($rules) || in_array('everyone', $rules, true))
        return true;

    if (in_array('guests', $rules, true) && !empty($user_info['is_guest']))
        return true;

    if (in_array('members', $rules, true) && empty($user_info['is_guest']))
        return true;

    if (in_array('staff', $rules, true) && empty($user_info['is_guest']))
    {
        if (!empty($user_info['is_admin']))
            return true;

        if (function_exists('allowedTo') && (allowedTo('moderate_forum') || allowedTo('admin_forum')))
            return true;
    }

    $groups = BetterPortal_EffectiveMembergroupIds();
    foreach ($rules as $rule)
        if (is_numeric($rule) && in_array((int) $rule, $groups, true))
            return true;

    return false;
}

/**
 * Return the current visitor's complete effective SMF membergroup set.
 *
 * SMF exposes convenient group information in $user_info, but depending on
 * the request and theme/integration path it is safer not to assume that
 * $user_info['groups'] alone always contains the primary, additional, and
 * post-count groups. Audience-aware Portal pages need one authoritative set
 * for both menu visibility and direct-access checks, so we merge the runtime
 * values with the member row when logged in.
 */
function BetterPortal_EffectiveMembergroupIds()
{
    global $user_info, $smcFunc;

    static $cache = null;
    if ($cache !== null)
        return $cache;

    $groups = array();

    if (!empty($user_info['groups']) && is_array($user_info['groups']))
        $groups = array_merge($groups, array_map('intval', $user_info['groups']));

    foreach (array('id_group', 'id_post_group') as $key)
        if (!empty($user_info[$key]))
            $groups[] = (int) $user_info[$key];

    if (!empty($user_info['additional_groups']))
    {
        $additional = is_array($user_info['additional_groups'])
            ? $user_info['additional_groups']
            : explode(',', (string) $user_info['additional_groups']);
        $groups = array_merge($groups, array_map('intval', $additional));
    }

    if (empty($user_info['is_guest']) && !empty($user_info['id']) && isset($smcFunc['db_query']))
    {
        $request = $smcFunc['db_query']('', '
            SELECT id_group, additional_groups, id_post_group
            FROM {db_prefix}members
            WHERE id_member={int:id_member}
            LIMIT 1', array('id_member' => (int) $user_info['id']));
        $member = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);

        if (!empty($member))
        {
            if (!empty($member['id_group']))
                $groups[] = (int) $member['id_group'];
            if (!empty($member['id_post_group']))
                $groups[] = (int) $member['id_post_group'];
            if (!empty($member['additional_groups']))
                $groups = array_merge($groups, array_map('intval', explode(',', (string) $member['additional_groups'])));
        }
    }

    $groups = array_values(array_unique(array_filter(array_map('intval', $groups), function ($id) { return $id > 0; })));
    sort($groups, SORT_NUMERIC);
    $cache = $groups;
    return $cache;
}

function BetterPortal_AudienceRulesFromPost($post)
{
    $mode = isset($post['page_audience']) ? (string) $post['page_audience'] : 'everyone';
    if (in_array($mode, array('everyone', 'guests', 'members', 'staff'), true))
        return array($mode);

    if ($mode === 'groups')
    {
        $groups = isset($post['page_audience_groups']) && is_array($post['page_audience_groups']) ? $post['page_audience_groups'] : array();
        $groups = array_values(array_unique(array_filter(array_map('intval', $groups), function ($id) { return $id > 0; })));
        if (!empty($groups))
            return $groups;

        // Fail closed if Selected membergroups is chosen without any groups.
        // This avoids accidentally turning a restricted page public.
        return array('__none__');
    }

    return array('everyone');
}

function BetterPortal_AudienceMode($allowed_groups)
{
    $rules = json_decode((string) $allowed_groups, true);
    if (!is_array($rules) || empty($rules) || in_array('everyone', $rules, true)) return 'everyone';
    if (in_array('guests', $rules, true)) return 'guests';
    if (in_array('members', $rules, true)) return 'members';
    if (in_array('staff', $rules, true)) return 'staff';
    return 'groups';
}

function BetterPortal_AudienceGroupIds($allowed_groups)
{
    $rules = json_decode((string) $allowed_groups, true);
    if (!is_array($rules)) return array();
    return array_values(array_filter(array_map('intval', $rules), function ($id) { return $id > 0; }));
}

function BetterPortal_LoadAudienceMembergroups()
{
    global $smcFunc;

    $groups = array();
    $request = $smcFunc['db_query']('', '
        SELECT id_group, group_name, min_posts
        FROM {db_prefix}membergroups
        WHERE id_group > {int:regular}
        ORDER BY min_posts, group_name', array('regular' => 0));
    while ($group = $smcFunc['db_fetch_assoc']($request))
        $groups[] = $group;
    $smcFunc['db_free_result']($request);

    return $groups;
}

function BetterPortal_Slug($slug){$slug=strtolower(trim($slug));$slug=preg_replace('~[^a-z0-9_-]+~','-',$slug);return substr(trim($slug,'-_'),0,80);}
function BetterPortal_UniqueSlug($slug,$exclude=0){global $smcFunc;if($slug==='')$slug='page';$base=$slug;$n=2;while(true){$request=$smcFunc['db_query']('', 'SELECT id_page FROM {db_prefix}betterportal_pages WHERE slug={string:slug} AND id_page != {int:exclude} LIMIT 1',array('slug'=>$slug,'exclude'=>$exclude));$exists=$smcFunc['db_fetch_row']($request);$smcFunc['db_free_result']($request);if(empty($exists))return $slug;$suffix='-'.$n++;$slug=substr($base,0,80-strlen($suffix)).$suffix;}}
function BetterPortal_LoadPageRecord($id_page)
{
    global $smcFunc;
    $request = $smcFunc['db_query']('', '
        SELECT p.*, COALESCE(n.show_in_nav, {int:nav_visible}) AS show_in_nav,
            COALESCE(n.menu_label, {string:empty_label}) AS menu_label,
            COALESCE(n.nav_order, p.id_page * {int:nav_step}) AS nav_order
        FROM {db_prefix}betterportal_pages AS p
            LEFT JOIN {db_prefix}betterportal_page_nav AS n ON (n.id_page = p.id_page)
        WHERE p.id_page={int:id}
        LIMIT 1', array('id'=>$id_page, 'nav_visible'=>1, 'empty_label'=>'', 'nav_step'=>10));
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    return $row ?: array();
}

function BetterPortal_AdminSortablePageIds()
{
    global $smcFunc;
    $ids = array();
    $request = $smcFunc['db_query']('', '
        SELECT p.id_page
        FROM {db_prefix}betterportal_pages AS p
            LEFT JOIN {db_prefix}betterportal_page_nav AS n ON (n.id_page = p.id_page)
        WHERE p.is_home = {int:not_home}
        ORDER BY COALESCE(n.nav_order, p.id_page * {int:nav_step}) ASC, p.id_page ASC', array(
            'not_home' => 0,
            'nav_step' => 10,
        ));
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $ids[] = (int) $row['id_page'];
    $smcFunc['db_free_result']($request);
    return $ids;
}

function BetterPortal_SaveAdminPageOrder($submitted)
{
    global $smcFunc;
    if (!is_array($submitted)) return false;
    $submitted = array_values(array_map('intval', $submitted));
    $existing = BetterPortal_AdminSortablePageIds();
    $a = $existing; $b = $submitted;
    sort($a, SORT_NUMERIC); sort($b, SORT_NUMERIC);
    if ($a !== $b || count($submitted) !== count(array_unique($submitted))) return false;

    foreach ($submitted as $index => $id_page)
    {
        BetterPortal_EnsurePageNavigation($id_page);
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}betterportal_page_nav
            SET nav_order = {int:nav_order}
            WHERE id_page = {int:id_page}', array(
                'nav_order' => min(65535, ($index + 1) * 10),
                'id_page' => $id_page,
            ));
    }
    return true;
}

function BetterPortal_MoveAdminPage($id_page, $direction)
{
    if ($id_page <= 0 || !in_array($direction, array('up', 'down'), true)) return false;
    $ids = BetterPortal_AdminSortablePageIds();
    $pos = array_search((int) $id_page, $ids, true);
    if ($pos === false) return false;
    $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
    if ($swap < 0 || $swap >= count($ids)) return false;
    $tmp = $ids[$swap]; $ids[$swap] = $ids[$pos]; $ids[$pos] = $tmp;
    return BetterPortal_SaveAdminPageOrder($ids);
}

function BetterPortal_NextNavigationOrder()
{
    global $smcFunc;
    $request = $smcFunc['db_query']('', 'SELECT MAX(nav_order) AS max_order FROM {db_prefix}betterportal_page_nav', array());
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    return min(65535, max(0, (int) ($row['max_order'] ?? 0)) + 10);
}

function BetterPortal_EnsurePageNavigation($id_page, $append = false)
{
    global $smcFunc;
    $request = $smcFunc['db_query']('', 'SELECT id_page FROM {db_prefix}betterportal_page_nav WHERE id_page={int:id} LIMIT 1', array('id' => (int) $id_page));
    $exists = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    if (!empty($exists)) return;

    $order = $append ? BetterPortal_NextNavigationOrder() : min(65535, (int) $id_page * 10);
    $smcFunc['db_insert']('insert', '{db_prefix}betterportal_page_nav', array(
        'id_page' => 'int', 'show_in_nav' => 'int', 'menu_label' => 'string-160', 'nav_order' => 'int',
    ), array((int) $id_page, 1, '', $order), array('id_page'));
}

function BetterPortal_SavePageNavigation($id_page, $show_in_nav, $menu_label, $nav_order)
{
    global $smcFunc;
    BetterPortal_EnsurePageNavigation((int) $id_page);
    $smcFunc['db_query']('', '
        UPDATE {db_prefix}betterportal_page_nav
        SET show_in_nav={int:visible}, menu_label={string:label}, nav_order={int:nav_order}
        WHERE id_page={int:id_page}', array(
            'visible' => $show_in_nav ? 1 : 0,
            'label' => substr((string) $menu_label, 0, 160),
            'nav_order' => max(1, min(65535, (int) $nav_order)),
            'id_page' => (int) $id_page,
        ));
}
function BetterPortal_LoadRow($id_page,$id_row){global $smcFunc;$request=$smcFunc['db_query']('', 'SELECT * FROM {db_prefix}betterportal_rows WHERE id_page={int:p} AND id_row={int:r} LIMIT 1',array('p'=>$id_page,'r'=>$id_row));$row=$smcFunc['db_fetch_assoc']($request);$smcFunc['db_free_result']($request);return $row?:array();}
function BetterPortal_LoadBlock($id_page,$id_block){global $smcFunc;$request=$smcFunc['db_query']('', 'SELECT * FROM {db_prefix}betterportal_blocks WHERE id_page={int:p} AND id_block={int:b} LIMIT 1',array('p'=>$id_page,'b'=>$id_block));$row=$smcFunc['db_fetch_assoc']($request);$smcFunc['db_free_result']($request);return $row?:array();}
