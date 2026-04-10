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

/**
 * Current tour-type filter slug from the Tours list screen (tour-type-code taxonomy).
 *
 * @return string
 */
function bst_tour_get_request_tour_type_filter_slug() {
    if ( empty( $_GET['tour_type_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return '';
    }
    return sanitize_text_field( wp_unslash( $_GET['tour_type_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * Parse query args from a list-table href (edit.php?...).
 *
 * WordPress outputs `&amp;` in HTML attributes; parse_str() on the raw string drops params like `author`
 * (Mine tab), so normalize before parsing.
 *
 * @param string $href Value from href="..." (relative or absolute).
 * @return array<string, string>
 */
function bst_parse_edit_php_href_query_args( $href ) {
    $href = str_replace( '&amp;', '&', $href );
    $href = html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $parts = wp_parse_url( $href );
    $query  = isset( $parts['query'] ) ? $parts['query'] : '';
    $params = array();
    if ( $query !== '' ) {
        parse_str( $query, $params );
    }
    return $params;
}

/**
 * Post statuses that appear in the default admin list table query when no `post_status` filter is set.
 *
 * Matches {@see WP_Query} logic (public OR protected+show_in_admin_all_list, plus private).
 * Statuses registered only with `show_in_admin_all_list` (e.g. some "cancelled" setups) but without
 * `public` or `protected` are excluded from the main list — they only appear on their status tab.
 * Using only `NOT IN ( show_in_admin_all_list => false )` for Mine over-counts those.
 *
 * @return string[]
 */
function bst_wp_admin_default_browse_post_status_slugs() {
    $public   = array_values( (array) get_post_stati( array( 'public' => true ), 'names' ) );
    $prot_adm = array_values(
        (array) get_post_stati(
            array(
                'protected'              => true,
                'show_in_admin_all_list' => true,
            ),
            'names'
        )
    );
    $private = array_values( (array) get_post_stati( array( 'private' => true ), 'names' ) );

    return array_unique( array_merge( $public, $prot_adm, $private ) );
}

/**
 * Per-status counts + "mine" count for tours filtered by tour-type-code, matching core list table logic.
 *
 * @param string $tour_type_slug Taxonomy term slug (tour-type-code).
 * @param string $perm           Same as wp_count_posts second arg ('readable' or '').
 * @return array{statuses: stdClass, mine: int}|null
 */
function bst_tour_get_filtered_tour_counts( $tour_type_slug, $perm = '' ) {
    global $wpdb;

    $tour_type_slug = sanitize_title( $tour_type_slug );
    if ( '' === $tour_type_slug ) {
        return null;
    }

    $private_sql = '';
    if ( 'readable' === $perm && is_user_logged_in() ) {
        $pto = get_post_type_object( 'tour' );
        if ( $pto && ! current_user_can( $pto->cap->read_private_posts ) ) {
            $private_sql = $wpdb->prepare(
                ' AND (p.post_status != "private" OR ( p.post_author = %d AND p.post_status = "private" ))',
                get_current_user_id()
            );
        }
    }

    $base_from = "
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'tour-type-code'
        INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND t.slug = %s
        WHERE p.post_type = 'tour'
        {$private_sql}
    ";

    $query = "
        SELECT p.post_status, COUNT(DISTINCT p.ID) AS count
        {$base_from}
        GROUP BY p.post_status
    ";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- base_from contains prepare placeholder for slug only when no private_sql; see below.
    $prepared = $wpdb->prepare( $query, $tour_type_slug );
    $results  = $wpdb->get_results( $prepared );

    $statuses = (object) array_fill_keys( array_keys( get_post_stati() ), 0 );
    foreach ( $results as $row ) {
        if ( isset( $statuses->{$row->post_status} ) ) {
            $statuses->{$row->post_status} = (int) $row->count;
        }
    }

    // "Mine" must match rows the default admin list would return (see bst_wp_admin_default_browse_post_status_slugs).
    $browse_slugs = bst_wp_admin_default_browse_post_status_slugs();
    $browse_in    = "'" . implode( "','", array_map( 'esc_sql', $browse_slugs ) ) . "'";
    $mine_sql     = "
        SELECT COUNT(DISTINCT p.ID)
        {$base_from}
        AND p.post_author = %d
        AND p.post_status IN ( {$browse_in} )
    ";
    $mine         = (int) $wpdb->get_var( $wpdb->prepare( $mine_sql, $tour_type_slug, get_current_user_id() ) );

    return array(
        'statuses' => $statuses,
        'mine'     => $mine,
    );
}

/**
 * Total for the "All" subview: sum of status counts minus statuses hidden from the All list (core behavior).
 *
 * @param stdClass $counts Per-status counts object.
 * @return int
 */
function bst_tour_all_total_from_status_counts( $counts ) {
    $total = array_sum( (array) $counts );
    foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
        if ( isset( $counts->$state ) ) {
            $total -= (int) $counts->$state;
        }
    }
    return (int) $total;
}

/**
 * Tour Date list: selected tour ID from meta_tour_filter (ACF "tour" field on tour-date).
 *
 * @return int
 */
function bst_tour_date_get_request_meta_tour_filter_id() {
    if ( empty( $_GET['meta_tour_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return 0;
    }
    return absint( wp_unslash( $_GET['meta_tour_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * Per-status + Mine counts for tour-date posts linked to a given tour (meta key `tour`).
 *
 * @param int    $tour_id Tour post ID.
 * @param string $perm    Same as wp_count_posts second arg ('readable' or '').
 * @return array{statuses: stdClass, mine: int}|null
 */
function bst_tour_date_get_filtered_counts( $tour_id, $perm = '' ) {
    global $wpdb;

    $tour_id = absint( $tour_id );
    if ( $tour_id <= 0 ) {
        return null;
    }

    // ACF / post object fields often store the ID as a string in postmeta.
    $tour_meta = (string) $tour_id;

    $private_sql = '';
    if ( 'readable' === $perm && is_user_logged_in() ) {
        $pto = get_post_type_object( 'tour-date' );
        if ( $pto && ! current_user_can( $pto->cap->read_private_posts ) ) {
            $private_sql = $wpdb->prepare(
                ' AND (p.post_status != "private" OR ( p.post_author = %d AND p.post_status = "private" ))',
                get_current_user_id()
            );
        }
    }

    $join = "
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        AND pm.meta_key = 'tour'
        AND pm.meta_value = %s
    ";

    $query = "
        SELECT p.post_status, COUNT(DISTINCT p.ID) AS count
        FROM {$wpdb->posts} p
        {$join}
        WHERE p.post_type = 'tour-date'
        {$private_sql}
        GROUP BY p.post_status
    ";

    $prepared = $wpdb->prepare( $query, $tour_meta );
    $results  = $wpdb->get_results( $prepared );

    $statuses = (object) array_fill_keys( array_keys( get_post_stati() ), 0 );
    foreach ( $results as $row ) {
        if ( isset( $statuses->{$row->post_status} ) ) {
            $statuses->{$row->post_status} = (int) $row->count;
        }
    }

    $browse_slugs = bst_wp_admin_default_browse_post_status_slugs();
    $browse_in    = "'" . implode( "','", array_map( 'esc_sql', $browse_slugs ) ) . "'";

    $mine_sql = "
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        {$join}
        WHERE p.post_type = 'tour-date'
        {$private_sql}
        AND p.post_author = %d
        AND p.post_status IN ( {$browse_in} )
    ";

    $mine = (int) $wpdb->get_var( $wpdb->prepare( $mine_sql, $tour_meta, get_current_user_id() ) );

    return array(
        'statuses' => $statuses,
        'mine'     => $mine,
    );
}

// Update status counts when Tour Type filter is applied.
add_filter(
    'wp_count_posts',
    function ( $counts, $type, $perm ) {
        if ( 'tour' !== $type || ! is_admin() ) {
            return $counts;
        }
        $slug = bst_tour_get_request_tour_type_filter_slug();
        if ( '' === $slug ) {
            return $counts;
        }

        $data = bst_tour_get_filtered_tour_counts( $slug, $perm );
        if ( null === $data ) {
            return $counts;
        }

        foreach ( $counts as $status => $_c ) {
            $counts->$status = 0;
        }
        foreach ( $data['statuses'] as $status => $num ) {
            if ( property_exists( $counts, $status ) ) {
                $counts->$status = (int) $num;
            }
        }

        return $counts;
    },
    10,
    3
);

// Preserve Tour Type filter when clicking status filters; fix subsubsub numbers (incl. Mine) when filtered.
add_filter(
    'views_edit-tour',
    function ( $views ) {
        $slug = bst_tour_get_request_tour_type_filter_slug();
        if ( '' !== $slug ) {
            foreach ( $views as $key => $view ) {
                if ( preg_match( '/href="([^"]*)"/', $view, $matches ) ) {
                    $url = $matches[1];

                    $query_params = bst_parse_edit_php_href_query_args( $url );

                    $query_params['post_type']        = 'tour';
                    $query_params['tour_type_filter'] = $slug;

                    $new_url       = admin_url( 'edit.php?' . http_build_query( $query_params ) );
                    $views[ $key ] = str_replace( $url, $new_url, $view );
                }
            }

            // Patch counts in labels — same logic as views_edit-tour-date (All/Mine + any status tab, incl. custom).
            $data = bst_tour_get_filtered_tour_counts( $slug, 'readable' );
            if ( ! $data ) {
                return $views;
            }

            $s = $data['statuses'];
            foreach ( $views as $vkey => $html ) {
                if ( false === strpos( $html, '(' ) ) {
                    continue;
                }
                if ( 'all' === $vkey ) {
                    $num = bst_tour_all_total_from_status_counts( $s );
                } elseif ( 'mine' === $vkey ) {
                    $num = (int) $data['mine'];
                } elseif ( isset( $s->{$vkey} ) ) {
                    $num = (int) $s->{$vkey};
                } else {
                    continue;
                }
                $views[ $vkey ] = preg_replace(
                    '/\(\s*[\d,]+\s*\)/',
                    '(' . number_format_i18n( $num ) . ')',
                    $html,
                    1
                );
            }
        }

        return $views;
    },
    10
);

/**
 * Pass list filter into post edit URLs so "Record X of Y" and prev/next use the same subset (server-side, no JS required).
 */
add_filter(
    'get_edit_post_link',
    function ( $link, $post_id, $context ) {
        if ( 'display' !== $context || ! is_admin() || ! $link ) {
            return $link;
        }
        $post = get_post( (int) $post_id );
        if ( ! $post ) {
            return $link;
        }
        if ( 'tour' === $post->post_type ) {
            $slug = bst_tour_get_request_tour_type_filter_slug();
            if ( '' !== $slug ) {
                $link = add_query_arg( 'filter_tour_type', $slug, $link );
            }
        }
        if ( 'tour-date' === $post->post_type && ! empty( $_GET['meta_tour_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $link = add_query_arg(
                'filter_tour',
                sanitize_text_field( wp_unslash( $_GET['meta_tour_filter'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $link
            );
        }
        // Mine tab uses ?author=ID on the list; use filter_author on post.php so nav matches.
        if ( in_array( $post->post_type, array( 'tour', 'tour-date' ), true ) && ! empty( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $aid = absint( $_GET['author'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $aid ) {
                $link = add_query_arg( 'filter_author', $aid, $link );
            }
        }
        return $link;
    },
    10,
    3
);

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

// Vehicle (normalized inventory entity)
add_action('init', function () {
    register_post_type('vehicle', array(
        'labels' => array(
            'name' => __('Vehicles'),
            'singular_name' => __('Vehicle'),
            'add_new_item' => __('Add New Vehicle'),
            'edit_item' => __('Edit Vehicle'),
            'new_item' => __('New Vehicle'),
            'view_item' => __('View Vehicle'),
            'search_items' => __('Search Vehicles'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'bst-plugin',
        'menu_position' => 26,
        'menu_icon' => 'dashicons-car',
        'supports' => array('title'),
        'has_archive' => false,
        'rewrite' => false,
        'show_in_rest' => false,
    ));
}, 20);

// Vehicle admin list helpers + columns, filter, and default title sort.

/**
 * Map Vehicle CPT post ID → tours that reference it in Tour → vehicle_pricing → vehicles → Vehicle (CPT).
 *
 * Uses raw field values (`get_field` format=false) so post_object stores as scalar IDs (same as migration).
 * No booking table or repeater text-label fallbacks — the linked CPT id is the source of truth.
 *
 * @return array<int, array<int, string>> vehicle_post_id => [ tour_post_id => tour_title, ... ]
 */
function bst_vehicle_usage_map() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cache = array();

	if ( ! function_exists( 'get_field' ) ) {
		return $cache;
	}

	$tour_ids = get_posts(
		array(
			'post_type'      => 'tour',
			'post_status'    => function_exists( 'bst_post_statuses_for_admin_scan' ) ? bst_post_statuses_for_admin_scan( 'tour' ) : 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $tour_ids as $tour_id ) {
		$tour_id    = (int) $tour_id;
		$tour_title = get_the_title( $tour_id );
		$pricing    = get_field( 'vehicle_pricing', $tour_id, false );
		if ( empty( $pricing ) || ! is_array( $pricing ) ) {
			continue;
		}

		foreach ( $pricing as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$nested_rows = array();
			if ( function_exists( 'bst_vehicle_migration_get_nested_vehicle_rows' ) ) {
				$nested_rows = bst_vehicle_migration_get_nested_vehicle_rows( $row );
			} elseif ( ! empty( $row['vehicles'] ) && is_array( $row['vehicles'] ) ) {
				$nested_rows = $row['vehicles'];
			}
			if ( empty( $nested_rows ) ) {
				continue;
			}
			foreach ( $nested_rows as $vrow ) {
				if ( ! is_array( $vrow ) ) {
					continue;
				}

				$linked_id = 0;
				if ( function_exists( 'bst_vehicle_migration_row_linked_post_id' ) ) {
					$linked_id = bst_vehicle_migration_row_linked_post_id( $vrow );
				} else {
					$lid = isset( $vrow['vehicle_id'] ) ? $vrow['vehicle_id'] : 0;
					if ( is_array( $lid ) && isset( $lid['ID'] ) ) {
						$linked_id = (int) $lid['ID'];
					} elseif ( is_object( $lid ) && isset( $lid->ID ) ) {
						$linked_id = (int) $lid->ID;
					} else {
						$linked_id = (int) $lid;
					}
				}

				if ( $linked_id <= 0 ) {
					continue;
				}
				$vp = get_post( $linked_id );
				if ( ! $vp || 'vehicle' !== $vp->post_type ) {
					continue;
				}
				if ( empty( $cache[ $linked_id ] ) ) {
					$cache[ $linked_id ] = array();
				}
				$cache[ $linked_id ][ $tour_id ] = $tour_title;
			}
		}
	}

	return $cache;
}

/**
 * Tours that list this vehicle via linked CPT in vehicle_pricing (see bst_vehicle_usage_map).
 *
 * @param int $vehicle_id Vehicle post ID.
 * @return array<int, string> tour_id => tour title
 */
function bst_vehicle_usage_for_post( $vehicle_id ) {
	$vehicle_id = (int) $vehicle_id;
	$map        = bst_vehicle_usage_map();
	$usage      = ( $vehicle_id > 0 && ! empty( $map[ $vehicle_id ] ) ) ? $map[ $vehicle_id ] : array();
	asort( $usage, SORT_NATURAL | SORT_FLAG_CASE );
	return $usage;
}

/**
 * Admin URL to edit a BST tour booking (custom admin page).
 *
 * @param int $booking_id Booking row id.
 * @return string
 */
function bst_vehicle_admin_booking_edit_url( $booking_id ) {
    return add_query_arg(
        array(
            'page'   => 'bst-tour-bookings',
            'action' => 'edit',
            'id'     => (int) $booking_id,
        ),
        admin_url( 'admin.php' )
    );
}

/**
 * Map vehicle post ID => sorted list of booking IDs referencing that vehicle (vehicle1_id or vehicle2_id).
 * One query per request (cached) for the Vehicles list screen.
 *
 * @return array<int, int[]>
 */
function bst_vehicle_bookings_by_vehicle_map() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    global $wpdb;
    $cache = array();
    $table = $wpdb->prefix . 'bst_tour_booking';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefixed, not user input.
    $rows = $wpdb->get_results( "SELECT id, vehicle1_id, vehicle2_id FROM {$table} WHERE vehicle1_id > 0 OR vehicle2_id > 0", ARRAY_A );
    if ( empty( $rows ) || ! is_array( $rows ) ) {
        return $cache;
    }

    foreach ( $rows as $row ) {
        $bid = isset( $row['id'] ) ? (int) $row['id'] : 0;
        if ( $bid <= 0 ) {
            continue;
        }
        $v1 = isset( $row['vehicle1_id'] ) ? (int) $row['vehicle1_id'] : 0;
        $v2 = isset( $row['vehicle2_id'] ) ? (int) $row['vehicle2_id'] : 0;
        if ( $v1 > 0 ) {
            if ( empty( $cache[ $v1 ] ) ) {
                $cache[ $v1 ] = array();
            }
            $cache[ $v1 ][ $bid ] = $bid;
        }
        if ( $v2 > 0 && $v2 !== $v1 ) {
            if ( empty( $cache[ $v2 ] ) ) {
                $cache[ $v2 ] = array();
            }
            $cache[ $v2 ][ $bid ] = $bid;
        }
    }

    foreach ( $cache as $vid => $ids ) {
        $cache[ $vid ] = array_values( $ids );
        sort( $cache[ $vid ], SORT_NUMERIC );
    }

    return $cache;
}

/**
 * @param int $vehicle_id Vehicle CPT id.
 * @return int[]
 */
function bst_vehicle_booking_ids_for_vehicle( $vehicle_id ) {
    $vehicle_id = (int) $vehicle_id;
    if ( $vehicle_id <= 0 ) {
        return array();
    }
    $map = bst_vehicle_bookings_by_vehicle_map();
    return isset( $map[ $vehicle_id ] ) ? $map[ $vehicle_id ] : array();
}

add_filter(
    'manage_vehicle_posts_columns',
    function ( $columns ) {
        $ordered = array();
        if ( isset( $columns['cb'] ) ) {
            $ordered['cb'] = $columns['cb'];
        }
        $ordered['bst_vehicle_post_id'] = __( 'ID' );
        if ( isset( $columns['title'] ) ) {
            $ordered['title'] = $columns['title'];
        }
        $ordered['bst_vehicle_type']   = __( 'Type' );
        $ordered['bst_vehicle_active'] = __( 'Available To Assign' );
        $ordered['bst_vehicle_limited'] = __( 'Limited' );
        $ordered['bst_vehicle_tours']    = __( 'On Tours' );
        $ordered['bst_vehicle_bookings'] = __( 'Used on Booking' );
        if ( isset( $columns['date'] ) ) {
            $ordered['date'] = $columns['date'];
        }
        foreach ( $columns as $key => $label ) {
            if ( isset( $ordered[ $key ] ) ) {
                continue;
            }
            $ordered[ $key ] = $label;
        }
        return $ordered;
    }
);

add_action(
    'manage_vehicle_posts_custom_column',
    function ( $column, $post_id ) {
        if ( 'bst_vehicle_post_id' === $column ) {
            echo (int) $post_id;
            return;
        }

        if ( 'bst_vehicle_bookings' === $column ) {
            $booking_ids = bst_vehicle_booking_ids_for_vehicle( (int) $post_id );
            if ( empty( $booking_ids ) ) {
                echo '&mdash;';
                return;
            }
            $max_show = 8;
            $slice    = array_slice( $booking_ids, 0, $max_show );
            $parts    = array();
            foreach ( $slice as $bid ) {
                $bid  = (int) $bid;
                $url  = bst_vehicle_admin_booking_edit_url( $bid );
                $parts[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">#' . esc_html( (string) $bid ) . '</a>';
            }
            $out = implode( ', ', $parts );
            if ( count( $booking_ids ) > $max_show ) {
                $out .= ' +' . ( count( $booking_ids ) - $max_show ) . ' ' . esc_html__( 'more' );
            }
            echo wp_kses(
                $out,
                array(
                    'a' => array(
                        'href'   => array(),
                        'target' => array(),
                        'rel'    => array(),
                    ),
                )
            );
            return;
        }

        if ( ! function_exists( 'get_field' ) ) {
            echo '&mdash;';
            return;
        }

        if ( 'bst_vehicle_type' === $column ) {
            $t = get_field( 'vehicle_type', $post_id );
            if ( 'motorcycle' === $t ) {
                esc_html_e( 'Motorcycle' );
            } elseif ( 'car' === $t ) {
                esc_html_e( 'Car' );
            } else {
                echo '&mdash;';
            }
            return;
        }

        if ( 'bst_vehicle_active' === $column ) {
            $active = get_field( 'vehicle_active', $post_id );
            echo $active ? esc_html__( 'Yes' ) : esc_html__( 'No' );
            return;
        }

        if ( 'bst_vehicle_limited' === $column ) {
            $lim = get_field( 'vehicle_usually_limited', $post_id );
            echo $lim ? esc_html__( 'Yes' ) : esc_html__( 'No' );
            return;
        }

        if ( 'bst_vehicle_tours' === $column ) {
            $usage = bst_vehicle_usage_for_post( $post_id );
            if ( empty( $usage ) ) {
                echo '&mdash;';
                return;
            }
            $max_show = 4;
            $count    = count( $usage );
            $i        = 0;
            $parts    = array();
            foreach ( $usage as $tour_id => $tour_title ) {
                if ( $i >= $max_show ) {
                    break;
                }
                $tour_id = (int) $tour_id;
                $url     = get_edit_post_link( $tour_id, 'raw' );
                if ( $url ) {
                    $parts[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $tour_title ) . '</a>';
                } else {
                    $parts[] = esc_html( $tour_title );
                }
                ++$i;
            }
            $out = implode( ', ', $parts );
            if ( $count > $max_show ) {
                $out .= ' +' . ( $count - $max_show ) . ' ' . esc_html__( 'more' );
            }
            echo wp_kses(
                $out,
                array(
                    'a' => array(
                        'href'   => array(),
                        'target' => array(),
                        'rel'    => array(),
                    ),
                )
            );
            return;
        }

        if ( 'bst_vehicle_type' !== $column && 'bst_vehicle_active' !== $column && 'bst_vehicle_limited' !== $column && 'bst_vehicle_tours' !== $column && 'bst_vehicle_bookings' !== $column ) {
            return;
        }
    },
    10,
    2
);

add_action(
    'pre_get_posts',
    function ( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        global $pagenow;
        if ( 'edit.php' !== $pagenow || empty( $_GET['post_type'] ) || 'vehicle' !== $_GET['post_type'] ) {
            return;
        }

        if ( empty( $_GET['orderby'] ) ) {
            $query->set( 'orderby', 'title' );
            $query->set( 'order', 'ASC' );
        }

        if ( empty( $_GET['bst_vehicle_type'] ) ) {
            return;
        }
        $t = sanitize_text_field( wp_unslash( $_GET['bst_vehicle_type'] ) );
        if ( ! in_array( $t, array( 'car', 'motorcycle' ), true ) ) {
            return;
        }
        $meta_query   = $query->get( 'meta_query' );
        $meta_query   = is_array( $meta_query ) ? $meta_query : array();
        $meta_query[] = array(
            'key'   => 'vehicle_type',
            'value' => $t,
        );
        $query->set( 'meta_query', $meta_query );
    }
);

add_action(
    'restrict_manage_posts',
    function () {
        global $typenow;
        if ( 'vehicle' !== $typenow ) {
            return;
        }
        $sel = isset( $_GET['bst_vehicle_type'] ) ? sanitize_text_field( wp_unslash( $_GET['bst_vehicle_type'] ) ) : '';
        echo '<select name="bst_vehicle_type" id="bst_vehicle_type">';
        echo '<option value="">' . esc_html__( 'All types' ) . '</option>';
        printf(
            '<option value="car"%s>%s</option>',
            selected( $sel, 'car', false ),
            esc_html__( 'Car' )
        );
        printf(
            '<option value="motorcycle"%s>%s</option>',
            selected( $sel, 'motorcycle', false ),
            esc_html__( 'Motorcycle' )
        );
        echo '</select>';
    }
);

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

// Update status counts when Tour filter is applied to tour-dates (matches list query + Mine tab).
add_filter(
    'wp_count_posts',
    function ( $counts, $type, $perm ) {
        if ( 'tour-date' !== $type || ! is_admin() ) {
            return $counts;
        }
        $tid = bst_tour_date_get_request_meta_tour_filter_id();
        if ( $tid <= 0 ) {
            return $counts;
        }

        $data = bst_tour_date_get_filtered_counts( $tid, $perm );
        if ( null === $data ) {
            return $counts;
        }

        foreach ( $counts as $status => $_c ) {
            $counts->$status = 0;
        }
        foreach ( $data['statuses'] as $status => $num ) {
            if ( property_exists( $counts, $status ) ) {
                $counts->$status = (int) $num;
            }
        }

        return $counts;
    },
    10,
    3
);

// Preserve Tour filter when clicking status filters; fix subsubsub counts (incl. Mine + custom statuses).
add_filter(
    'views_edit-tour-date',
    function ( $views ) {
        $tour_id = bst_tour_date_get_request_meta_tour_filter_id();
        if ( $tour_id <= 0 ) {
            return $views;
        }

        foreach ( $views as $key => $view ) {
            if ( preg_match( '/href="([^"]*)"/', $view, $matches ) ) {
                $url           = $matches[1];
                $query_params  = bst_parse_edit_php_href_query_args( $url );
                $query_params['post_type']        = 'tour-date';
                $query_params['meta_tour_filter'] = $tour_id;
                $new_url                         = admin_url( 'edit.php?' . http_build_query( $query_params ) );
                $views[ $key ]                   = str_replace( $url, $new_url, $view );
            }
        }

        $data = bst_tour_date_get_filtered_counts( $tour_id, 'readable' );
        if ( ! $data ) {
            return $views;
        }

        $s = $data['statuses'];
        foreach ( $views as $vkey => $html ) {
            if ( false === strpos( $html, '(' ) ) {
                continue;
            }
            if ( 'all' === $vkey ) {
                $num = bst_tour_all_total_from_status_counts( $s );
            } elseif ( 'mine' === $vkey ) {
                $num = (int) $data['mine'];
            } elseif ( isset( $s->{$vkey} ) ) {
                $num = (int) $s->{$vkey};
            } else {
                continue;
            }
            $views[ $vkey ] = preg_replace(
                '/\(\s*[\d,]+\s*\)/',
                '(' . number_format_i18n( $num ) . ')',
                $html,
                1
            );
        }

        return $views;
    },
    10
);

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
