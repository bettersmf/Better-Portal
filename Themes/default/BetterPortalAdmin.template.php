<?php
/** Better Portal admin templates. @version 0.1.0-dev24 */
function template_betterportal_admin_notice_saved(){global $context,$txt;if(!empty($context['betterportal_saved']))echo '<div class="infobox">',$txt['betterportal_saved'],'</div>';}
function template_betterportal_admin_overview(){global $context,$txt,$scripturl;echo '<div class="cat_bar"><h3 class="catbg">',$txt['betterportal_admin_overview'],'</h3></div><div class="windowbg"><p>',$txt['betterportal_overview_intro'],'</p><dl class="settings"><dt><strong>',$txt['betterportal_mode'],'</strong></dt><dd>',htmlspecialchars($context['betterportal_mode'],ENT_QUOTES,'UTF-8'),'</dd><dt><strong>',$txt['betterportal_pages_count'],'</strong></dt><dd>',(int)$context['betterportal_counts']['pages'],'</dd><dt><strong>',$txt['betterportal_rows_count'],'</strong></dt><dd>',(int)$context['betterportal_counts']['rows'],'</dd><dt><strong>',$txt['betterportal_blocks_count'],'</strong></dt><dd>',(int)$context['betterportal_counts']['blocks'],'</dd></dl><p><a class="button" href="',$scripturl,'?action=portal">',$txt['betterportal_view_portal'],'</a></p></div>';}
function template_betterportal_admin_pages()
{
    global $context, $txt, $scripturl, $smcFunc;

    echo '<div class="cat_bar"><h3 class="catbg">', $txt['betterportal_admin_pages'], '</h3></div>
    <div class="windowbg">
        <p>', $txt['betterportal_pages_dev2_note'], '</p>
        <div class="table_grid betterportal-pages-sorter" data-reorder-url="', $scripturl, '?action=admin;area=betterportal;sa=pages" data-session-var="', $smcFunc['htmlspecialchars']($context['session_var']), '" data-session-id="', $smcFunc['htmlspecialchars']($context['session_id']), '" data-token-var="', $smcFunc['htmlspecialchars']($context['betterportal-pages_token_var']), '" data-token="', $smcFunc['htmlspecialchars']($context['betterportal-pages_token']), '"><table class="table_grid">
            <thead><tr class="title_bar"><th class="betterportal-page-order-heading">', $txt['betterportal_page_order'], '</th><th>', $txt['betterportal_page_title'], '</th><th>', $txt['betterportal_page_slug'], '</th><th>', $txt['betterportal_page_layout'], '</th><th>', $txt['betterportal_blocks_count'], '</th><th>', $txt['betterportal_page_home'], '</th><th>', $txt['betterportal_page_enabled'], '</th><th>', $txt['betterportal_page_audience'], '</th><th>', $txt['betterportal_page_navigation'], '</th><th></th></tr></thead><tbody>';

    foreach ($context['betterportal_pages'] as $p)
    {
        $home = !empty($p['is_home']);
        echo '<tr class="windowbg betterportal-page-sort-row', $home ? ' betterportal-page-home-row' : '', '" data-page-id="', (int) $p['id_page'], '" data-sortable="', $home ? '0' : '1', '"><td class="betterportal-page-order-cell">';
        if ($home)
            echo '<span class="betterportal-page-pinned" title="', $smcFunc['htmlspecialchars']($txt['betterportal_home_order_pinned']), '">●</span>';
        else
        {
            echo '<span class="betterportal-page-drag-handle" draggable="true" tabindex="0" title="', $smcFunc['htmlspecialchars']($txt['betterportal_drag_page']), '">☰</span>
                <form class="betterportal-page-arrow-form" method="post" action="', $scripturl, '?action=admin;area=betterportal;sa=pages">
                    <input type="hidden" name="id_page" value="', (int) $p['id_page'], '">
                    <button class="button" name="move_page" value="up" title="', $smcFunc['htmlspecialchars']($txt['betterportal_move_page_up']), '">↑</button>
                    <button class="button" name="move_page" value="down" title="', $smcFunc['htmlspecialchars']($txt['betterportal_move_page_down']), '">↓</button>
                    <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
                    <input type="hidden" name="', $context['betterportal-pages_token_var'], '" value="', $context['betterportal-pages_token'], '">
                </form>';
        }
        echo '</td><td><strong>', $smcFunc['htmlspecialchars']($p['title']), '</strong></td><td><code>', $smcFunc['htmlspecialchars']($p['slug']), '</code></td><td>', $smcFunc['htmlspecialchars']($txt['betterportal_layout_' . str_replace('-', '_', $p['layout'])] ?? $p['layout']), '</td><td>', (int) $p['block_count'], '</td><td>', $home ? $txt['yes'] : $txt['no'], '</td><td>', !empty($p['is_enabled']) ? $txt['yes'] : $txt['no'], '</td><td>', $txt['betterportal_audience_' . BetterPortal_AudienceMode($p['allowed_groups'])], '</td><td>', $home ? $txt['betterportal_navigation_home_entry'] : (!empty($p['show_in_nav']) ? $txt['betterportal_navigation_shown'] : $txt['betterportal_navigation_hidden']), '<br><span class="smalltext">', $smcFunc['htmlspecialchars'](trim((string) $p['menu_label']) !== '' ? $p['menu_label'] : $p['title']), ' · ', (int) $p['nav_order'], '</span></td><td><div class="betterportal-table-actions"><a class="button" href="', $scripturl, '?action=admin;area=betterportal;sa=builder;id=', (int) $p['id_page'], '">', $txt['betterportal_build'], '</a><a class="button" href="', $scripturl, '?action=portal;page=', $smcFunc['htmlspecialchars']($p['slug']), '">', $txt['betterportal_preview'], '</a>';
        if (!$home)
        {
            echo '<form style="display:inline" method="post" action="', $scripturl, '?action=admin;area=betterportal;sa=pages">
                <input type="hidden" name="id_page" value="', (int) $p['id_page'], '">
                <button class="button" name="set_home" value="1">', $txt['betterportal_make_home'], '</button>
                <button class="button you_sure" name="delete_page" value="1">', $txt['delete'], '</button>
                <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
                <input type="hidden" name="', $context['betterportal-pages_token_var'], '" value="', $context['betterportal-pages_token'], '">
            </form>';
        }
        echo '</div></td></tr>';
    }

    echo '</tbody></table><div id="betterportal-page-sort-status" class="smalltext betterportal-sort-status" aria-live="polite"></div></div>
    </div>

    <div class="betterportal-admin-card">
        <div class="cat_bar"><h3 class="catbg">', $txt['betterportal_create_page'], '</h3></div>
        <div class="windowbg">
            <p>', $txt['betterportal_create_page_intro'], '</p>
            <form method="post" action="', $scripturl, '?action=admin;area=betterportal;sa=pages">
                <div class="betterportal-create-grid">
                    <label>', $txt['betterportal_page_title'], '
                        <input type="text" name="new_title" size="30" required placeholder="About Better SMF">
                        <span class="betterportal-help">', $txt['betterportal_page_title_help'], '</span>
                    </label>
                    <label>', $txt['betterportal_page_slug'], '
                        <input type="text" name="new_slug" size="24" placeholder="Leave blank for automatic">
                        <span class="betterportal-help">', $txt['betterportal_page_slug_help'], '</span>
                    </label>
                    <label>', $txt['betterportal_page_layout'], '
                        <select name="new_layout">
                            <option value="1">', $txt['betterportal_layout_1'], '</option>
                            <option value="1-1">', $txt['betterportal_layout_1_1'], '</option>
                            <option value="2-1">', $txt['betterportal_layout_2_1'], '</option>
                            <option value="1-2">', $txt['betterportal_layout_1_2'], '</option>
                            <option value="1-1-1">', $txt['betterportal_layout_1_1_1'], '</option>
                        </select>
                        <span class="betterportal-help">', $txt['betterportal_create_layout_help'], '</span>
                    </label>
                </div>
                <p><button class="button" name="create_page" value="1">', $txt['betterportal_create_and_build'], '</button></p>
                <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
                <input type="hidden" name="', $context['betterportal-pages_token_var'], '" value="', $context['betterportal-pages_token'], '">
            </form>
        </div>
    </div>';

    echo '<script>
    document.addEventListener("DOMContentLoaded",function(){
        var sorter=document.querySelector(".betterportal-pages-sorter");if(!sorter||!("draggable" in document.createElement("span")))return;
        var body=sorter.querySelector("tbody"),dragged=null,placeholder=document.createElement("tr"),status=document.getElementById("betterportal-page-sort-status");
        placeholder.className="betterportal-page-drop-placeholder";placeholder.innerHTML="<td colspan=\"10\"></td>";
        function rows(){return Array.prototype.slice.call(body.querySelectorAll(".betterportal-page-sort-row[data-sortable=\"1\"]"));}
        function ids(){return rows().map(function(r){return parseInt(r.dataset.pageId,10);});}
        function refresh(){window.location.assign(sorter.dataset.reorderUrl);}
        function postOrder(){var p=new URLSearchParams();p.set("reorder_pages","1");p.set("page_order",JSON.stringify(ids()));p.set(sorter.dataset.sessionVar,sorter.dataset.sessionId);p.set(sorter.dataset.tokenVar,sorter.dataset.token);if(status)status.textContent=', json_encode($txt['betterportal_page_order_saving']), ';fetch(sorter.dataset.reorderUrl,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8","X-Requested-With":"XMLHttpRequest"},body:p.toString()}).then(function(r){if(!r.ok)throw new Error();return r.json();}).then(function(d){if(!d||!d.success)throw new Error();if(status)status.textContent=', json_encode($txt['betterportal_page_order_saved']), ';setTimeout(refresh,250);}).catch(function(){if(status)status.textContent=', json_encode($txt['betterportal_page_order_failed']), ';setTimeout(refresh,900);});}
        function after(y){var els=rows().filter(function(r){return r!==dragged;});var closest={offset:Number.NEGATIVE_INFINITY,el:null};els.forEach(function(el){var box=el.getBoundingClientRect(),off=y-box.top-box.height/2;if(off<0&&off>closest.offset)closest={offset:off,el:el};});return closest.el;}
        body.addEventListener("dragstart",function(e){var h=e.target.closest?e.target.closest(".betterportal-page-drag-handle"):null;if(!h)return;dragged=h.closest(".betterportal-page-sort-row");if(!dragged)return;dragged.classList.add("betterportal-page-is-dragging");e.dataTransfer.effectAllowed="move";e.dataTransfer.setData("text/plain",dragged.dataset.pageId||"");});
        body.addEventListener("dragover",function(e){if(!dragged)return;e.preventDefault();var a=after(e.clientY);if(a)body.insertBefore(placeholder,a);else body.appendChild(placeholder);});
        body.addEventListener("drop",function(e){if(!dragged)return;e.preventDefault();if(placeholder.parentNode===body)body.insertBefore(dragged,placeholder);if(placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);dragged.classList.remove("betterportal-page-is-dragging");dragged=null;postOrder();});
        body.addEventListener("dragend",function(){if(placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);if(dragged)dragged.classList.remove("betterportal-page-is-dragging");dragged=null;});
    });
    </script>';
}

function template_betterportal_admin_builder()
{
    global $context, $txt, $scripturl, $smcFunc;

    $b = $context['betterportal_builder'];
    $page = $b['page'];
    $registry = $b['block_registry'];
    $audience_mode = $b['audience_mode'];
    $audience_groups = array_map('intval', $b['audience_groups']);

    template_betterportal_admin_notice_saved();
    if (!empty($context['betterportal_page_created']))
        echo '<div class="infobox">', $txt['betterportal_page_created'], '</div>';

    echo '<div class="windowbg betterportal-page-switcher"><strong>', $txt['betterportal_building_page'], '</strong> <select onchange="if(this.value) window.location=this.value">';
    foreach ($b['pages'] as $builder_page)
    {
        $label = $builder_page['title'] . (!empty($builder_page['is_home']) ? ' — ' . $txt['betterportal_home_badge'] : '') . (empty($builder_page['is_enabled']) ? ' — ' . $txt['betterportal_disabled_badge'] : '');
        echo '<option value="', $scripturl, '?action=admin;area=betterportal;sa=builder;id=', (int) $builder_page['id_page'], '"', (int) $builder_page['id_page'] === (int) $page['id_page'] ? ' selected' : '', '>', $smcFunc['htmlspecialchars']($label), '</option>';
    }
    echo '</select><a class="button" href="', $scripturl, '?action=admin;area=betterportal;sa=pages">', $txt['betterportal_manage_pages'], '</a></div>';

    echo '<div class="windowbg betterportal-builder-steps">', $txt['betterportal_builder_steps'], '</div>';

    echo '<form method="post" action="', $scripturl, '?action=admin;area=betterportal;sa=builder;id=', (int) $page['id_page'], '">
        <div class="cat_bar"><h3 class="catbg">', $txt['betterportal_page_settings'], '</h3></div>
        <div class="windowbg"><dl class="settings">
            <dt>', $txt['betterportal_page_title'], '</dt><dd><input type="text" name="page_title" value="', $smcFunc['htmlspecialchars']($page['title']), '" size="45"></dd>
            <dt>', $txt['betterportal_page_slug'], '</dt><dd><input type="text" name="page_slug" value="', $smcFunc['htmlspecialchars']($page['slug']), '" size="32"></dd>
            <dt>', $txt['betterportal_page_navigation'], '<br><span class="smalltext">', $txt['betterportal_page_navigation_desc'], '</span></dt>
            <dd><label><input type="checkbox" name="page_show_in_nav" value="1"', !empty($page['show_in_nav']) || !empty($page['is_home']) ? ' checked' : '', !empty($page['is_home']) ? ' disabled' : '', '> ', $txt['betterportal_page_show_in_nav'], '</label>', !empty($page['is_home']) ? '<input type="hidden" name="page_show_in_nav" value="1">' : '', '<br>
            <label>', $txt['betterportal_page_menu_label'], ' <input type="text" name="page_menu_label" value="', $smcFunc['htmlspecialchars']((string) ($page['menu_label'] ?? '')), '" size="26" placeholder="', $smcFunc['htmlspecialchars']($page['title']), '"></label><br>
            <label>', $txt['betterportal_page_nav_order'], ' <input type="number" name="page_nav_order" value="', (int) ($page['nav_order'] ?? 0), '" min="1" max="65535" step="1" style="width:7em"', !empty($page['is_home']) ? ' disabled' : '', '></label>', !empty($page['is_home']) ? '<input type="hidden" name="page_nav_order" value="' . (int) ($page['nav_order'] ?? 0) . '">' : '', '<div class="smalltext">', !empty($page['is_home']) ? $txt['betterportal_home_navigation_note'] : $txt['betterportal_page_navigation_order_desc'], '</div></dd>
            <dt>', $txt['betterportal_page_audience'], '<br><span class="smalltext">', $txt['betterportal_page_audience_desc'], '</span></dt>
            <dd><select name="page_audience" id="betterportal_page_audience">
                <option value="everyone"', $audience_mode === 'everyone' ? ' selected' : '', '>', $txt['betterportal_audience_everyone'], '</option>
                <option value="guests"', $audience_mode === 'guests' ? ' selected' : '', '>', $txt['betterportal_audience_guests'], '</option>
                <option value="members"', $audience_mode === 'members' ? ' selected' : '', '>', $txt['betterportal_audience_members'], '</option>
                <option value="staff"', $audience_mode === 'staff' ? ' selected' : '', '>', $txt['betterportal_audience_staff'], '</option>
                <option value="groups"', $audience_mode === 'groups' ? ' selected' : '', '>', $txt['betterportal_audience_groups'], '</option>
            </select><div id="betterportal_audience_groups" class="betterportal-audience-groups"', $audience_mode === 'groups' ? '' : ' hidden', '><span class="smalltext">', $txt['betterportal_audience_groups_desc'], '</span><br>';
    foreach ($b['membergroups'] as $group)
        echo '<label class="betterportal-audience-group"><input type="checkbox" name="page_audience_groups[]" value="', (int) $group['id_group'], '"', in_array((int) $group['id_group'], $audience_groups, true) ? ' checked' : '', '> ', $smcFunc['htmlspecialchars']($group['group_name']), '</label> ';
    echo '</div><div class="smalltext betterportal-audience-current">', $txt['betterportal_audience_visible_to_you'], ' <strong>', !empty($b['audience_visible_to_current_user']) ? $txt['yes'] : $txt['no'], '</strong></div></dd>
            <dt>', $txt['betterportal_page_enabled'], '</dt><dd><input type="checkbox" name="page_enabled" value="1"', !empty($page['is_enabled']) ? ' checked' : '', '></dd>
            <dt>', $txt['betterportal_page_show_title'], '</dt><dd><input type="checkbox" name="page_show_title" value="1"', !empty($page['show_title']) ? ' checked' : '', '></dd>
        </dl><div class="betterportal-action-group betterportal-page-settings-actions"><button class="button" name="save_page" value="1">', $txt['save'], '</button><a class="button" href="', $scripturl, '?action=portal;page=', $smcFunc['htmlspecialchars']($page['slug']), '">', $txt['betterportal_preview'], '</a></div>
        <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '"><input type="hidden" name="', $context['betterportal-builder_token_var'], '" value="', $context['betterportal-builder_token'], '"></div></form>';

    if (!empty($b['has_forum_mode']) && !empty($b['themelab_info']['detected']))
    {
        $tl_mode = strtolower((string) ($b['themelab_info']['width_mode'] ?? ''));
        $tl_width = (int) ($b['themelab_info']['max_width'] ?? 0);
        $notice = $tl_mode === 'fluid' ? $txt['betterportal_themelab_forum_notice_fluid'] : sprintf($txt['betterportal_themelab_forum_notice'], $tl_width > 0 ? $tl_width . 'px' : $txt['betterportal_themelab_width_unknown']);
        echo '<div class="information betterportal-forum-width-notice"><strong>', $txt['betterportal_themelab_detected'], '</strong> ', $notice, '</div>';
    }

    echo '<div class="cat_bar"><h3 class="catbg">', $txt['betterportal_rows_builder'], '</h3></div><div class="windowbg betterportal-columns-builder"><p class="description">', $txt['betterportal_rows_builder_desc'], '</p>';
    echo '<form class="betterportal-add-row" method="post" action="', $scripturl, '?action=admin;area=betterportal;sa=builder;id=', (int)$page['id_page'], '"><select name="new_row_layout">';
    foreach (array('1','1-1','2-1','1-2','1-1-1') as $option)
        echo '<option value="', $option, '">', $txt['betterportal_layout_' . str_replace('-','_',$option)], '</option>';
    echo '</select><button class="button" name="add_row" value="1">', $txt['betterportal_add_row'], '</button><input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '"><input type="hidden" name="', $context['betterportal-builder_token_var'], '" value="', $context['betterportal-builder_token'], '"></form>';

    echo '<div class="betterportal-rows-builder" data-reorder-url="', $scripturl, '?action=admin;area=betterportal;sa=builder;id=', (int)$page['id_page'], '" data-session-var="', $smcFunc['htmlspecialchars']($context['session_var']), '" data-session-id="', $smcFunc['htmlspecialchars']($context['session_id']), '" data-token-var="', $smcFunc['htmlspecialchars']($context['betterportal-builder_token_var']), '" data-token="', $smcFunc['htmlspecialchars']($context['betterportal-builder_token']), '">';

    $row_count = count($b['rows']);
    foreach ($b['rows'] as $index => $row)
    {
        $layout = $row['layout'];
        $width_preset = $row['column_widths'];
        $effective_width = $row['effective_width'];
        $forum_mode = !empty($row['forum_mode']);
        $forum_main_column = $row['forum_main_column'];
        $block_count = count($row['blocks']);

        echo '<section class="betterportal-admin-row" data-row-id="', (int)$row['id_row'], '"><div class="betterportal-row-toolbar"><span class="betterportal-row-drag-handle" draggable="true" title="', $smcFunc['htmlspecialchars']($txt['betterportal_drag_row']), '">☰</span><strong>', sprintf($txt['betterportal_row_heading'], $index + 1), '</strong><span class="smalltext">', sprintf($txt['betterportal_row_blocks_count'], $block_count), '</span></div>';
        echo '<form class="betterportal-row-settings" method="post" action="', $scripturl, '?action=admin;area=betterportal;sa=builder;id=', (int)$page['id_page'], '"><label>', $txt['betterportal_row_layout'], ' <select name="row_layout">';
        foreach (array('1','1-1','2-1','1-2','1-1-1') as $option)
            echo '<option value="', $option, '"', $layout===$option?' selected':'', '>', $txt['betterportal_layout_' . str_replace('-','_',$option)], '</option>';
        echo '</select></label><label>', $txt['betterportal_column_widths'], ' <select name="row_widths"><option value="auto"', $width_preset==='auto'?' selected':'', '>', $txt['betterportal_width_auto'], '</option><option value="equal"', $width_preset==='equal'?' selected':'', '>', $txt['betterportal_width_equal'], '</option><option value="wide-left"', $width_preset==='wide-left'?' selected':'', '>', $txt['betterportal_width_wide_left'], '</option><option value="wide-center"', $width_preset==='wide-center'?' selected':'', '>', $txt['betterportal_width_wide_center'], '</option><option value="wide-right"', $width_preset==='wide-right'?' selected':'', '>', $txt['betterportal_width_wide_right'], '</option></select></label><label class="betterportal-row-forum-mode"><input type="checkbox" name="row_forum_mode" value="1"', $forum_mode?' checked':'', '> ', $txt['betterportal_forum_mode'], '</label><div class="betterportal-row-actions"><button class="button" name="row_action" value="save">', $txt['save'], '</button><button class="button" name="row_action" value="up" title="', $txt['betterportal_move_up'], '">↑</button><button class="button" name="row_action" value="down" title="', $txt['betterportal_move_down'], '">↓</button>';
        if ($row_count > 1 && $block_count === 0)
            echo '<button class="button you_sure" name="row_action" value="delete">', $txt['delete'], '</button>';
        elseif ($row_count > 1)
            echo '<span class="smalltext">', $txt['betterportal_delete_row_empty_only'], '</span>';
        echo '</div><input type="hidden" name="id_row" value="', (int)$row['id_row'], '"><input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '"><input type="hidden" name="', $context['betterportal-builder_token_var'], '" value="', $context['betterportal-builder_token'], '"></form>';

        if ($forum_mode)
            echo '<div class="information betterportal-board-shell-notice"><strong>', $txt['betterportal_board_shell'], ':</strong> ', $txt['betterportal_board_shell_desc'], '</div>';

        echo '<div class="betterportal-admin-columns betterportal-admin-layout-', str_replace('-','_',$layout), ' betterportal-width-', $smcFunc['htmlspecialchars']($effective_width), '">';
        foreach ($row['columns'] as $column)
        {
            $is_forum_main = $forum_mode && $column === $forum_main_column;
            echo '<section class="betterportal-admin-column', $is_forum_main?' betterportal-admin-forum-column':'', '"><h4>', $smcFunc['htmlspecialchars'](BetterPortal_ColumnLabel($layout,$column)), '</h4>';
            if ($is_forum_main)
                echo '<div class="betterportal-forum-placeholder"><strong>', $txt['betterportal_native_forum_index'], '</strong><span class="smalltext">', $txt['betterportal_native_forum_index_desc'], '</span></div>';
            else
            {
                echo '<form class="betterportal-add-block" method="post" action="', $scripturl, '?action=admin;area=betterportal;sa=builder;id=', (int)$page['id_page'], '"><select name="block_type" aria-label="', $txt['betterportal_choose_block'], '">';
                $last_category=null;
                foreach($registry as $type=>$definition)
                {
                    $category=!empty($definition['category'])?(string)$definition['category']:'other';
                    if($last_category!==$category){if($last_category!==null)echo '</optgroup>';$group_label=$txt['betterportal_block_category_'.$category]??ucfirst($category);echo '<optgroup label="',$smcFunc['htmlspecialchars']($group_label),'">';$last_category=$category;}
                    $provider=!empty($definition['provider'])&&$definition['provider']!=='Better Portal'?' — '.$definition['provider']:'';
                    echo '<option value="',$smcFunc['htmlspecialchars']($type),'">',$smcFunc['htmlspecialchars']($definition['label'].$provider),'</option>';
                }
                if($last_category!==null)echo '</optgroup>';
                echo '</select><button class="button" name="add_block" value="1">',$txt['betterportal_add_block'],'</button><input type="hidden" name="id_row" value="',(int)$row['id_row'],'"><input type="hidden" name="column_key" value="',$column,'"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><input type="hidden" name="',$context['betterportal-builder_token_var'],'" value="',$context['betterportal-builder_token'],'"></form>';
            }

            $column_blocks=array();foreach($row['blocks'] as $block)if($block['column_key']===$column)$column_blocks[]=$block;
            echo '<div class="betterportal-block-list', $is_forum_main?' betterportal-block-list-locked':'', '" data-row-id="',(int)$row['id_row'],'" data-column-key="',$column,'" data-droppable="',$is_forum_main?'0':'1','">';
            if(empty($column_blocks)&&!$is_forum_main)echo '<p class="smalltext betterportal-empty-column">',$txt['betterportal_empty_column'],'</p>';

            foreach($column_blocks as $block)
            {
                $forum_mode_inactive=$forum_mode&&$column===$forum_main_column;
                echo '<form class="betterportal-block-editor', $forum_mode_inactive?' betterportal-forum-inactive-block':'', '" data-block-id="',(int)$block['id_block'],'" method="post" action="',$scripturl,'?action=admin;area=betterportal;sa=builder;id=',(int)$page['id_page'],'"><div class="betterportal-block-editor-heading">', !$forum_mode_inactive?'<span class="betterportal-drag-handle" draggable="true" tabindex="0" title="'.$smcFunc['htmlspecialchars']($txt['betterportal_drag_block']).'">☰</span>':'', '<strong>',$smcFunc['htmlspecialchars']($registry[$block['block_type']]['label']??$block['block_type']),'</strong><span class="smalltext">#',(int)$block['id_block'],'</span></div>', $forum_mode_inactive?'<div class="smalltext betterportal-forum-inactive-note">'.$txt['betterportal_forum_mode_block_inactive'].'</div>':'', '<dl class="settings"><dt>',$txt['betterportal_block_title'],'</dt><dd><input type="text" name="block_title" value="',$smcFunc['htmlspecialchars']($block['title']),'" size="36"></dd><dt>',$txt['betterportal_block_column'],'</dt><dd><select name="column_key">';
                foreach($row['columns'] as $move_column){if($forum_mode&&$move_column===$forum_main_column)continue;echo '<option value="',$move_column,'"',$block['column_key']===$move_column?' selected':'','>',$smcFunc['htmlspecialchars'](BetterPortal_ColumnLabel($layout,$move_column)),'</option>';}
                echo '</select></dd><dt>',$txt['betterportal_block_enabled'],'</dt><dd><input type="checkbox" name="block_enabled" value="1"',!empty($block['is_enabled'])?' checked':'','></dd><dt>',$txt['betterportal_block_show_title'],'</dt><dd><input type="checkbox" name="block_show_title" value="1"',!empty($block['show_title'])?' checked':'','></dd>';
                if($block['block_type']==='custom_text')
                    echo '<dt>',$txt['betterportal_block_body'],'</dt><dd><textarea name="block_body" rows="5" cols="54">',$smcFunc['htmlspecialchars']($block['body']),'</textarea></dd>';
                else
                {
                    $definition=$registry[$block['block_type']]??array();echo '<dt>',$txt['betterportal_block_source'],'</dt><dd>',$smcFunc['htmlspecialchars']($definition['description']??$txt['betterportal_block_managed_preset']),'</dd>';
                    if($block['block_type']==='dynamic_topic'){$native=BetterPortal_IsNativeInteractiveColumnSuitable($row,$block['column_key']);echo '<dt>',$txt['betterportal_dynamic_topic_mode'],'</dt><dd class="smalltext ',$native?'successbox':'noticebox','">',$native?$txt['betterportal_dynamic_topic_mode_interactive']:$txt['betterportal_dynamic_topic_mode_preview'],'</dd>';}
                    if(!empty($definition['settings_fields'])&&is_array($definition['settings_fields']))
                    {
                        $saved=!empty($block['settings_decoded'])&&is_array($block['settings_decoded'])?$block['settings_decoded']:array();$defaults=!empty($definition['default_settings'])&&is_array($definition['default_settings'])?$definition['default_settings']:array();
                        foreach($definition['settings_fields'] as $key=>$field){$value=array_key_exists($key,$saved)?$saved[$key]:($defaults[$key]??'');echo '<dt>',$smcFunc['htmlspecialchars']($field['label']??ucfirst(str_replace('_',' ',$key))),'</dt><dd>';if(($field['type']??'text')==='number'){echo '<input class="betterportal-small-number" type="number" name="block_setting_',$smcFunc['htmlspecialchars']($key),'" value="',(int)$value,'" min="',(int)($field['min']??1),'" max="',(int)($field['max']??100),'">';}elseif(($field['type']??'')==='select'){echo '<select name="block_setting_',$smcFunc['htmlspecialchars']($key),'">';foreach(BetterPortal_GetSettingOptions($field) as $ov=>$ol)echo '<option value="',$smcFunc['htmlspecialchars']($ov),'"',(string)$value===(string)$ov?' selected':'','>',$smcFunc['htmlspecialchars']($ol),'</option>';echo '</select>';}else echo '<input type="text" name="block_setting_',$smcFunc['htmlspecialchars']($key),'" value="',$smcFunc['htmlspecialchars']((string)$value),'">';echo '</dd>';}
                    }
                }
                echo '</dl><div class="betterportal-block-actions"><button class="button" name="block_action" value="save">',$txt['save'],'</button><button class="button" name="block_action" value="up" title="',$txt['betterportal_move_up'],'">↑</button><button class="button" name="block_action" value="down" title="',$txt['betterportal_move_down'],'">↓</button><button class="button you_sure" name="block_action" value="delete">',$txt['delete'],'</button></div><input type="hidden" name="id_block" value="',(int)$block['id_block'],'"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><input type="hidden" name="',$context['betterportal-builder_token_var'],'" value="',$context['betterportal-builder_token'],'"></form>';
            }
            echo '</div></section>';
        }
        echo '</div></section>';
    }
    echo '</div><div id="betterportal-sort-status" class="smalltext betterportal-sort-status" aria-live="polite"></div></div>';

    echo '<script>(function(){
        var audience=document.getElementById("betterportal_page_audience"),groups=document.getElementById("betterportal_audience_groups");if(audience&&groups){var rg=function(){groups.hidden=audience.value!=="groups";};audience.addEventListener("change",rg);rg();}
        var builder=document.querySelector(".betterportal-rows-builder");if(!builder||!("draggable" in document.createElement("span")))return;
        var lists=Array.prototype.slice.call(builder.querySelectorAll(".betterportal-block-list[data-droppable=\\"1\\"]")),dragged=null,rowDragged=null,placeholder=document.createElement("div"),rowPlaceholder=document.createElement("div"),status=document.getElementById("betterportal-sort-status");placeholder.className="betterportal-drop-placeholder";rowPlaceholder.className="betterportal-row-drop-placeholder";
        function refreshEmpty(){lists.forEach(function(list){var e=list.querySelector(".betterportal-empty-column"),has=!!list.querySelector(".betterportal-block-editor");if(e)e.hidden=has;});}
        function afterElement(container,y,selector){var els=Array.prototype.slice.call(container.querySelectorAll(selector)),closest={offset:Number.NEGATIVE_INFINITY,element:null};els.forEach(function(el){var box=el.getBoundingClientRect(),off=y-box.top-box.height/2;if(off<0&&off>closest.offset)closest={offset:off,element:el};});return closest.element;}
        function serializeBlocks(){var out={};lists.forEach(function(list){var rid=list.dataset.rowId;if(!out[rid])out[rid]={};out[rid][list.dataset.columnKey]=Array.prototype.map.call(list.querySelectorAll(".betterportal-block-editor"),function(b){return parseInt(b.dataset.blockId,10);});});return out;}
        function refreshBuilder(){window.location.assign(builder.dataset.reorderUrl);}
        function post(kind,value,cb){var p=new URLSearchParams();p.set(kind,"1");p.set(kind==="reorder_blocks"?"block_layout":"row_order",JSON.stringify(value));p.set(builder.dataset.sessionVar,builder.dataset.sessionId);p.set(builder.dataset.tokenVar,builder.dataset.token);if(status)status.textContent=', json_encode($txt['betterportal_order_saving']), ';fetch(builder.dataset.reorderUrl,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8","X-Requested-With":"XMLHttpRequest"},body:p.toString()}).then(function(r){if(!r.ok)throw new Error();return r.json();}).then(function(d){if(!d||!d.success)throw new Error();if(status)status.textContent=', json_encode($txt['betterportal_order_saved']), ';if(cb)cb();}).catch(function(){if(status)status.textContent=', json_encode($txt['betterportal_order_failed']), ';setTimeout(refreshBuilder,900);});}
        builder.addEventListener("dragstart",function(e){var bh=e.target.closest?e.target.closest(".betterportal-drag-handle"):null,rh=e.target.closest?e.target.closest(".betterportal-row-drag-handle"):null;if(bh){dragged=bh.closest(".betterportal-block-editor");if(!dragged)return;dragged.classList.add("betterportal-is-dragging");e.dataTransfer.effectAllowed="move";e.dataTransfer.setData("text/plain",dragged.dataset.blockId||"");}else if(rh){rowDragged=rh.closest(".betterportal-admin-row");if(!rowDragged)return;rowDragged.classList.add("betterportal-row-is-dragging");e.dataTransfer.effectAllowed="move";e.dataTransfer.setData("text/plain","row-"+(rowDragged.dataset.rowId||""));}});
        lists.forEach(function(list){list.addEventListener("dragover",function(e){if(!dragged)return;e.preventDefault();var a=afterElement(list,e.clientY,".betterportal-block-editor:not(.betterportal-is-dragging)");if(a)list.insertBefore(placeholder,a);else list.appendChild(placeholder);});list.addEventListener("drop",function(e){if(!dragged)return;e.preventDefault();var oldRow=dragged.closest(".betterportal-admin-row").dataset.rowId,newRow=list.dataset.rowId;if(placeholder.parentNode===list)list.insertBefore(dragged,placeholder);else list.appendChild(dragged);if(placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);dragged.classList.remove("betterportal-is-dragging");dragged=null;refreshEmpty();post("reorder_blocks",serializeBlocks(),oldRow!==newRow?refreshBuilder:null);});});
        builder.addEventListener("dragover",function(e){if(!rowDragged)return;var target=e.target.closest?e.target.closest(".betterportal-admin-row"):null;if(!target||target===rowDragged)return;e.preventDefault();var box=target.getBoundingClientRect();if(e.clientY<box.top+box.height/2)builder.insertBefore(rowPlaceholder,target);else builder.insertBefore(rowPlaceholder,target.nextSibling);});
        builder.addEventListener("drop",function(e){if(!rowDragged)return;e.preventDefault();if(rowPlaceholder.parentNode===builder)builder.insertBefore(rowDragged,rowPlaceholder);if(rowPlaceholder.parentNode)rowPlaceholder.parentNode.removeChild(rowPlaceholder);rowDragged.classList.remove("betterportal-row-is-dragging");rowDragged=null;var ids=Array.prototype.map.call(builder.querySelectorAll(".betterportal-admin-row"),function(r){return parseInt(r.dataset.rowId,10);});post("reorder_rows",ids,refreshBuilder);});
        builder.addEventListener("dragend",function(){if(placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);if(rowPlaceholder.parentNode)rowPlaceholder.parentNode.removeChild(rowPlaceholder);if(dragged)dragged.classList.remove("betterportal-is-dragging");if(rowDragged)rowDragged.classList.remove("betterportal-row-is-dragging");dragged=null;rowDragged=null;refreshEmpty();});refreshEmpty();
    }());</script>';
}
function template_betterportal_admin_settings(){global $context,$txt,$scripturl;template_betterportal_admin_notice_saved();echo '<form action="',$scripturl,'?action=admin;area=betterportal;sa=settings" method="post"><div class="cat_bar"><h3 class="catbg">',$txt['betterportal_admin_settings'],'</h3></div><div class="windowbg"><dl class="settings"><dt>',$txt['betterportal_mode'],'<br><span class="smalltext">',$txt['betterportal_mode_desc'],'</span></dt><dd><select name="betterportal_mode">';foreach(array('disabled','separate','home') as $m)echo '<option value="',$m,'"',$context['betterportal_mode']===$m?' selected':'','>',$txt['betterportal_mode_'.$m],'</option>';echo '</select></dd><dt>',$txt['betterportal_menu_button'],'</dt><dd><input type="checkbox" name="betterportal_menu_button" value="1"',!empty($context['betterportal_menu_button'])?' checked':'','></dd></dl><button class="button" name="save_settings" value="1">',$txt['save'],'</button><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><input type="hidden" name="',$context['betterportal-settings_token_var'],'" value="',$context['betterportal-settings_token'],'"></div></form>';}
