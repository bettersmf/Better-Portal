<?php
/** Better Portal public template. @version 0.1.0-dev32i */

function template_betterportal_page()
{
    global $context, $smcFunc;

    $page = $context['betterportal_page'];
    $shell_class = !empty($context['betterportal_shell_mode']) ? ' betterportal-shell-' . preg_replace('~[^a-z0-9_-]~i', '', (string) $context['betterportal_shell_mode']) : '';
    echo '<div id="betterportal" class="betterportal', $shell_class, '">';

    if (!empty($page['show_title']))
        echo '<div class="cat_bar betterportal-page-header"><h2 class="catbg">', $smcFunc['htmlspecialchars']($page['title']), '</h2></div>';

    foreach ($context['betterportal_rows'] as $row)
    {
        $layout_class = 'betterportal-layout-' . str_replace('-', '_', $row['layout']);
        $width_class = BetterPortal_ColumnWidthClass(BetterPortal_EffectiveColumnWidthPreset($row));
        $custom = preg_replace('~[^a-zA-Z0-9 _-]~', '', (string) $row['css_class']);
        $custom = preg_replace('~(?:^|\s)betterportal-width-(?:equal|wide-left|wide-center|wide-right)(?=\s|$)~', ' ', $custom);
        $custom = trim(preg_replace('~\s+~', ' ', $custom));
        echo '<div class="betterportal-row ', $layout_class, ' ', $width_class, $custom !== '' ? ' ' . $smcFunc['htmlspecialchars']($custom) : '', '">';

        foreach ($row['columns'] as $column_key => $blocks)
        {
            $is_forum_main = !empty($row['forum_mode']) && $column_key === $row['forum_main_column'];
            echo '<div class="betterportal-column betterportal-', $smcFunc['htmlspecialchars']($column_key), $is_forum_main ? ' betterportal-forum-main' : '', '">';

            if ($is_forum_main)
            {
                echo '<div class="betterportal-embedded-forum">';
                if (!empty($context['betterportal_embedded_forum_ready']) && function_exists('template_main'))
                    template_main();
                else
                    echo '<div class="windowbg">', $smcFunc['htmlspecialchars']($context['betterportal_forum_unavailable'] ?? 'Forum index unavailable.'), '</div>';
                echo '</div>';
            }
            else
            {
                foreach ($blocks as $block)
                {
                    $is_native_block = !empty($context['betterportal_embedded_native_ready'])
                        && !empty($context['betterportal_embedded_block_id'])
                        && (int) $context['betterportal_embedded_block_id'] === (int) $block['id_block'];

                    echo '<section id="betterportal-block-', (int) $block['id_block'], '" class="betterportal-block', $is_native_block ? ' betterportal-block-native-shell' : '', '">';
                    if (!empty($block['show_title']))
                    {
                        echo '<div class="cat_bar betterportal-block-header"><div class="betterportal-block-header-inner"><h3 class="catbg">', $smcFunc['htmlspecialchars']($block['title']), '</h3>';
                        if ($is_native_block && !empty($context['betterportal_block_return']['href']) && !empty($context['betterportal_block_return']['label']))
                            echo '<a class="betterportal-block-return" href="', $smcFunc['htmlspecialchars']($context['betterportal_block_return']['href']), '">&larr; ', $smcFunc['htmlspecialchars']($context['betterportal_block_return']['label']), '</a>';
                        elseif (!empty($block['rendered']['header_action']))
                            echo $block['rendered']['header_action'];
                        echo '</div></div>';
                    }
                    echo '<div class="betterportal-block-body windowbg', !empty($block['show_title']) ? ' betterportal-block-body-titled' : '', $is_native_block ? ' betterportal-native-block-body' : '', '">';
                    if (!empty($block['rendered']['body_action']))
                        echo '<div class="betterportal-block-body-actions">', $block['rendered']['body_action'], '</div>';
                    if (empty($block['show_title']) && !empty($block['rendered']['header_action']))
                        echo '<div class="betterportal-block-return-fallback">', $block['rendered']['header_action'], '</div>';
                    if ($is_native_block && function_exists('template_main'))
                    {
                        // If a block title is hidden, keep the return control
                        // available as a compact top-right action in the body.
                        if (empty($block['show_title']) && !empty($context['betterportal_block_return']['href']) && !empty($context['betterportal_block_return']['label']))
                            echo '<div class="betterportal-block-return-fallback"><a class="button betterportal-block-return" href="', $smcFunc['htmlspecialchars']($context['betterportal_block_return']['href']), '">&larr; ', $smcFunc['htmlspecialchars']($context['betterportal_block_return']['label']), '</a></div>';

                        ob_start();
                        template_main();
                        $native_html = ob_get_clean();
                        echo BetterPortal_RewriteNativeBlockHtml($native_html, $context['betterportal_embedded_block_shell'] ?? array());
                    }
                    else
                        echo $block['rendered']['body'];
                    echo '</div></section>';
                }
            }
            echo '</div>';
        }
        echo '</div>';
    }

    echo '</div>';
}
