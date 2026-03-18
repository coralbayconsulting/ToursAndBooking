<?php

//Tour Type

// Add custom columns to the Tour Type post type
add_filter('manage_tour-type_posts_columns', 'set_custom_edit_tour_type_columns');
add_action('manage_tour-type_posts_custom_column', 'custom_tour_type_column', 10, 2);

function set_custom_edit_tour_type_columns($columns) {
  $columns = array(
      'cb' => $columns['cb'],
      'title' => __('Title'),
      'type_code' => __('Code'),
      'listing_description' => __('Listing Description'),
      'listing_sort_order' => __('Sort Order')
  );
  return $columns;
}

function custom_tour_type_column($column, $post_id) {
    switch ($column) {
        case 'type_code':
            $term = get_field('type_code', $post_id);
            if ($term && isset($term->slug)) {
                echo esc_html($term->slug);
            } else {
                echo __('Unknown');
            }
            break;
        case 'listing_description':
            $listing_description = get_field('listing_description', $post_id);
            echo $listing_description ? $listing_description : __('Unknown');
            break;
        case 'listing_sort_order':
            $listing_sort_order = get_field('listing_sort_order', $post_id);
            echo $listing_sort_order ? $listing_sort_order : __('Unknown');
            break;
    }
}

// default sort for tour-types
function sort_tour_type_by_listing_sort_order($query) {
    if (!is_admin() && $query->is_main_query() && is_post_type_archive('tour-type')) {
        $query->set('meta_key', 'listing_sort_order');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'sort_tour_type_by_listing_sort_order');

//Tour

// Add custom columns to the Tour post type
add_filter('manage_tour_posts_columns', 'set_custom_edit_tour_columns');
add_action('manage_tour_posts_custom_column', 'custom_tour_column', 10, 2);

function set_custom_edit_tour_columns($columns) {
  $columns = array(
      'cb' => $columns['cb'],
      'title' => __('Title'),
      'id' => __('ID'),
      'type_code' => __('Type Code'), 
      'listing_description' => __('Listing Description'),
      'listing_sort_order' => __('Sort Order'),
      'short_description' => __('Short Description'),
      'new' => __('New'),
  );
  return $columns;
}

function custom_tour_column($column, $post_id) {
    switch ($column) {
        case 'id':
            echo $post_id;
            break;
        case 'type_code':
            $term = get_field('type_code', $post_id);
            if ($term && isset($term->slug)) {
                echo esc_html($term->slug);
            } else {
                echo __('Unknown');
            }
            break;
        case 'listing_description':
            $listing_description = get_field('listing_description', $post_id);
            echo $listing_description ? $listing_description : __('Unknown');
            break;
        case 'listing_sort_order':
            $listing_sort_order = get_field('listing_sort_order', $post_id);
            echo $listing_sort_order ? $listing_sort_order : __('Unknown');
            break;
        case 'short_description':
            $short_description = get_field('short_description', $post_id);
            echo $short_description ? $short_description : __('Unknown');
            break;
        case 'new':
            $new = get_field('new', $post_id);
            echo '<input type="checkbox" disabled ' . ($new ? 'checked' : '') . '>';
            break;
    }
}

// Make the Tour Type column sortable
add_filter('manage_edit-tour_sortable_columns', 'set_custom_tour_sortable_columns');
function set_custom_tour_sortable_columns($columns) {
    $columns['tour_type'] = 'tour_type';
    return $columns;
}

// Preserve filter parameters when sorting columns for tours
add_filter('views_edit-tour', 'preserve_tour_filters_in_column_headers');
function preserve_tour_filters_in_column_headers($views) {
    // Add JavaScript to modify column header links to preserve filters
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Get current filter parameters
        const urlParams = new URLSearchParams(window.location.search);
        const filterParams = {};
        
        // Preserve important filter parameters
        if (urlParams.get("tour_type_filter")) filterParams.tour_type_filter = urlParams.get("tour_type_filter");
        if (urlParams.get("m")) filterParams.m = urlParams.get("m");
        if (urlParams.get("post_status")) filterParams.post_status = urlParams.get("post_status");
        if (urlParams.get("s")) filterParams.s = urlParams.get("s");
        
        // Modify sortable column header links
        const sortableHeaders = document.querySelectorAll(".manage-column.sortable a, .manage-column.sorted a");
        sortableHeaders.forEach(function(link) {
            const url = new URL(link.href);
            
            // Add filter parameters to the sorting URL
            Object.keys(filterParams).forEach(function(key) {
                url.searchParams.set(key, filterParams[key]);
            });
            
            link.href = url.toString();
        });
    });
    </script>';
    
    return $views;
}

// Handle the sorting of the Tour Type column and set default sort
add_action('pre_get_posts', 'custom_tour_orderby');
function custom_tour_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    
    // Handle Tour Type column sorting
    if ('tour_type' == $orderby) {
        $query->set('meta_key', 'tour_type');
        $query->set('orderby', 'meta_value');
    }
    
    // Set default sort for tours admin list when no specific sorting is applied
    if ($query->get('post_type') === 'tour' && empty($orderby)) {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
        
        // Set a flag to indicate we're using auto-sort for the UI
        if (is_admin() && $query->is_main_query()) {
            set_transient('bst_tours_auto_sorted', true, 60); // 1 minute expiry
        }
    }
    
    // Set default sort for tour-type admin list when no specific sorting is applied
    if ($query->get('post_type') === 'tour-type' && empty($orderby)) {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
        
        // Set a flag to indicate we're using auto-sort for the UI
        if (is_admin() && $query->is_main_query()) {
            set_transient('bst_tour_types_auto_sorted', true, 60); // 1 minute expiry
        }
    }
    
    // Set default sort for source-code admin list when no specific sorting is applied
    if ($query->get('post_type') === 'source-code' && empty($orderby)) {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
        
        // Set a flag to indicate we're using auto-sort for the UI
        if (is_admin() && $query->is_main_query()) {
            set_transient('bst_source_codes_auto_sorted', true, 60); // 1 minute expiry
        }
    }
}

// Add a filter dropdown for the Tour Type column (updated for taxonomy approach)
add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow == 'tour') {
        $selected = isset($_GET['tour_type_filter']) ? $_GET['tour_type_filter'] : '';
        
        // Get tour types that have the tour-type-code taxonomy terms
        $tour_type_terms = get_terms(array(
            'taxonomy' => 'tour-type-code',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        echo '<select name="tour_type_filter">';
        echo '<option value="">' . __('All Tour Types', 'textdomain') . '</option>';
        
        if (!is_wp_error($tour_type_terms) && !empty($tour_type_terms)) {
            foreach ($tour_type_terms as $term) {
                // Get the tour-type post associated with this taxonomy term
                $tour_type_posts = get_posts(array(
                    'post_type' => 'tour-type',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'tour-type-code',
                            'field'    => 'term_id',
                            'terms'    => $term->term_id,
                        ),
                    ),
                    'posts_per_page' => 1
                ));
                
                if (!empty($tour_type_posts)) {
                    $tour_type = $tour_type_posts[0];
                    printf(
                        '<option value="%s"%s>%s</option>',
                        $term->slug,
                        $term->slug == $selected ? ' selected="selected"' : '',
                        $tour_type->post_title
                    );
                }
            }
        }
        echo '</select>';
    }
});

// Filter the tours by the selected Tour Type (fix for taxonomy conflict)
add_filter('pre_get_posts', function($query) {
    global $pagenow;
    $type = 'tour';
    if (
        is_admin() &&
        $pagenow == 'edit.php' &&
        isset($_GET['tour_type_filter']) &&
        $_GET['tour_type_filter'] != '' &&
        isset($query->query_vars['post_type']) &&
        $query->query_vars['post_type'] == $type
    ) {
        // Filter by tour-type-code taxonomy
        $tax_query = isset($query->query_vars['tax_query']) ? $query->query_vars['tax_query'] : array();
        $tax_query[] = array(
            'taxonomy' => 'tour-type-code',
            'field' => 'slug',
            'terms' => $_GET['tour_type_filter']
        );
        $query->set('tax_query', $tax_query);
    }
});

// Update status counts when Tour Type filter is applied
add_filter('wp_count_posts', function($counts, $type, $perm) {
    if ($type === 'tour' && is_admin() && isset($_GET['tour_type_filter']) && $_GET['tour_type_filter'] != '') {
        global $wpdb;
        
        // Get counts for each status with the Tour Type filter applied using taxonomy
        $tour_type_code = sanitize_text_field($_GET['tour_type_filter']);
        
        $query = "
            SELECT p.post_status, COUNT(*) as count 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.post_type = 'tour'
            AND tt.taxonomy = 'tour-type-code'
            AND t.slug = %s
            GROUP BY p.post_status
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $tour_type_code));
        
        // Reset all counts to 0
        foreach ($counts as $status => $count) {
            $counts->$status = 0;
        }
        
        // Update counts based on query results
        foreach ($results as $result) {
            if (property_exists($counts, $result->post_status)) {
                $counts->{$result->post_status} = $result->count;
            }
        }
    }
    
    return $counts;
}, 10, 3);

// Preserve Tour Type filter when clicking status filters
add_filter('views_edit-tour', function($views) {
    if (isset($_GET['tour_type_filter']) && $_GET['tour_type_filter'] != '') {
        $tour_type_code = sanitize_text_field($_GET['tour_type_filter']);
        
        // Rebuild each status filter link to include the tour type parameter
        foreach ($views as $key => $view) {
            // Extract the existing URL from the href attribute
            if (preg_match('/href="([^"]*)"/', $view, $matches)) {
                $url = $matches[1];
                
                // Parse the URL and add our parameter
                $parsed_url = parse_url($url);
                parse_str($parsed_url['query'] ?? '', $query_params);
                
                // Ensure post_type=tour is always included
                $query_params['post_type'] = 'tour';
                
                // Add the tour type filter
                $query_params['tour_type_filter'] = $tour_type_code;
                
                // Rebuild the URL
                $new_url = admin_url('edit.php?' . http_build_query($query_params));
                
                // Replace the href in the view
                $views[$key] = str_replace($url, $new_url, $view);
            }
        }
    }
    
    return $views;
});

// Add sort indicator for auto-sorted title column
add_action('admin_footer', function() {
    global $typenow, $pagenow;
    if ($typenow === 'tour' && $pagenow === 'edit.php' && get_transient('bst_tours_auto_sorted')) {
        // Only show if no explicit sorting is in the URL
        if (empty($_GET['orderby'])) {
            echo '<script>
                jQuery(document).ready(function($) {
                    // First, remove any existing sort classes from all columns
                    $("th.sortable, th.sorted").removeClass("sorted asc desc");
                    
                    // Then add sort indicator only to Title column
                    $(".column-title").addClass("sorted asc");
                });
            </script>';
        }
        delete_transient('bst_tours_auto_sorted');
    }
    
    // Add sort indicator for auto-sorted tour column in tour-date admin
    if ($typenow === 'tour-date' && $pagenow === 'edit.php' && get_transient('bst_tour_dates_auto_sorted')) {
        // Only show if no explicit sorting is in the URL
        if (empty($_GET['orderby'])) {
            echo '<script>
                jQuery(document).ready(function($) {
                    // First, remove any existing sort classes from all columns
                    $("th.sortable, th.sorted").removeClass("sorted asc desc");
                    
                    // Then add sort indicator only to Tour column
                    $(".column-tour").addClass("sorted asc");
                });
            </script>';
        }
        delete_transient('bst_tour_dates_auto_sorted');
    }
    
    // Add sort indicator for auto-sorted title column in tour-type admin
    if ($typenow === 'tour-type' && $pagenow === 'edit.php' && get_transient('bst_tour_types_auto_sorted')) {
        // Only show if no explicit sorting is in the URL
        if (empty($_GET['orderby'])) {
            echo '<script>
                jQuery(document).ready(function($) {
                    // First, remove any existing sort classes from all columns
                    $("th.sortable, th.sorted").removeClass("sorted asc desc");
                    
                    // Then add sort indicator only to Title column
                    $(".column-title").addClass("sorted asc");
                });
            </script>';
        }
        delete_transient('bst_tour_types_auto_sorted');
    }
    
    // Add sort indicator for auto-sorted title column in source-code admin
    if ($typenow === 'source-code' && $pagenow === 'edit.php' && get_transient('bst_source_codes_auto_sorted')) {
        // Only show if no explicit sorting is in the URL
        if (empty($_GET['orderby'])) {
            echo '<script>
                jQuery(document).ready(function($) {
                    // First, remove any existing sort classes from all columns
                    $("th.sortable, th.sorted").removeClass("sorted asc desc");
                    
                    // Then add sort indicator only to Title column
                    $(".column-title").addClass("sorted asc");
                });
            </script>';
        }
        delete_transient('bst_source_codes_auto_sorted');
    }
});

// Add Export to Excel buttons to Tours and Tour Dates admin pages
add_action('admin_footer', function() {
    global $typenow, $pagenow;
    
    // Add export button for Tours
    if ($typenow === 'tour' && $pagenow === 'edit.php') {
        $selected_tour_type = isset($_GET['tour_type_filter']) ? $_GET['tour_type_filter'] : '';
        $nonce = wp_create_nonce('bst_export_tours');
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Create export form for Tours
            var exportForm = $('<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block; margin-left: 10px;">' +
                '<input type="hidden" name="action" value="bst_export_tours_excel">' +
                '<input type="hidden" name="tour_type_filter" value="<?php echo esc_attr($selected_tour_type); ?>">' +
                '<input type="hidden" name="post_status" value="<?php echo esc_attr(isset($_GET['post_status']) ? $_GET['post_status'] : ''); ?>">' +
                '<input type="hidden" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>">' +
                '<input type="hidden" name="orderby" value="<?php echo esc_attr(isset($_GET['orderby']) ? $_GET['orderby'] : ''); ?>">' +
                '<input type="hidden" name="order" value="<?php echo esc_attr(isset($_GET['order']) ? $_GET['order'] : ''); ?>">' +
                '<input type="hidden" name="export_nonce" value="<?php echo $nonce; ?>">' +
                '<button type="submit" class="button button-primary bst-export-button">📊 Export Selection to Excel</button>' +
                '</form>');
            
            // Insert after the Filter button
            var filterButton = $('.tablenav.top .button[value="Filter"]');
            if (filterButton.length) {
                filterButton.after(exportForm);
            } else {
                // Fallback: add after the bulk actions
                $('.tablenav.top .alignleft.actions').first().after(exportForm);
            }
        });
        </script>
        <?php
    }
    
    // Add export button for Tour Dates
    if ($typenow === 'tour-date' && $pagenow === 'edit.php') {
        $selected_tour_filter = isset($_GET['meta_tour_filter']) ? $_GET['meta_tour_filter'] : '';
        $nonce = wp_create_nonce('bst_export_tour_dates');
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Create export form for Tour Dates
            var exportForm = $('<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block; margin-left: 10px;">' +
                '<input type="hidden" name="action" value="bst_export_tour_dates_excel">' +
                '<input type="hidden" name="meta_tour_filter" value="<?php echo esc_attr($selected_tour_filter); ?>">' +
                '<input type="hidden" name="post_status" value="<?php echo esc_attr(isset($_GET['post_status']) ? $_GET['post_status'] : ''); ?>">' +
                '<input type="hidden" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>">' +
                '<input type="hidden" name="orderby" value="<?php echo esc_attr(isset($_GET['orderby']) ? $_GET['orderby'] : ''); ?>">' +
                '<input type="hidden" name="order" value="<?php echo esc_attr(isset($_GET['order']) ? $_GET['order'] : ''); ?>">' +
                '<input type="hidden" name="export_nonce" value="<?php echo $nonce; ?>">' +
                '<button type="submit" class="button button-primary bst-export-button">📊 Export Selection to Excel</button>' +
                '</form>');
            
            // Insert after the Filter button
            var filterButton = $('.tablenav.top .button[value="Filter"]');
            if (filterButton.length) {
                filterButton.after(exportForm);
            } else {
                // Fallback: add after the bulk actions
                $('.tablenav.top .alignleft.actions').first().after(exportForm);
            }
        });
        </script>
        <?php
    }
});

// Disable the date filter for the Tour and Tour Date post types
add_filter('disable_months_dropdown', 'disable_date_filters', 10, 2);
function disable_date_filters($disable, $post_type) {
    if ($post_type === 'tour' || $post_type === 'tour-date' || $post_type === 'tour-type' || $post_type === 'cource-code') {
        return true;
    }
    return $disable;
}

// Suppress Yoast SEO and Readability filters for tour, tour-date, tour-type, and source-code post types
add_action('restrict_manage_posts', function() {
    global $typenow;
    if ($typenow === 'tour' || $typenow === 'tour-date' || $typenow === 'tour-type' || $typenow === 'source-code') {
        // Remove Yoast SEO filters by targeting their specific elements
        echo '<script>
            jQuery(document).ready(function($) {
                // Hide Yoast SEO Score filter
                $("select[name=\"seo_filter\"]").closest("label").hide();
                $("select[name=\"seo_filter\"]").hide();
                
                // Hide Yoast Readability filter  
                $("select[name=\"readability_filter\"]").closest("label").hide();
                $("select[name=\"readability_filter\"]").hide();
                
                // Alternative selectors in case Yoast uses different names
                $("select[id*=\"seo\"], select[id*=\"readability\"]").hide();
                $("select[name*=\"seo\"], select[name*=\"readability\"]").hide();
                
                // Hide any dropdown containing "SEO" or "Readability" in options
                $("select option").each(function() {
                    var text = $(this).text().toLowerCase();
                    if (text.includes("seo") || text.includes("readability")) {
                        $(this).closest("select").hide();
                    }
                });
            });
        </script>';
    }
}, 100); // High priority to run after Yoast adds its filters

// Add tour filter dropdown for tour-date post type
add_action('restrict_manage_posts', function() {
    global $typenow, $wpdb;
    if ($typenow == 'tour-date') {
        $selected = isset($_GET['meta_tour_filter']) ? $_GET['meta_tour_filter'] : '';
        
        // Get all tour IDs that are referenced by tour-dates
        $tour_ids = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'tour' 
            AND meta_value != '' 
            AND meta_value REGEXP '^[0-9]+$'
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tour-date'
            )
        ");
        
        // Get all tours (fallback to all if no tour-dates exist)
        if (!empty($tour_ids)) {
            $tours = get_posts(array(
                'post_type' => 'tour',
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'post__in' => $tour_ids,
                'orderby' => 'title',
                'order' => 'ASC'
            ));
        } else {
            $tours = get_posts(array(
                'post_type' => 'tour',
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'orderby' => 'title',
                'order' => 'ASC'
            ));
        }
        
        echo '<select name="meta_tour_filter">';
        echo '<option value="">' . __('All Tours', 'textdomain') . '</option>';
        foreach ($tours as $tour) {
            printf(
                '<option value="%s"%s>%s</option>',
                $tour->ID,
                $tour->ID == $selected ? ' selected="selected"' : '',
                esc_html($tour->post_title)
            );
        }
        echo '</select>';
    }
}, 99); // Run before Yoast suppression

// Filter the tour-dates by the selected Tour
add_filter('pre_get_posts', function($query) {
    global $pagenow;
    $type = 'tour-date';
    if (
        is_admin() &&
        $pagenow == 'edit.php' &&
        isset($_GET['meta_tour_filter']) &&
        $_GET['meta_tour_filter'] != '' &&
        isset($query->query_vars['post_type']) &&
        $query->query_vars['post_type'] == $type
    ) {
        $meta_query = isset($query->query_vars['meta_query']) ? $query->query_vars['meta_query'] : array();
        $meta_query[] = array(
            'key' => 'tour',
            'value' => $_GET['meta_tour_filter'],
            'compare' => '='
        );
        $query->set('meta_query', $meta_query);
    }
});

// Update status counts when Tour filter is applied to tour-dates
add_filter('wp_count_posts', function($counts, $type, $perm) {
    if ($type === 'tour-date' && is_admin() && isset($_GET['meta_tour_filter']) && $_GET['meta_tour_filter'] != '') {
        global $wpdb;
        
        // Get counts for each status with the Tour filter applied
        $tour_id = intval($_GET['meta_tour_filter']);
        
        $query = "
            SELECT p.post_status, COUNT(*) as count 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tour-date'
            AND pm.meta_key = 'tour'
            AND pm.meta_value = %s
            GROUP BY p.post_status
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $tour_id));
        
        // Create a new counts object to ensure we have all statuses
        $filtered_counts = (object) array();
        
        // Initialize with 0 for all known statuses
        $known_statuses = array('publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft', 'inherit');
        foreach ($known_statuses as $status) {
            $filtered_counts->$status = 0;
        }
        
        // Also initialize any existing counts properties to 0
        foreach ($counts as $status => $count) {
            $filtered_counts->$status = 0;
        }
        
        // Update with actual filtered counts
        foreach ($results as $result) {
            $filtered_counts->{$result->post_status} = intval($result->count);
        }
        
        return $filtered_counts;
    }
    
    return $counts;
}, 10, 3);

// Preserve Tour filter when clicking status filters for tour-dates
add_filter('views_edit-tour-date', function($views) {
    if (isset($_GET['meta_tour_filter']) && $_GET['meta_tour_filter'] != '') {
        $tour_id = sanitize_text_field($_GET['meta_tour_filter']);
        
        // Rebuild each status filter link to include the tour filter parameter
        foreach ($views as $key => $view) {
            // Extract the existing URL from the href attribute
            if (preg_match('/href="([^"]*)"/', $view, $matches)) {
                $url = $matches[1];
                
                // Parse the URL and add our parameter
                $parsed_url = parse_url($url);
                parse_str($parsed_url['query'] ?? '', $query_params);
                
                // Ensure post_type=tour-date is always included
                $query_params['post_type'] = 'tour-date';
                
                // Add the tour filter
                $query_params['meta_tour_filter'] = $tour_id;
                
                // Rebuild the URL
                $new_url = admin_url('edit.php?' . http_build_query($query_params));
                
                // Replace the href in the view
                $views[$key] = str_replace($url, $new_url, $view);
            }
        }
    }
    
    return $views;
});

// Add custom columns to the Tour Date post type
add_filter('manage_tour-date_posts_columns', 'set_custom_edit_tour_date_columns');
add_action('manage_tour-date_posts_custom_column', 'custom_tour_date_column', 10, 2);

function set_custom_edit_tour_date_columns($columns) {
    $columns = array(
        'cb' => $columns['cb'],
        'tour' => __('Tour'),
        'start_date' => __('Start'),
        'end_date' => __('End'),
        'id' => __('ID'),
        'max_slots' => __('Max'),
        'sold_slots' => __('Sold'),
        'offline_sold_slots' => __('Offline'),
        'reserved_slots' => __('Reserved'),
        'available_slots' => __('Available'),
        'actions' => '', // No header text
    );
    return $columns;
}

function custom_tour_date_column($column, $post_id) {
    switch ($column) {
        case 'id':
            echo $post_id;
            break;
        case 'tour':
            $tour_id = get_field('tour', $post_id);
            if ($tour_id) {
                $tour_post = get_post($tour_id);
                echo $tour_post ? $tour_post->post_title : __('Unknown');
            } else {
                echo __('Unknown');
            }
            break;
        case 'start_date':
            $start_date = get_field('start_date', $post_id);
            echo $start_date ? $start_date : __('Unknown');
            break;
        case 'end_date':
            $end_date = get_field('end_date', $post_id);
            echo $end_date ? $end_date : __('Unknown');
            break;
        case 'max_slots':
            $max_slots = get_field('max_slots', $post_id);
            echo ($max_slots !== '' && $max_slots !== null) ? $max_slots : 0;
            break;
        case 'sold_slots':
            $sold_slots = get_field('sold_slots', $post_id);
            echo ($sold_slots !== '' && $sold_slots !== null) ? $sold_slots : 0;
            break;
        case 'offline_sold_slots':
            $offline_sold_slots = get_field('offline_sold_slots', $post_id);
            echo ($offline_sold_slots !== '' && $offline_sold_slots !== null) ? $offline_sold_slots : 0;
            break;
        case 'reserved_slots':
            $reserved_slots = get_field('reserved_slots', $post_id);
            echo ($reserved_slots !== '' && $reserved_slots !== null) ? $reserved_slots : 0;
            break;
        case 'available_slots':
            // First try to use the stored availability value
            $availability = get_field('available_slots', $post_id);
            if ($availability !== '' && $availability !== null) {
                echo $availability;
            } else {
                // Display stored availability value
                echo '0'; // Default if no availability stored
            }
            break;
        case 'actions':
            $edit_url = get_edit_post_link($post_id);
            echo '<a href="' . esc_url($edit_url) . '" class="button view-booking" title="View Tour Date">View</a>';
            break;
    }
}

// Make the Tour, Start Date, and ID columns sortable
add_filter('manage_edit-tour-date_sortable_columns', 'set_custom_tour_date_sortable_columns');
function set_custom_tour_date_sortable_columns($columns) {
    $columns['tour'] = 'tour';
    $columns['start_date'] = 'start_date';
    $columns['id'] = 'id';
    return $columns;
}

// Preserve filter parameters when sorting columns
add_filter('views_edit-tour-date', 'preserve_tour_date_filters_in_column_headers');
function preserve_tour_date_filters_in_column_headers($views) {
    // Add JavaScript to modify column header links to preserve filters
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Get current filter parameters
        const urlParams = new URLSearchParams(window.location.search);
        const filterParams = {};
        
        // Preserve important filter parameters
        if (urlParams.get("meta_tour_filter")) filterParams.meta_tour_filter = urlParams.get("meta_tour_filter");
        if (urlParams.get("tour_type_filter")) filterParams.tour_type_filter = urlParams.get("tour_type_filter");
        if (urlParams.get("m")) filterParams.m = urlParams.get("m");
        if (urlParams.get("post_status")) filterParams.post_status = urlParams.get("post_status");
        if (urlParams.get("s")) filterParams.s = urlParams.get("s");
        
        // Modify sortable column header links
        const sortableHeaders = document.querySelectorAll(".manage-column.sortable a, .manage-column.sorted a");
        sortableHeaders.forEach(function(link) {
            const url = new URL(link.href);
            
            // Add filter parameters to the sorting URL
            Object.keys(filterParams).forEach(function(key) {
                url.searchParams.set(key, filterParams[key]);
            });
            
            link.href = url.toString();
        });
    });
    </script>';
    
    return $views;
}

// Handle the sorting of the Tour, Start Date, and ID columns
add_action('pre_get_posts', 'custom_tour_date_orderby');
function custom_tour_date_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Only apply to tour-date post type
    if ($query->get('post_type') !== 'tour-date') {
        return;
    }

    $orderby = $query->get('orderby');
    
    // Handle specific column sorting
    if ('tour' == $orderby) {
        // Sort by tour title using custom SQL - don't set conflicting meta args
        
        // Add custom ordering by tour title + start date
        add_filter('posts_join', 'custom_tour_date_join_for_tour_sort');
        add_filter('posts_orderby', 'custom_tour_date_orderby_for_tour_sort');
    } elseif ('start_date' == $orderby) {
        // Use custom join and orderby for proper date sorting - don't set conflicting meta args
        
        // Add custom filters for proper date sorting
        add_filter('posts_join', 'custom_tour_date_join_for_start_date_sort');
        add_filter('posts_orderby', 'custom_tour_date_orderby_for_start_date_sort');
    } elseif ('id' == $orderby) {
        // Simple ID sorting
        $query->set('orderby', 'ID');
    }
    
    // Set default sort for tour-date admin list when no specific sorting is applied
    if ($query->get('post_type') === 'tour-date' && empty($orderby)) {
        // Use custom tour sorting for default (tour title + start date)
        add_filter('posts_join', 'custom_tour_date_join_for_tour_sort');
        add_filter('posts_orderby', 'custom_tour_date_orderby_for_tour_sort');
        
        // Set a flag to indicate we're using auto-sort for the UI
        if (is_admin() && $query->is_main_query()) {
            set_transient('bst_tour_dates_auto_sorted', true, 60); // 1 minute expiry
        }
    }
}

// Custom join for tour sorting
function custom_tour_date_join_for_tour_sort($join) {
    global $wpdb;
    
    // Only apply on tour-date admin pages - use more reliable check
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'tour-date') {
        // Join the tour posts table - create our own meta join to be sure
        $join .= " LEFT JOIN {$wpdb->postmeta} AS tour_meta ON {$wpdb->posts}.ID = tour_meta.post_id AND tour_meta.meta_key = 'tour'";
        $join .= " LEFT JOIN {$wpdb->posts} AS tour_posts ON tour_meta.meta_value = tour_posts.ID";
        
        // Also join start_date meta for secondary sorting
        $join .= " LEFT JOIN {$wpdb->postmeta} AS start_date_meta ON {$wpdb->posts}.ID = start_date_meta.post_id AND start_date_meta.meta_key = 'start_date'";
    }
    
    return $join;
}

// Custom orderby for tour sorting
function custom_tour_date_orderby_for_tour_sort($orderby) {
    global $wpdb;
    
    // Only apply on tour-date admin pages - use more reliable check
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'tour-date') {
        $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
        // Sort by tour title first, then by start_date (using CAST for proper date sorting)
        $new_orderby = "tour_posts.post_title {$order}, CAST(start_date_meta.meta_value AS DATE) {$order}";
        
        return $new_orderby;
    }
    
    return $orderby;
}

// Custom orderby for tour title + start_date sorting (used when orderby=title)
function custom_tour_date_orderby_with_start_date($orderby) {
    global $wpdb;
    
    // Only apply on tour-date admin pages - check screen
    $screen = get_current_screen();
    if (is_admin() && $screen && $screen->id === 'edit-tour-date') {
        $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
        // First sort by tour title, then by start_date
        $orderby = "tour_posts.post_title {$order}, start_date_meta.meta_value {$order}";
    }
    
    return $orderby;
}

// Remove the custom filters after the query to avoid affecting other queries
add_action('posts_results', function($posts) {
    remove_filter('posts_join', 'custom_tour_date_join_for_tour_sort');
    remove_filter('posts_orderby', 'custom_tour_date_orderby_for_tour_sort');
    remove_filter('posts_orderby', 'custom_tour_date_orderby_with_start_date');
    return $posts;
});

// Add custom columns to the Source Code post type
add_filter('manage_source-code_posts_columns', 'set_custom_edit_source_code_columns');
add_action('manage_source-code_posts_custom_column', 'custom_source_code_column', 10, 2);

function set_custom_edit_source_code_columns($columns) {
    $columns = array(
        'cb' => $columns['cb'],
        'title' => __('Title'),
        'source' => __('Source'),
        'code' => __('Code'),
    );
    return $columns;
}

function custom_source_code_column($column, $post_id) {
    switch ($column) {
        case 'source':
            $source = get_field('source', $post_id); // Assuming ACF is used for custom fields
            echo $source ? $source : __('Unknown');
            break;
        case 'code':
            $code = get_field('code', $post_id);
            echo $code ? $code : __('Unknown');
            break;
    }
}

// Custom join for start_date sorting
function custom_tour_date_join_for_start_date_sort($join) {
    global $wpdb;
    
    // Only apply on tour-date admin pages - use more reliable check
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'tour-date') {
        // Join start_date meta with a specific alias for sorting
        $join .= " LEFT JOIN {$wpdb->postmeta} AS start_date_sort_meta ON {$wpdb->posts}.ID = start_date_sort_meta.post_id AND start_date_sort_meta.meta_key = 'start_date'";
    }
    
    return $join;
}

// Custom orderby for start_date sorting
function custom_tour_date_orderby_for_start_date_sort($orderby) {
    global $wpdb;
    
    // Only apply on tour-date admin pages - use more reliable check
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'tour-date') {
        $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
        // Use CAST as DATE for more reliable date sorting
        $new_orderby = "CAST(start_date_sort_meta.meta_value AS DATE) {$order}";
        
        return $new_orderby;
    }
    
    return $orderby;
}

// Tour Dates Metabox - Custom Columns
add_filter('acf/prepare_field/name=tour_dates', function($field) {
    $field['wrapper']['class'] .= ' acf-tables';
    return $field;
});