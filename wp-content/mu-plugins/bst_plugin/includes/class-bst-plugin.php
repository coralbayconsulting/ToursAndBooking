<?php
class BST_Plugin {
    private static $instance = null;

    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Register hooks
        $this->register_hooks();
        
        // Initialize email system components
        $this->init_email_system();
    }

    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_dependencies() {
        require_once BST_PLUGIN_DIR . 'includes/functions.php';
        require_once BST_PLUGIN_DIR . 'includes/database/create-tables.php';
        require_once BST_PLUGIN_DIR . 'includes/database/database-utils.php';
        require_once BST_PLUGIN_DIR . 'includes/database/tour-booking-actions.php'; 
        require_once BST_PLUGIN_DIR . 'includes/custom-post-types.php';
        require_once BST_PLUGIN_DIR . 'includes/vehicle-helpers.php';
        require_once BST_PLUGIN_DIR . 'includes/tour-date-limited-vehicles.php';
        require_once BST_PLUGIN_DIR . 'includes/vehicle-migration.php';
        require_once BST_PLUGIN_DIR . 'includes/vehicle-tour-names-export.php';
        require_once BST_PLUGIN_DIR . 'includes/vehicle-booking-remap.php';
        require_once BST_PLUGIN_DIR . 'includes/rating-helpers.php';
        require_once BST_PLUGIN_DIR . 'includes/dashboard-helpers.php';
        require_once BST_PLUGIN_DIR . 'includes/tour-type-helpers.php';
        require_once BST_PLUGIN_DIR . 'includes/tour-structured-data.php';
        require_once BST_PLUGIN_DIR . 'includes/tour-breadcrumbs.php';
        require_once BST_PLUGIN_DIR . 'includes/tour-date-helpers.php';
        require_once BST_PLUGIN_DIR . 'includes/share-helpers.php';
        require_once BST_PLUGIN_DIR . 'includes/gravity-forms.php';
        require_once BST_PLUGIN_DIR . 'includes/booking-display.php';
        require_once BST_PLUGIN_DIR . 'includes/data-import-handlers.php';
        require_once BST_PLUGIN_DIR . 'includes/booking-payment-status.php';
        require_once BST_PLUGIN_DIR . 'includes/data-export-handlers.php';
        require_once BST_PLUGIN_DIR . 'includes/xlsx-export-handler.php';
        require_once BST_PLUGIN_DIR . 'includes/admin-settings.php'; // Include admin settings
        require_once BST_PLUGIN_DIR . 'includes/class-bst-notifications.php'; // Include notifications system
        require_once BST_PLUGIN_DIR . 'includes/user-notification-settings.php'; // Include user notification settings
        require_once BST_PLUGIN_DIR . 'includes/exchange-rates.php'; // Include exchange rates functionality
        require_once BST_PLUGIN_DIR . 'includes/tools-helpers.php';  // Tools page logic

        // Include email system
        require_once BST_PLUGIN_DIR . 'includes/email-system/class-email-manager.php';
        require_once BST_PLUGIN_DIR . 'includes/email-system/class-email-merge-fields.php';
        require_once BST_PLUGIN_DIR . 'includes/email-system/class-email-log-viewer.php';
        require_once BST_PLUGIN_DIR . 'includes/email-system/class-email-automation.php';
        require_once BST_PLUGIN_DIR . 'includes/email-system/class-gmail-api.php';
        require_once BST_PLUGIN_DIR . 'includes/email-system/default-templates.php';

    }

    private function register_hooks() {
        add_action('init', array($this, 'register_tour_type_code_taxonomy'));
        // Remove default taxonomy meta boxes for tour-type-code and tour-rating on tour edit screen
        add_action('add_meta_boxes', function() {
            remove_meta_box('tagsdiv-tour-type-code', 'tour', 'side');
            remove_meta_box('tagsdiv-tour-type-code', 'tour', 'normal');
            remove_meta_box('tagsdiv-tour-rating', 'tour', 'side');
            remove_meta_box('tagsdiv-tour-rating', 'tour', 'normal');
            remove_meta_box('tour-ratingdiv', 'tour', 'side');
            remove_meta_box('tour-ratingdiv', 'tour', 'normal');
        }, 99);
        add_action('init', array($this, 'register_custom_post_types'));
        add_action('init', array($this, 'setup_bst_capabilities'));
        add_action('admin_menu', array($this, 'add_admin_menus'), 5); // Lower priority to run before custom post types
        add_action('admin_menu', array($this, 'reorder_bst_menu'), PHP_INT_MAX); // Maximum priority to ensure it runs last
        add_action('wp_ajax_bst_get_tour_related_fields', array($this, 'bst_get_tour_related_fields'));
        // Manual cron triggers
        add_action('wp_ajax_bst_run_availability_sync', array($this, 'run_availability_sync_manual'));
        add_action('wp_ajax_bst_run_exchange_rates', array($this, 'run_exchange_rates_manual'));
        // Automated notifications removed - replaced by dashboard
        add_action('wp_dashboard_setup', array($this, 'add_overbooking_dashboard_widget'));
        add_filter('acf/fields/relationship/result', array($this, 'customize_relationship_field_display'), 10, 4);
        add_action('add_meta_boxes', array($this, 'add_tour_dates_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_save_tour_date', array($this, 'save_tour_date'));
        add_action('wp_ajax_delete_tour_date', array($this, 'delete_tour_date'));
        add_action('wp_ajax_sync_tour_date', array($this, 'sync_tour_date'));
        add_action('wp_ajax_bst_regenerate_tour_date_titles', array($this, 'handle_regenerate_tour_date_titles'));
        add_action('wp_ajax_bst_sync_sold_slots_ajax', array($this, 'handle_sync_sold_slots_ajax'));
        add_action('wp_ajax_bst_release_data_cleanup', array($this, 'handle_release_data_cleanup'));
        add_action('wp_ajax_bst_sync_limited_vehicles_create', array($this, 'handle_sync_limited_vehicles_create'));
        add_action('wp_ajax_bst_sync_limited_vehicles_sold', array($this, 'handle_sync_limited_vehicles_sold'));
        
        // Daily availability sync automation
        add_action('wp_loaded', array($this, 'schedule_daily_availability_sync'));
        add_action('bst_daily_availability_sync', array($this, 'run_daily_availability_sync'));
        
        // Edit page navigation for tours, tour-dates, and vehicles
        add_action('edit_form_after_title', array($this, 'add_edit_page_navigation'));
        add_filter('post_row_actions', array($this, 'modify_post_row_actions'), 10, 2);
        add_action('admin_footer', array($this, 'add_tour_edit_link_script'));
        add_action('admin_action_bst_duplicate_tour', array($this, 'handle_duplicate_tour'));
        add_filter('redirect_post_location', array($this, 'preserve_list_state_after_save'), 10, 2);
        add_action('wp_ajax_save_tour_booking', 'bst_save_tour_booking');
        
        // Cleanup action for availability migration
        add_action('admin_init', array($this, 'handle_availability_cleanup'));
        add_action('wp_ajax_delete_tour_booking', array($this, 'bst_delete_tour_booking'));
        add_action('wp_ajax_bst_update_tile', 'bst_update_tile'); 
        add_action('wp_ajax_bst_update_tour_price', 'bst_update_tour_price'); 
        add_action('wp_ajax_bst_lookup_customer', 'bst_lookup_customer'); 
        add_action('wp_ajax_bst_create_booking', 'bst_create_booking');
        add_action('wp_ajax_bst_create_waiting_list_booking', 'bst_create_waiting_list_booking');
        add_action('wp_ajax_bst_send_finalization_email', array($this, 'ajax_send_finalization_email'));
        add_action('wp_ajax_bst_cancel_booking', array($this, 'ajax_cancel_booking'));
        add_action('wp_ajax_bst_get_manual_email_templates', array($this, 'ajax_get_manual_email_templates'));
        add_action('wp_ajax_bst_get_email_template_content', array($this, 'ajax_get_email_template_content'));
        add_action('wp_ajax_bst_preview_email_content', array($this, 'ajax_preview_email_content'));
        add_action('wp_ajax_bst_get_finalization_bookings', array($this, 'ajax_get_finalization_bookings'));
        add_action('wp_ajax_bst_get_merge_fields', array($this, 'ajax_get_merge_fields'));
        add_action('wp_ajax_bst_save_email_template', array($this, 'ajax_save_email_template'));
        add_action('wp_ajax_nopriv_bst_create_waiting_list_booking', 'bst_create_waiting_list_booking');
        add_action('wp_ajax_bst_delete_booking', 'bst_delete_booking');
        add_action('wp_ajax_bst_recalculate_invoice_data', 'bst_recalculate_invoice_data');
        add_action('wp_ajax_bst_get_tour_dates', array($this, 'bst_get_tour_dates'));
        add_action('wp_ajax_bst_get_tour_packages', array($this, 'bst_get_tour_packages')); 
        add_action('wp_ajax_bst_get_package_config', array($this, 'bst_get_package_config')); 
        add_action('wp_ajax_update_customer_from_booking', array($this, 'update_customer_from_booking'));
        add_action('wp_ajax_populate_customer_from_email', array($this, 'populate_customer_from_email')); 
        add_action('wp_ajax_check_tour_availability', array($this, 'check_tour_availability'));
        add_action('wp_ajax_nopriv_check_tour_availability', array($this, 'check_tour_availability'));
        add_action('wp_ajax_bst_mark_bookings_complete', array($this, 'bst_mark_bookings_complete'));
        add_action('wp_ajax_bst_get_bookings_count_for_completion', array($this, 'bst_get_bookings_count_for_completion'));
        add_action('wp_ajax_bst_get_booking_emails', array($this, 'bst_get_booking_emails'));
        
        // Email system AJAX handlers
        add_action('wp_ajax_bst_send_booking_email', array($this, 'ajax_send_booking_email'));
        
        // Show only 'tour' posts on tour-type-code taxonomy archives
        add_action('pre_get_posts', function($query) {
            if (!is_admin() && $query->is_main_query() && is_tax('tour-type-code')) {
                $query->set('post_type', 'tour');
            }
        });

        // Hook for auto-generating tour date titles
        add_action('acf/save_post', array($this, 'auto_generate_tour_date_title'), 30);
        
        // Also hook into wp_insert_post_data to ensure permalink updates
        add_filter('wp_insert_post_data', array($this, 'update_tour_date_permalink_on_save'), 10, 2);
        
        // Sync ACF taxonomy field with actual taxonomy assignment
        add_action('acf/save_post', array($this, 'sync_acf_taxonomy_assignment'), 20);
        
        // Register custom post status for cancelled tour dates
        add_action('init', array($this, 'register_custom_post_statuses'));
        
        // Tour Date admin screen modifications
        add_filter('acf/prepare_field/name=sold_slots', array($this, 'make_sold_slots_readonly'));
        add_filter('acf/prepare_field/name=reserved_slots', array($this, 'make_reserved_slots_readonly'));
        add_filter('acf/prepare_field/name=available_slots', array($this, 'make_available_slots_readonly'));
        add_filter('acf/fields/post_object/query/key=field_696e8b1a0a002', array($this, 'acf_limited_vehicle_post_object_query'), 10, 3);
        add_filter('acf/fields/post_object/query/key=field_67f9e40b1c001', array($this, 'acf_tour_vehicle_pricing_cpt_query'), 10, 3);

        // Make title field readonly for tour-date posts
        add_action('admin_head', array($this, 'make_tour_date_title_readonly'));
        
        // Custom sorting for tour-date posts in admin (sort by tour name + start date when sorting by title)
        add_action('pre_get_posts', array($this, 'custom_tour_date_sorting'));
    }

    public function register_tour_type_code_taxonomy() {
        register_taxonomy(
            'tour-type-code',
            array('tour', 'tour-type'),
            array(
                'label' => 'Tour Type Code',
                'labels' => array(
                    'name'              => 'Tour Type Codes',
                    'singular_name'     => 'Tour Type Code',
                    'search_items'      => 'Search Tour Type Codes',
                    'all_items'         => 'All Tour Type Codes',
                    'edit_item'         => 'Edit Tour Type Code',
                    'update_item'       => 'Update Tour Type Code',
                    'add_new_item'      => 'Add New Tour Type Code',
                    'new_item_name'     => 'New Tour Type Code Name',
                    'menu_name'         => 'Tour Type Codes',
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'hierarchical'      => false,
                'rewrite'           => array('slug' => 'tours'), // Pretty URLs: /tours/miata/
                'show_in_rest'      => true,
            )
        );
        
        // Register Tour Rating Taxonomy (for tours only - exactly like tour-type-code but only for tours)
        register_taxonomy(
            'tour-rating',
            array('tour'),
            array(
                'label' => 'Tour Rating',
                'labels' => array(
                    'name'              => 'Tour Ratings',
                    'singular_name'     => 'Tour Rating',
                    'search_items'      => 'Search Tour Ratings',
                    'all_items'         => 'All Tour Ratings',
                    'edit_item'         => 'Edit Tour Rating',
                    'update_item'       => 'Update Tour Rating',
                    'add_new_item'      => 'Add New Tour Rating',
                    'new_item_name'     => 'New Tour Rating Name',
                    'menu_name'         => 'Tour Ratings',
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'hierarchical'      => false,
                'rewrite'           => array('slug' => 'tour-rating'),
                'show_in_rest'      => true,
            )
        );
        
        // Force flush rewrite rules on taxonomy registration
        if (!get_option('bst_taxonomy_flushed')) {
            flush_rewrite_rules();
            update_option('bst_taxonomy_flushed', true);
        }
    }

    public function register_custom_post_types() {
        // Tour Post Type
        register_post_type('tour', array(
            'labels' => array(
                'name' => 'Tours',
                'singular_name' => 'Tour',
                'add_new_item' => 'Add New Tour',
                'edit_item' => 'Edit Tour',
                'all_items' => 'Tours'
            ),
            'show_in_rest' => true,
            'supports' => array('title'),
            'rewrite' => array('slug' => 'tour'),
            'has_archive' => true,
            'public' => true,
            'show_in_menu' => 'bst-plugin',
            'menu_icon' => 'dashicons-admin-site'
        ));
        
        // Tour Date Post Type
        register_post_type('tour-date', array(
            'labels' => array(
                'name' => 'Tour Dates',
                'singular_name' => 'Tour Date',
                'add_new_item' => 'Add New Tour Date',
                'edit_item' => 'Edit Tour Date',
                'all_items' => 'Tour Dates'
            ),
            'public' => true,
            'show_in_rest' => true,
            'supports' => array('title', 'custom-fields'),
            'show_in_menu' => 'bst-plugin',
            'menu_icon' => 'dashicons-calendar-alt'
        ));

        // Register custom "cancelled" status for tour-date posts
        register_post_status('cancelled', array(
            'label' => 'Cancelled',
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>'),
            'post_type' => array('tour-date')
        ));

        // Tour Type Post Type
        register_post_type('tour-type', array(
            'labels' => array(
                'name' => 'Tour Types',
                'singular_name' => 'Tour Type',
                'add_new_item' => 'Add New Tour Type',
                'edit_item' => 'Edit Tour Type',
                'all_items' => 'Tour Types'
            ),
            'rewrite' => array('slug' => 'tour-types'),
            'has_archive' => true,
            'public' => true,
            'show_in_rest' => true,
            'supports' => array('title'),
            'show_in_menu' => 'bst-plugin',
            'menu_icon' => 'dashicons-admin-site',
            'capability_type' => array('tour_type', 'tour_types'),
            'map_meta_cap' => true,
        ));

        // Source Code Post Type
        register_post_type('source-code', array(
            'labels' => array(
                'name' => 'Source Codes',
                'singular_name' => 'Source Code',
                'add_new_item' => 'Add New Source Code',
                'edit_item' => 'Edit Source Code',
                'all_items' => 'Source Codes'
            ),
            'public' => true,
            'show_in_menu' => 'bst-plugin',
            'menu_icon' => 'dashicons-info',
            'supports' => array('title'),
            'capability_type' => array('source_code', 'source_codes'),
            'map_meta_cap' => true,
        ));
    }

    // Set up the admin menu
    public function add_admin_menus() {
        add_menu_page(
            'BST Tour & Booking Plugin Page',
            'Tours & Booking',
            'view_bst_dashboard',
            'bst-plugin',
            array($this, 'bst_dashboard_page'), // Point main menu to dashboard
            'dashicons-car',
            6
        );

        // Remove the default submenu item that WordPress automatically creates
        remove_submenu_page('bst-plugin', 'bst-plugin');

        // Dashboard submenu - first item
        add_submenu_page(
            'bst-plugin',
            'Dashboard',
            'Dashboard',
            'view_bst_dashboard',
            'bst-dashboard',
            array($this, 'bst_dashboard_page')
        );

        add_submenu_page(
            'bst-plugin', // Parent menu slug
            'Tour Type Codes', // Page title
            'Tour Type Codes', // Menu title
            'manage_options', // Capability - Admin only
            'edit-tags.php?taxonomy=tour-type-code&post_type=tour', // Taxonomy management page
            '', // No callback needed
            30 // Position (optional)
        );

        add_submenu_page(
            'bst-plugin', // Parent menu slug
            'Tour Ratings', // Page title
            'Tour Ratings', // Menu title
            'manage_options', // Capability - Admin only
            'edit-tags.php?taxonomy=tour-rating&post_type=tour', // Force taxonomy management access
            '', // No callback needed
            35 // Position (optional)
        );

        // Customers submenu (between Tour Type Codes and Tour Bookings)
        add_submenu_page(
            'bst-plugin',
            'Customers',
            'Customers',
            'manage_bst_customers',
            'bst-plugin-customer-list',
            array($this, 'bst_customer_list_page'),
            15 // Position between Tour Type Codes (10) and Tour Bookings (20)
        );

        add_submenu_page(
            'bst-plugin', 
            'Tour Bookings', 
            'Tour Bookings',    
            'manage_bst_bookings', 
            'bst-tour-bookings',    
            array($this, 'bst_tour_bookings_page')
        );

        // Invoices submenu (visible under Tour Bookings in the reordered menu)
        add_submenu_page(
            'bst-plugin',
            'Invoices',
            'Invoices',
            'manage_bst_bookings',
            'bst-invoices',
            array($this, 'bst_invoices_page')
        );

        // Hidden page for editing a booking
        add_submenu_page(
            null, // No parent, so it won't show in the menu
            'Edit Booking',
            'Edit Booking',
            'manage_bst_bookings',
            'edit_booking',
            array($this, 'bst_tour_bookings_edit_page')
        );

        // Hidden page for adding a booking (if you have a separate add page)
        add_submenu_page(
            null, // No parent, so it won't show in the menu
            'Add Booking',
            'Add Booking',
            'manage_bst_bookings',
            'add_booking',
            array($this, 'bst_tour_bookings_edit_page') // You need to create this method/template
        );

        // Hidden page for viewing a booking
        add_submenu_page(
            null, // No parent, so it won't show in the menu
            'View Booking',
            'View Booking',
            'manage_bst_bookings',
            'view_booking',
            array($this, 'bst_tour_bookings_view_page')
        );

        // Hidden page for adding/editing/viewing a customer
        add_submenu_page(
            null, // No parent, so it won't show in the menu
            'Add/Edit Customer',
            'Add/Edit Customer',
            'manage_bst_customers',
            'bst-plugin-customer-form',
            array($this, 'bst_customer_form_page')
        );

        add_submenu_page(
            'bst-plugin',
            'About',
            'About',
            'view_bst_dashboard',
            'bst-plugin-about',
            array($this, 'bst_plugin_about_page')
        );

        add_submenu_page(
            'bst-plugin',
            'Settings',
            'Settings',
            'manage_options',
            'bst_settings_page',
            array($this, 'bst_settings_page_html')
        );

        add_submenu_page(
            'bst-plugin',
            'Tools',
            'Tools',
            'manage_options',
            'bst_tools_page',
            array($this, 'bst_tools_page_html')
        );

        add_submenu_page(
            'bst-plugin',
            'Exchange Rates',
            'Exchange Rates',
            'manage_options',
            'bst-exchange-rates',
            array($this, 'bst_exchange_rates_page_html')
        );
    }

    // Reorder the BST menu to put Dashboard first and organize properly
    public function reorder_bst_menu() {
        global $submenu;
        
        if (isset($submenu['bst-plugin'])) {
            // Remove the automatic duplicate if it exists
            foreach ($submenu['bst-plugin'] as $key => $item) {
                if ($item[2] === 'bst-plugin') {
                    unset($submenu['bst-plugin'][$key]);
                    break;
                }
            }
            
            // Create new ordered array
            $new_submenu = array();
            $old_submenu = $submenu['bst-plugin'];
            
            // Define the desired order by menu slug (workflow-based organization)
            $desired_order = array(
                // Management Tools (daily operations)
                'bst-dashboard',                           // Dashboard (overview)
                'bst-tour-bookings',                      // Tour Bookings (primary management)
                'bst-invoices',                           // Invoices (under Tour Bookings)
                'bst-plugin-customer-list',               // Customers (customer management)
                // Tour Content & Metadata (content creation and organization)
                'edit.php?post_type=tour-type',           // Tour Types (define types first)
                'edit-tags.php?taxonomy=tour-type-code&post_type=tour', // Tour Type Codes (taxonomy for types)
                'edit.php?post_type=tour',                // Tours (content using the types)
                'edit.php?post_type=tour-date',           // Tour Dates (dates for the tours)
                'edit.php?post_type=vehicle',             // Vehicles (under Tour Dates)
                'edit.php?post_type=source-code',         // Source Codes
                'edit-tags.php?taxonomy=tour-rating&post_type=tour',    // Tour Ratings
                'edit.php?post_type=email-template',      // Email Templates
                'bst-exchange-rates',                     // Exchange Rates
                // Plugin-level Settings & Information
                'bst_settings_page',                      // Settings (core configuration)
                'bst_tools_page',                         // Tools (admin operations)
                'bst-plugin-about'                        // About (plugin info)
            );
            
            // Add items in the desired order
            foreach ($desired_order as $slug) {
                foreach ($old_submenu as $key => $item) {
                    if ($item[2] === $slug) {
                        $new_submenu[] = $item;
                        unset($old_submenu[$key]);
                        break;
                    }
                }
            }
            
            // Add any remaining items that weren't in our desired order
            foreach ($old_submenu as $item) {
                $new_submenu[] = $item;
            }
            
            // Replace the submenu
            $submenu['bst-plugin'] = $new_submenu;
        }
    }

    function bst_tour_bookings_page() {
        global $wpdb;
        $tour_booking_table = $wpdb->prefix . "bst_tour_booking";

        // Check for action parameter to route to different pages
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        
        // Handle different actions
        switch($action) {
            case 'edit':
                $this->bst_tour_bookings_edit_page();
                return;
                
            case 'new':
                $this->bst_tour_bookings_new_page();
                return;
                
            default:
                // Show the list page
                break;
        }

        // Get all bookings for building filter dropdowns
        $all_tour_bookings = $wpdb->get_results("SELECT * FROM $tour_booking_table", OBJECT);

        // Get filter and sort parameters
        $selected_tour = isset($_GET['filter_tour_id']) ? intval($_GET['filter_tour_id']) : 0;
        $selected_date = isset($_GET['filter_tour_date_id']) ? intval($_GET['filter_tour_date_id']) : 0;
        $selected_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        // Determine sorting - apply defaults when no sort parameters are provided
        $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
        $sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';

        // Build the SQL query with filters
        $where_conditions = array();
        $where_params = array();

        if ($selected_tour > 0) {
            $where_conditions[] = "tour_id = %d";
            $where_params[] = $selected_tour;
        }

        if ($selected_date > 0) {
            $where_conditions[] = "tour_date_id = %d";
            $where_params[] = $selected_date;
        }

        if (!empty($selected_status)) {
            if ($selected_status === 'all_active') {
                $where_conditions[] = "booking_status NOT IN (%s, %s)";
                $where_params[] = 'Waiting List';
                $where_params[] = 'Cancelled';
            } else {
                $where_conditions[] = "booking_status = %s";
                $where_params[] = $selected_status;
            }
        }

        if (!empty($search)) {
            $where_conditions[] = "(guest1_first_name LIKE %s OR guest1_last_name LIKE %s OR guest2_first_name LIKE %s OR guest2_last_name LIKE %s)";
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $where_params[] = $search_param;
            $where_params[] = $search_param;
            $where_params[] = $search_param;
            $where_params[] = $search_param;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
        }

        // Add ORDER BY clause
        $order_clause = '';
        if (!empty($sort_by)) {
            $sort_order = ($sort_order === 'desc') ? 'DESC' : 'ASC';
            
            if ($sort_by === 'name') {
                // For name sorting, sort by last name first, then first name
                $order_clause = " ORDER BY guest1_last_name $sort_order, guest1_first_name $sort_order";
            } elseif ($sort_by === 'date') {
                // For date sorting, use created_date field
                $order_clause = " ORDER BY created_date $sort_order";
            } elseif ($sort_by === 'tour') {
                // For tour sorting, use live tour title from posts table.
                $order_clause = " ORDER BY (SELECT post_title FROM {$wpdb->posts} p WHERE p.ID = {$tour_booking_table}.tour_id LIMIT 1) $sort_order";
            } else {
                $allowed_sort_columns = array('id', 'guest1_first_name', 'guest1_last_name', 'booking_status', 'created_date');
                if (in_array($sort_by, $allowed_sort_columns)) {
                    $order_clause = " ORDER BY $sort_by $sort_order";
                }
            }
        }

        $sql = "SELECT * FROM $tour_booking_table" . $where_clause . $order_clause;
        
        if (!empty($where_params)) {
            $tour_bookings = $wpdb->get_results($wpdb->prepare($sql, $where_params), OBJECT);
        } else {
            $tour_bookings = $wpdb->get_results($sql, OBJECT);
        }
        

    
        // Include the template file
        global $title;
        $title = 'Tour Bookings';
        include BST_PLUGIN_DIR . 'templates/tour-bookings-list.php';
    }
    
    function bst_tour_bookings_edit_page() {
        global $wpdb;
        $tour_booking_table = $wpdb->prefix . "bst_tour_booking";
    
        $booking = null;
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            // Try both id and booking_entry_id for backward compatibility
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tour_booking_table WHERE id = %d OR booking_entry_id = %d", $id, $id), OBJECT);
        }
    
        // Build filtered query for navigation (respecting filters from main page)
        $where_conditions = array();
        $query_params = array();
        
        // Apply same filters as main page
        if (isset($_GET['filter_tour_id']) && !empty($_GET['filter_tour_id'])) {
            $where_conditions[] = "tour_id = %d";
            $query_params[] = intval($_GET['filter_tour_id']);
        }
        if (isset($_GET['filter_tour_date_id']) && !empty($_GET['filter_tour_date_id'])) {
            $where_conditions[] = "tour_date_id = %d";
            $query_params[] = intval($_GET['filter_tour_date_id']);
        }
        if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
            $fs = trim($_GET['filter_status']);
            if ($fs === 'all_active') {
                $where_conditions[] = "booking_status NOT IN (%s, %s)";
                $query_params[] = 'Waiting List';
                $query_params[] = 'Cancelled';
            } else {
                $where_conditions[] = "booking_status = %s";
                $query_params[] = $fs;
            }
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = sanitize_text_field($_GET['search']);
            $where_conditions[] = "(guest1_first_name LIKE %s OR guest1_last_name LIKE %s OR guest2_first_name LIKE %s OR guest2_last_name LIKE %s)";
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
        }
        
        // Build WHERE clause
        $where_sql = '';
        if (!empty($where_conditions)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Apply sorting (mirror main list page behaviour)
        $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
        $sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
        $sort_order = (strtolower($sort_order) === 'desc') ? 'DESC' : 'ASC';
        
        if ($sort_by === 'name') {
            // Sort by last name, then first name
            $order_sql = " ORDER BY guest1_last_name $sort_order, guest1_first_name $sort_order";
        } elseif ($sort_by === 'date') {
            // Sort by created date
            $order_sql = " ORDER BY created_date $sort_order";
        } elseif ($sort_by === 'tour') {
            // Sort by live tour title
            $order_sql = " ORDER BY (SELECT post_title FROM {$wpdb->posts} p WHERE p.ID = {$tour_booking_table}.tour_id LIMIT 1) $sort_order";
        } else {
            // Fallback to safe column whitelist
            $allowed_sort_columns = array('id', 'guest1_first_name', 'guest1_last_name', 'booking_status', 'created_date');
            if (!in_array($sort_by, $allowed_sort_columns, true)) {
                $sort_by = 'id';
            }
            $order_sql = " ORDER BY $sort_by $sort_order";
        }
        
        // Get filtered booking IDs for navigation
        $query = "SELECT id FROM $tour_booking_table" . $where_sql . $order_sql;
        if (!empty($query_params)) {
            $booking_ids = $wpdb->get_col($wpdb->prepare($query, $query_params));
        } else {
            $booking_ids = $wpdb->get_col($query);
        }
        
        // Find current position in filtered results
        $current_index = null;
        $current_position = null;
        if ($booking && $booking_ids) {
            $current_index = array_search($booking->id, $booking_ids);
            if ($current_index !== false) {
                $current_position = $current_index + 1; // 1-based position
            }
        }
        
        // Pass navigation info to template
        $GLOBALS['bst_booking_nav'] = [
            'ids' => $booking_ids,
            'current_index' => $current_index,
            'current_position' => $current_position,
            'total_filtered' => count($booking_ids)
        ];
        
        // Set page title for admin header
        global $title;
        $title = $booking ? 'Edit Booking #' . $booking->id : 'Add Booking';
        
        include BST_PLUGIN_DIR . 'templates/tour-bookings-edit.php';
    }

    function bst_tour_bookings_view_page() {
        global $wpdb;
        $tour_booking_table = $wpdb->prefix . "bst_tour_booking";

        $booking = null;
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            // Fixed: Only search by ID, not booking_entry_id
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tour_booking_table WHERE id = %d", $id), OBJECT);
        } elseif (isset($_GET['booking_id'])) {
            // Support booking_id parameter (used in some links)
            $id = intval($_GET['booking_id']);
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tour_booking_table WHERE id = %d", $id), OBJECT);
        } elseif (isset($_GET['booking_entry_id'])) {
            // Support booking_entry_id parameter (for admin links in email notifications)
            $entry_id = intval($_GET['booking_entry_id']);
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tour_booking_table WHERE booking_entry_id = %d", $entry_id), OBJECT);
        }
    
        // Build filtered query for navigation (respecting filters from main page, including search)
        $where_conditions = array();
        $query_params = array();
    
        // Apply same filters as main page
        if (isset($_GET['filter_tour_id']) && !empty($_GET['filter_tour_id'])) {
            $where_conditions[] = "tour_id = %d";
            $query_params[] = intval($_GET['filter_tour_id']);
        }
        if (isset($_GET['filter_tour_date_id']) && !empty($_GET['filter_tour_date_id'])) {
            $where_conditions[] = "tour_date_id = %d";
            $query_params[] = intval($_GET['filter_tour_date_id']);
        }
        if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
            $fs = trim($_GET['filter_status']);
            if ($fs === 'all_active') {
                $where_conditions[] = "booking_status NOT IN (%s, %s)";
                $query_params[] = 'Waiting List';
                $query_params[] = 'Cancelled';
            } else {
                $where_conditions[] = "booking_status = %s";
                $query_params[] = $fs;
            }
        }
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = sanitize_text_field($_GET['search']);
            $where_conditions[] = "(guest1_first_name LIKE %s OR guest1_last_name LIKE %s OR guest2_first_name LIKE %s OR guest2_last_name LIKE %s)";
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
            $query_params[] = $search_param;
        }
    
        // Build WHERE clause
        $where_sql = '';
        if (!empty($where_conditions)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
        }
    
        // Apply sorting (mirror main list page behaviour)
        $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
        $sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
        $sort_order = (strtolower($sort_order) === 'desc') ? 'DESC' : 'ASC';
    
        if ($sort_by === 'name') {
            // Sort by last name, then first name
            $order_sql = " ORDER BY guest1_last_name $sort_order, guest1_first_name $sort_order";
        } elseif ($sort_by === 'date') {
            // Sort by created date
            $order_sql = " ORDER BY created_date $sort_order";
        } elseif ($sort_by === 'tour') {
            // Sort by live tour title
            $order_sql = " ORDER BY (SELECT post_title FROM {$wpdb->posts} p WHERE p.ID = {$tour_booking_table}.tour_id LIMIT 1) $sort_order";
        } else {
            // Fallback to safe column whitelist
            $allowed_sort_columns = array('id', 'guest1_first_name', 'guest1_last_name', 'booking_status', 'created_date');
            if (!in_array($sort_by, $allowed_sort_columns, true)) {
                $sort_by = 'id';
            }
            $order_sql = " ORDER BY $sort_by $sort_order";
        }
    
        // Get filtered booking IDs for navigation
        $query = "SELECT id FROM $tour_booking_table" . $where_sql . $order_sql;
        if (!empty($query_params)) {
            $booking_ids = $wpdb->get_col($wpdb->prepare($query, $query_params));
        } else {
            $booking_ids = $wpdb->get_col($query);
        }
    
        // Find current position in filtered results
        $current_index = null;
        $current_position = null;
        if ($booking && $booking_ids) {
            $current_index = array_search($booking->id, $booking_ids);
            if ($current_index !== false) {
                $current_position = $current_index + 1; // 1-based position
            }
        }
    
        // Pass navigation info to template
        $GLOBALS['bst_booking_nav'] = [
            'ids' => $booking_ids,
            'current_index' => $current_index,
            'current_position' => $current_position,
            'total_filtered' => count($booking_ids)
        ];
    
        // Set page title for admin header
        global $title;
        $title = $booking ? 'Edit Booking #' . $booking->id : 'View Booking';
    
        include BST_PLUGIN_DIR . 'templates/tour-bookings-edit.php';
    }

    function bst_tour_bookings_new_page() {
        global $wpdb;
        
        // Get booking type and tour/date filters
        $booking_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'paper';
        $selected_tour_id = isset($_GET['filter_tour_id']) ? intval($_GET['filter_tour_id']) : 0;
        $selected_tour_date_id = isset($_GET['filter_tour_date_id']) ? intval($_GET['filter_tour_date_id']) : 0;
        
        // Validate booking type
        $allowed_types = ['paper', 'waiting_list', 'reservation'];
        if (!in_array($booking_type, $allowed_types)) {
            $booking_type = 'paper';
        }
        
        // Get all published tours for the dropdown
        $tours = get_posts([
            'post_type' => 'tour',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        // Set page title for admin header
        global $title;
        $title = 'Add New Booking';
        
        // Pass variables to template
        include BST_PLUGIN_DIR . 'templates/tour-bookings-new.php';
    }

    /**
     * Invoices list page
     */
    public function bst_invoices_page() {
        global $wpdb, $title;
        $title = 'Invoices';

        $booking_table = $wpdb->prefix . 'bst_tour_booking';

        // --- Filters ---
        $filter_year  = isset( $_GET['filter_year'] )  ? intval( $_GET['filter_year'] )  : 0;
        $filter_month = isset( $_GET['filter_month'] ) ? intval( $_GET['filter_month'] ) : 0;

        // --- Sorting (default: invoice_number ASC) ---
        $allowed_sort = array( 'invoice_number', 'invoice_date', 'name' );
        $sort_by    = ( isset( $_GET['sort_by'] ) && in_array( $_GET['sort_by'], $allowed_sort ) )
                        ? $_GET['sort_by'] : 'invoice_number';
        $sort_order = ( isset( $_GET['sort_order'] ) && strtoupper( $_GET['sort_order'] ) === 'DESC' )
                        ? 'DESC' : 'ASC';

        $order_map = array(
            'invoice_number' => 'booking_invoice_number ' . $sort_order,
            'invoice_date'   => 'booking_invoice_date '   . $sort_order . ', booking_invoice_number ASC',
            'name'           => 'guest1_last_name '       . $sort_order . ', guest1_first_name ' . $sort_order,
        );
        $order_sql = 'ORDER BY ' . $order_map[ $sort_by ];

        // --- Build WHERE clause ---
        // Only show bookings that have an invoice number generated
        $where_conditions = array( 'booking_invoice_number IS NOT NULL' );
        $where_params     = array();

        if ( $filter_year > 0 ) {
            $where_conditions[] = 'YEAR(booking_invoice_date) = %d';
            $where_params[]     = $filter_year;
        }

        if ( $filter_month > 0 ) {
            $where_conditions[] = 'MONTH(booking_invoice_date) = %d';
            $where_params[]     = $filter_month;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_conditions );

        $sql = "SELECT * FROM {$booking_table} {$where_sql} {$order_sql}";

        if ( ! empty( $where_params ) ) {
            $invoices = $wpdb->get_results( $wpdb->prepare( $sql, $where_params ), OBJECT );
        } else {
            $invoices = $wpdb->get_results( $sql, OBJECT );
        }

        // --- Available years for the filter dropdown ---
        $years_sql      = "SELECT DISTINCT YEAR(booking_invoice_date) as yr FROM {$booking_table} WHERE booking_invoice_number IS NOT NULL AND booking_invoice_date IS NOT NULL ORDER BY yr DESC";
        $available_years = $wpdb->get_col( $years_sql );

        include BST_PLUGIN_DIR . 'templates/invoices-list.php';
    }

    // Point to admin page templates
    public function bst_plugin_main_page() {
        global $title;
        $title = 'BST Tour & Booking Plugin';
        include BST_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function bst_plugin_about_page() {
        global $title;
        $title = 'About BST Plugin';
        include BST_PLUGIN_DIR . 'templates/about-page.php';
    }

    public function bst_settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $title;
        $title = 'BST Settings';
        include BST_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function bst_tools_page_html() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        global $title;
        $title = 'BST Tools';
        include BST_PLUGIN_DIR . 'templates/tools-page.php';
    }

    public function bst_exchange_rates_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $title;
        $title = 'Exchange Rates';
        include BST_PLUGIN_DIR . 'templates/exchange-rates-page.php';
    }

    public function customize_relationship_field_display($title, $post, $field, $post_id) {
        // Check if this is the relationship field for tour dates
        if ($field['name'] == 'dates') {
            // Get custom fields from the tour date post
            $start_date = get_field('start_date', $post->ID);
            $end_date = get_field('end_date', $post->ID);
            
            // Normalize dates to Y-m-d format for consistent handling
            $normalized_start_date = date('Y-m-d', strtotime($start_date));
            $normalized_end_date = date('Y-m-d', strtotime($end_date));
            
            $available_slots = get_field('available_slots', $post->ID);

            // Format the display text using normalized dates
            $date_text = (date('M', strtotime($normalized_start_date)) == date('M', strtotime($normalized_end_date))) 
                ? date('j', strtotime($normalized_start_date)) . ' - ' . date('j M Y', strtotime($normalized_end_date))
                : date('j M', strtotime($normalized_start_date)) . ' - ' . date('j M Y', strtotime($normalized_end_date));
            $availability_text = ($available_slots == 0) ? ' (Sold Out)' : ' (' . $available_slots . ' slots available)';

            // Combine the date text and availability text
            $title = $date_text . $availability_text;
        }

        return $title;
    }

    // Add a meta box for tour dates
    public function add_tour_dates_meta_box() {
        add_meta_box(
            'tour_dates_meta_box',
            'Related Tour Dates (you must save each Tour Date with its buttons)',
            array($this, 'render_tour_dates_meta_box'),
            'tour',
            'normal',
            'high'
        );
    }

    // Enqueue admin scripts
    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        $screen_id = isset($screen->id) ? (string) $screen->id : '';
        $screen_post_type = isset($screen->post_type) ? (string) $screen->post_type : '';

        // Do not run BST admin enhancement scripts on ACF/SCF field-group editors.
        // These screens manage field definitions and can break if unrelated admin scripts run.
        if (
            false !== strpos($screen_id, 'acf-field-group') ||
            false !== strpos($screen_id, 'scf-field-group') ||
            'acf-field-group' === $screen_post_type
        ) {
            return;
        }

        // Enqueue scripts for Tour Dates meta box on Tour edit/new pages
        if (($hook == 'post.php' || $hook == 'post-new.php') && ($screen_post_type === 'tour' || $screen_post_type === 'tour-date')) {
            // Use a more reliable URL path for Azure
            $script_url = content_url('mu-plugins/bst_plugin/js/tour-dates-admin.js');
            $script_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/tour-dates-admin.js';
            $script_version = file_exists($script_path) ? filemtime($script_path) : time();
            wp_enqueue_script('bst-admin-script', $script_url, array('jquery'), $script_version, true);
            wp_localize_script('bst-admin-script', 'bstAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bst_admin_nonce'),
                'regenerate_nonce' => wp_create_nonce('bst_regenerate_titles')
            ));
        }

        // Enqueue scripts for Tour Bookings admin page
        if (isset($screen->id) && ($screen->id == 'source-code_page_tour-bookings' || $screen->id == 'admin_page_view_booking' || $screen->id == 'admin_page_add_booking')) {
            // Use a more reliable URL path for Azure
            $script_url = content_url('mu-plugins/bst_plugin/js/tour-bookings-admin.js');
            $script_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/tour-bookings-admin.js';
            $script_version = file_exists($script_path) ? filemtime($script_path) : time();
            wp_enqueue_script('bst-tour-bookings-admin', $script_url, array('jquery'), $script_version, true);
            wp_localize_script('bst-tour-bookings-admin', 'bst_ajax_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bst_tour_bookings_nonce') // Different nonce for tour bookings
            ));
            
            // Provide ajaxurl for legacy code
            wp_add_inline_script('bst-tour-bookings-admin', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
        }
        
        //loads for multiple pages
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');
        
        // Enqueue admin CSS for consistent date formatting
        $date_css_url = content_url('mu-plugins/bst_plugin/css/admin-date-format.css');
        $date_css_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/css/admin-date-format.css';
        $date_css_version = file_exists($date_css_path) ? filemtime($date_css_path) : time();
        wp_enqueue_style('bst-admin-date-format', $date_css_url, array(), $date_css_version);
        
        // Enqueue admin JS for consistent date formatting
        // Use a more reliable URL path for Azure
        $date_js_url = content_url('mu-plugins/bst_plugin/js/admin-date-format.js');
        $date_js_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/admin-date-format.js';
        $date_js_version = file_exists($date_js_path) ? filemtime($date_js_path) : time();
        wp_enqueue_script('bst-admin-date-format', $date_js_url, array('jquery'), $date_js_version, true);

    }

    // Render the tour dates meta box
    public function render_tour_dates_meta_box($post) {
        $tour_dates = get_posts(array(
            'post_type' => 'tour-date',
            'meta_query' => array(
                array(
                    'key' => 'tour',
                    'value' => $post->ID,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => array('publish', 'draft', 'pending', 'private') // Include all statuses
        ));
        echo '<div id="message-area"></div>';
        echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 12px; margin-bottom: 15px; border-radius: 4px;">';
        echo '<h4 style="margin: 0 0 8px 0; color: #23282d;">📊 Tour Dates Management</h4>';
        echo '<p style="margin: 0; font-size: 13px; color: #666;">'; 
        echo '<strong>Read-only fields:</strong> Title, Sold, Reserved, and Available slots are automatically calculated/generated. ';
        echo '<strong>Title</strong> is auto-generated from tour name and date range. ';
        echo '<strong>Sold/Reserved/Available</strong> are calculated from booking records and updated when you save.';
        echo '</p>';
        echo '</div>';
        echo '<div id="tour-dates-container">';
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="id-column">ID</th>';
        echo '<th class="date-column">Start</th>';
        echo '<th class="date-column">End</th>';
        echo '<th class="max-slots-column">Max</th>';
        echo '<th class="sold-slots-column">Sold</th>';
        echo '<th class="offline-sold-slots-column">Offline</th>';
        echo '<th class="reserved-slots-column">Reserved</th>';
        echo '<th class="available-slots-column">Available</th>';
        echo '<th class="status-column">Status</th>';
        echo '<th class="ext-column">Ext</th>';
        echo '<th class="actions-column">Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    
        foreach ($tour_dates as $tour_date) {
            $start_date = get_field('start_date', $tour_date->ID);
            $end_date = get_field('end_date', $tour_date->ID);
            $max_slots = get_field('max_slots', $tour_date->ID);
            $sold_slots = get_field('sold_slots', $tour_date->ID);
            $offline_sold_slots = get_field('offline_sold_slots', $tour_date->ID);
            $reserved_slots = get_field('reserved_slots', $tour_date->ID);
            $status = get_post_status($tour_date->ID);
    
            // Format the dates to yyyy-MM-dd with proper error handling
            $start_date_formatted = '';
            $end_date_formatted = '';
            
            if ($start_date) {
                $start_timestamp = strtotime($start_date);
                if ($start_timestamp === false) {
                    // If strtotime fails, try parsing as m/d/Y format
                    $start_timestamp = DateTime::createFromFormat('m/d/Y', $start_date);
                    if ($start_timestamp) {
                        $start_date_formatted = $start_timestamp->format('Y-m-d');
                    }
                } else {
                    $start_date_formatted = date('Y-m-d', $start_timestamp);
                }
            }
            
            if ($end_date) {
                $end_timestamp = strtotime($end_date);
                if ($end_timestamp === false) {
                    // If strtotime fails, try parsing as m/d/Y format
                    $end_timestamp = DateTime::createFromFormat('m/d/Y', $end_date);
                    if ($end_timestamp) {
                        $end_date_formatted = $end_timestamp->format('Y-m-d');
                    }
                } else {
                    $end_date_formatted = date('Y-m-d', $end_timestamp);
                }
            }
            
            // Get availability from ACF field
            $available_slots = intval(get_field('available_slots', $tour_date->ID));
            
            // Get extension offered value
            $extension_offered = get_field('extension_offered', $tour_date->ID);
            
            // Check if this tour date has any bookings
            $has_bookings = $this->tour_date_has_bookings($tour_date->ID);
    
            echo '<tr class="tour-date-item" data-id="' . $tour_date->ID . '">';
            echo '<td class="id-column"><input type="text" name="tour_date_id" value="' . esc_attr($tour_date->ID) . '" readonly class="small-input">
                    <a href="' . admin_url('post.php?post=' . $tour_date->ID . '&action=edit') . '" target="_blank" class="edit-tour-date button">
                      <i class="fas fa-edit"></i>
                    </a>
                  </td>';
            echo '<td class="date-column"><input type="date" name="start_date" value="' . esc_attr($start_date_formatted) . '"></td>';
            echo '<td class="date-column"><input type="date" name="end_date" value="' . esc_attr($end_date_formatted) . '"></td>';
            echo '<td class="max-slots-column"><input type="number" name="max_slots" value="' . esc_attr($max_slots) . '" class="small-input calculate-availability"></td>';
            echo '<td class="sold-slots-column"><input type="number" name="sold_slots" value="' . esc_attr($sold_slots) . '" class="small-input" readonly title="Calculated from booking records with Pending, Booked, Finalized, and Completed status"></td>';
            echo '<td class="offline-sold-slots-column"><input type="number" name="offline_sold_slots" value="' . esc_attr($offline_sold_slots) . '" class="small-input calculate-availability"></td>';
            echo '<td class="reserved-slots-column"><input type="number" name="reserved_slots" value="' . esc_attr($reserved_slots) . '" class="small-input" readonly title="Calculated from booking records with Reserved status"></td>';
            echo '<td class="available-slots-column">
                    <input type="number" name="available_slots" value="' . esc_attr($available_slots) . '" class="small-input" readonly title="Calculated as: Max Slots - Sold Slots - Offline Sold - Reserved Slots">
                  </td>';
            echo '<td class="status-column">
                    <select name="status">
                        <option value="publish" ' . selected($status, 'publish', false) . '>Publish</option>
                        <option value="draft" ' . selected($status, 'draft', false) . '>Draft</option>
                        <option value="pending" ' . selected($status, 'pending', false) . '>Pending</option>
                        <option value="private" ' . selected($status, 'private', false) . '>Private</option>
                        <option value="cancelled" ' . selected($status, 'cancelled', false) . '>Cancelled</option>
                    </select>
                  </td>';
            echo '<td class="ext-column">
                    <input type="checkbox" name="extension_offered" value="1" ' . checked($extension_offered, '1', false) . ' style="margin: 0; cursor: pointer;">
                  </td>';
            
            // Disable delete button if there are any sold, offline, or reserved slots
            $has_activity = ($sold_slots > 0 || $offline_sold_slots > 0 || $reserved_slots > 0);
            $delete_button_disabled = $has_activity ? 'disabled' : '';
            $delete_button_title = $has_activity ? 'Cannot delete tour date with sold, offline, or reserved slots. Change status to Cancelled instead.' : 'Delete this tour date';
            
            echo '<td class="actions-column">
                  <button type="button" class="save-tour-date button"><i class="fas fa-save"></i></button>
                  <button type="button" class="delete-tour-date button" ' . $delete_button_disabled . ' title="' . esc_attr($delete_button_title) . '"><i class="fas fa-trash-alt"></i></button>';
            
            echo '</td>';        
            echo '</tr>';
        }
    
        echo '</tbody>';
        echo '</table>';
        
        // Mobile card layout (hidden on desktop, shown on mobile)
        echo '<div class="mobile-cards">';
        foreach ($tour_dates as $tour_date) {
            $start_date = get_field('start_date', $tour_date->ID);
            $end_date = get_field('end_date', $tour_date->ID);
            $max_slots = get_field('max_slots', $tour_date->ID);
            $sold_slots = get_field('sold_slots', $tour_date->ID);
            $offline_sold_slots = get_field('offline_sold_slots', $tour_date->ID);
            $reserved_slots = get_field('reserved_slots', $tour_date->ID);
            $status = get_post_status($tour_date->ID);
            
            // Format dates for mobile
            $start_date_formatted = '';
            $end_date_formatted = '';
            
            if ($start_date) {
                $start_timestamp = strtotime($start_date);
                if ($start_timestamp === false) {
                    $start_timestamp = DateTime::createFromFormat('m/d/Y', $start_date);
                    if ($start_timestamp) {
                        $start_date_formatted = $start_timestamp->format('Y-m-d');
                    }
                } else {
                    $start_date_formatted = date('Y-m-d', $start_timestamp);
                }
            }
            
            if ($end_date) {
                $end_timestamp = strtotime($end_date);
                if ($end_timestamp === false) {
                    $end_timestamp = DateTime::createFromFormat('m/d/Y', $end_date);
                    if ($end_timestamp) {
                        $end_date_formatted = $end_timestamp->format('Y-m-d');
                    }
                } else {
                    $end_date_formatted = date('Y-m-d', $end_timestamp);
                }
            }
            
            $available_slots = intval(get_field('available_slots', $tour_date->ID));
            $extension_offered = get_field('extension_offered', $tour_date->ID);
            
            echo '<div class="tour-date-card" data-id="' . $tour_date->ID . '">';
            
            // Hidden ID field for form functionality
            echo '<input type="hidden" name="tour_date_id" value="' . esc_attr($tour_date->ID) . '">';
            
            // Card header
            echo '<div class="tour-date-card-header">';
            echo '<span class="card-title">Tour Date #' . $tour_date->ID . '</span>';
            echo '</div>';
            
            // Dates
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Start Date:</span>';
            echo '<div class="tour-date-card-value"><input type="date" name="start_date" value="' . esc_attr($start_date_formatted) . '"></div>';
            echo '</div>';
            
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">End Date:</span>';
            echo '<div class="tour-date-card-value"><input type="date" name="end_date" value="' . esc_attr($end_date_formatted) . '"></div>';
            echo '</div>';
            
            // Slots info
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Max Slots:</span>';
            echo '<div class="tour-date-card-value"><input type="number" name="max_slots" value="' . esc_attr($max_slots) . '" class="small-input calculate-availability"></div>';
            echo '</div>';
            
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Sold:</span>';
            echo '<div class="tour-date-card-value"><input type="number" name="sold_slots" value="' . esc_attr($sold_slots) . '" class="small-input" readonly title="Calculated from booking records"></div>';
            echo '</div>';
            
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Offline:</span>';
            echo '<div class="tour-date-card-value"><input type="number" name="offline_sold_slots" value="' . esc_attr($offline_sold_slots) . '" class="small-input calculate-availability"></div>';
            echo '</div>';
            
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Reserved:</span>';
            echo '<div class="tour-date-card-value"><input type="number" name="reserved_slots" value="' . esc_attr($reserved_slots) . '" class="small-input" readonly title="Calculated from booking records"></div>';
            echo '</div>';
            
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Available:</span>';
            echo '<div class="tour-date-card-value"><input type="number" name="available_slots" value="' . esc_attr($available_slots) . '" class="small-input" readonly title="Calculated availability"></div>';
            echo '</div>';
            
            // Status
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Status:</span>';
            echo '<div class="tour-date-card-value">';
            echo '<select name="status" class="tour-date-status">';
            echo '<option value="publish"' . selected($status, 'publish', false) . '>Published</option>';
            echo '<option value="draft"' . selected($status, 'draft', false) . '>Draft</option>';
            echo '<option value="private"' . selected($status, 'private', false) . '>Private</option>';
            echo '</select>';
            echo '</div>';
            echo '</div>';
            
            // Extension Offered
            echo '<div class="tour-date-card-row">';
            echo '<span class="tour-date-card-label">Ext:</span>';
            echo '<div class="tour-date-card-value">';
            echo '<input type="checkbox" name="extension_offered" value="1" ' . checked($extension_offered, '1', false) . ' style="margin: 0; cursor: pointer;">';
            echo '</div>';
            echo '</div>';
            
            // Actions
            echo '<div class="tour-date-card-actions">';
            echo '<button class="button button-primary save-tour-date">Save</button>';
            
            // Disable delete button if there are any sold, offline, or reserved slots
            $has_activity = ($sold_slots > 0 || $offline_sold_slots > 0 || $reserved_slots > 0);
            $delete_button_disabled = $has_activity ? ' disabled' : '';
            $delete_button_title = $has_activity ? 'Cannot delete tour date with sold, offline, or reserved slots' : 'Delete this tour date';
            
            echo '<button class="button button-link-delete delete-tour-date"' . $delete_button_disabled . ' title="' . esc_attr($delete_button_title) . '">Delete</button>';
            echo '</div>';
            
            echo '</div>'; // Close card
        }
        echo '</div>'; // Close mobile-cards
        
        echo '</div>';
        
        // Check if this is a new post (no ID yet) to disable the button
        if ($post->ID && get_post_status($post->ID)) {
            echo '<button id="add-tour-date">Add Tour Date</button>';
        } else {
            echo '<button id="add-tour-date" disabled title="Save the tour first before adding dates">Add Tour Date</button>';
            echo '<p style="font-style: italic; color: #666; margin-top: 5px;">Save this tour first before adding tour dates.</p>';
        }
    }

    function save_tour_date() {
        // Check if user is logged in and has proper capabilities
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
    
        // Validate required fields
        if (!isset($_POST['tour_id']) || !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
            wp_send_json_error('Required fields missing');
            return;
        }
    
        $tour_id = intval($_POST['tour_id']);
        $tour_date_id = isset($_POST['tour_date_id']) ? intval($_POST['tour_date_id']) : 0;
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $max_slots = isset($_POST['max_slots']) ? intval($_POST['max_slots']) : 0;
        $sold_slots = isset($_POST['sold_slots']) ? intval($_POST['sold_slots']) : 0;
        $offline_sold_slots = isset($_POST['offline_sold_slots']) ? intval($_POST['offline_sold_slots']) : 0;
        $reserved_slots = isset($_POST['reserved_slots']) ? intval($_POST['reserved_slots']) : 0;
        $extension_offered = isset($_POST['extension_offered']) ? sanitize_text_field($_POST['extension_offered']) : '0';
        
        // Calculate availability server-side to ensure consistency
        $availability = $max_slots - $sold_slots - $offline_sold_slots - $reserved_slots;
        $availability = max(0, $availability); // Ensure it's never negative
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'publish';
    
        if ($tour_date_id) {
            // Update existing tour date
            $tour_date = array(
                'ID' => $tour_date_id,
                'post_type' => 'tour-date',
                'post_status' => $status
            );
            wp_update_post($tour_date);
    
            // Use update_field() for all ACF fields to ensure consistency
            update_field('start_date', $start_date, $tour_date_id);
            update_field('end_date', $end_date, $tour_date_id);
            update_field('max_slots', $max_slots, $tour_date_id);
            update_field('sold_slots', $sold_slots, $tour_date_id);
            update_field('offline_sold_slots', $offline_sold_slots, $tour_date_id);
            update_field('reserved_slots', $reserved_slots, $tour_date_id);
            update_field('available_slots', $availability, $tour_date_id);
            update_field('extension_offered', $extension_offered, $tour_date_id);
            
            // Manually trigger title generation since update_field() doesn't fire acf/save_post
            $this->auto_generate_tour_date_title($tour_date_id);
            
            // Manually trigger waiting list notifications check
            if (function_exists('bst_waiting_list_on_acf_save')) {
                bst_waiting_list_on_acf_save($tour_date_id);
            }
        } else {
            // Create new tour date
            $tour_date = array(
                'post_title' => 'New Tour Date',
                'post_type' => 'tour-date',
                'post_status' => $status,
                'meta_input' => array(
                    'tour' => $tour_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'max_slots' => $max_slots,
                    'sold_slots' => $sold_slots,
                    'offline_sold_slots' => $offline_sold_slots,
                    'reserved_slots' => $reserved_slots,
                    'extension_offered' => $extension_offered
                )
            );
            $tour_date_id = wp_insert_post($tour_date);
            
            // Use ACF for the availability field
            if ($tour_date_id) {
                update_field('available_slots', $availability, $tour_date_id);
                
                // Manually trigger title generation for new tour dates
                $this->auto_generate_tour_date_title($tour_date_id);
                
                // Manually trigger waiting list notifications check
                if (function_exists('bst_waiting_list_on_acf_save')) {
                    bst_waiting_list_on_acf_save($tour_date_id);
                }
            }
        }
    
        if (is_wp_error($tour_date_id)) {
            wp_send_json_error($tour_date_id->get_error_message());
        } else {
            wp_send_json_success(array(
                'tour_date_id' => $tour_date_id,
                'available_slots' => $availability,
                'calculated_availability' => $availability
            ));
        }
    }

    public function delete_tour_date() {
        check_ajax_referer('bst_admin_nonce', 'nonce');

        $tour_date_id = intval($_POST['tour_date_id']);

        if ($tour_date_id) {
            wp_delete_post($tour_date_id, true);
            wp_send_json_success(array('message' => 'Tour date deleted successfully'));
        } else {
            wp_send_json_error('Invalid tour date ID');
        }
    }

    public function sync_tour_date() {
        check_ajax_referer('bst_admin_nonce', 'nonce');

        $tour_date_id = intval($_POST['tour_date_id']);

        if (!$tour_date_id) {
            wp_send_json_error('Invalid tour date ID');
            return;
        }

        // Validate tour date exists
        $tour_date_post = get_post($tour_date_id);
        if (!$tour_date_post || $tour_date_post->post_type !== 'tour-date') {
            wp_send_json_error('Invalid tour date - post not found or wrong type');
            return;
        }

        global $wpdb;
        $tour_booking_table = $wpdb->prefix . "bst_tour_booking";

        $debug_info = array();
        $errors = array();

        try {
            // Get tour name for better debugging
            $tour_field = get_field('tour', $tour_date_id);
            $tour_name = 'Unknown Tour';
            if ($tour_field) {
                if (is_object($tour_field) && isset($tour_field->post_title)) {
                    $tour_name = $tour_field->post_title;
                } elseif (is_array($tour_field) && isset($tour_field['post_title'])) {
                    $tour_name = $tour_field['post_title'];
                } elseif (is_numeric($tour_field)) {
                    $tour_name = get_the_title($tour_field);
                }
            }

            $debug_info['tour_name'] = $tour_name;
            $debug_info['tour_date_id'] = $tour_date_id;

            // Get current values before sync
            $old_sold_slots = intval(get_field('sold_slots', $tour_date_id));
            $old_reserved_slots = intval(get_field('reserved_slots', $tour_date_id));
            $old_availability = intval(get_field('available_slots', $tour_date_id));
            $max_slots = intval(get_field('max_slots', $tour_date_id));
            $offline_sold_slots = intval(get_field('offline_sold_slots', $tour_date_id));

            $debug_info['before_sync'] = array(
                'sold_slots' => $old_sold_slots,
                'reserved_slots' => $old_reserved_slots,
                'available_slots' => $old_availability,
                'max_slots' => $max_slots,
                'offline_sold_slots' => $offline_sold_slots
            );

            // Calculate sold slots from confirmed/paid bookings
            $sold_slots_query = $wpdb->prepare(
                "SELECT COALESCE(SUM(package_vehicles), 0) as total_sold 
                 FROM $tour_booking_table 
                 WHERE tour_date_id = %d 
                 AND booking_status IN ('Pending', 'Booked', 'Finalized', 'Completed')",
                $tour_date_id
            );
            
            $new_sold_slots = intval($wpdb->get_var($sold_slots_query));

            // Calculate reserved slots from Reserved status bookings
            $reserved_slots_query = $wpdb->prepare(
                "SELECT COALESCE(SUM(package_vehicles), 0) as total_reserved 
                 FROM $tour_booking_table 
                 WHERE tour_date_id = %d 
                 AND booking_status = 'Reserved'",
                $tour_date_id
            );
            
            $new_reserved_slots = intval($wpdb->get_var($reserved_slots_query));

            // Calculate availability: max_slots - sold_slots - offline_sold_slots - reserved_slots
            $new_availability = $max_slots - $new_sold_slots - $offline_sold_slots - $new_reserved_slots;
            // Ensure availability is never negative
            $new_availability = max(0, $new_availability);

            $debug_info['booking_queries'] = array(
                'sold_query' => $sold_slots_query,
                'reserved_query' => $reserved_slots_query
            );

            $debug_info['calculated_values'] = array(
                'new_sold_slots' => $new_sold_slots,
                'new_reserved_slots' => $new_reserved_slots,
                'new_availability' => $new_availability
            );

            // Update sold_slots field
            $sold_updated = false;
            if ($old_sold_slots !== $new_sold_slots) {
                $sold_result = update_post_meta($tour_date_id, 'sold_slots', $new_sold_slots);
                if ($sold_result !== false) {
                    $sold_updated = true;
                    $debug_info['sold_slots_updated'] = true;
                    $debug_info['sold_slots_change'] = $old_sold_slots . ' → ' . $new_sold_slots;
                } else {
                    $errors[] = 'Failed to update sold_slots field';
                }
            } else {
                $debug_info['sold_slots_updated'] = false;
                $debug_info['sold_slots_reason'] = 'No change needed';
            }

            // Update reserved_slots field
            $reserved_updated = false;
            if ($old_reserved_slots !== $new_reserved_slots) {
                $reserved_result = update_post_meta($tour_date_id, 'reserved_slots', $new_reserved_slots);
                if ($reserved_result !== false) {
                    $reserved_updated = true;
                    $debug_info['reserved_slots_updated'] = true;
                    $debug_info['reserved_slots_change'] = $old_reserved_slots . ' → ' . $new_reserved_slots;
                } else {
                    $errors[] = 'Failed to update reserved_slots field';
                }
            } else {
                $debug_info['reserved_slots_updated'] = false;
                $debug_info['reserved_slots_reason'] = 'No change needed';
            }

            // Calculate final availability
            $available_slots = $max_slots - $new_sold_slots - $offline_sold_slots - $new_reserved_slots;
            // Ensure availability is never negative
            $available_slots = max(0, $available_slots);
            
            // Update availability field using ACF
            $availability_updated = false;
            if ($old_availability !== $available_slots) {
                $availability_result = update_field('available_slots', $available_slots, $tour_date_id);
                if ($availability_result !== false) {
                    $availability_updated = true;
                    $debug_info['availability_updated'] = true;
                    $debug_info['availability_change'] = $old_availability . ' → ' . $available_slots;
                } else {
                    $errors[] = 'Failed to update availability field';
                }
            } else {
                $debug_info['availability_updated'] = false;
                $debug_info['availability_reason'] = 'No change needed';
            }
            
            $debug_info['final_calculation'] = array(
                'max_slots' => $max_slots,
                'sold_slots' => $new_sold_slots,
                'offline_sold_slots' => $offline_sold_slots,
                'reserved_slots' => $new_reserved_slots,
                'available_slots' => $available_slots
            );

            // Log successful sync
            if ($sold_updated || $reserved_updated || $availability_updated) {
                $log_parts = array();
                if ($sold_updated) {
                    $log_parts[] = "sold: {$old_sold_slots}→{$new_sold_slots}";
                }
                if ($reserved_updated) {
                    $log_parts[] = "reserved: {$old_reserved_slots}→{$new_reserved_slots}";
                }
                if ($availability_updated) {
                    $log_parts[] = "availability: {$old_availability}→{$available_slots}";
                }
                $log_message = sprintf('Sync success: %s (ID:%d) %s', 
                    $tour_name, $tour_date_id, implode(', ', $log_parts));
                error_log('BST Sync: ' . $log_message);
            }

            // Prepare response
            $response = array(
                'sold_slots' => $new_sold_slots,
                'reserved_slots' => $new_reserved_slots,
                'available_slots' => $available_slots,
                'updates_made' => $sold_updated || $reserved_updated || $availability_updated,
                'sold_updated' => $sold_updated,
                'reserved_updated' => $reserved_updated,
                'availability_updated' => $availability_updated,
                'tour_name' => $tour_name,
                'debug' => $debug_info
            );

            if (!empty($errors)) {
                $response['errors'] = $errors;
                wp_send_json_error($response);
            } else {
                wp_send_json_success($response);
            }

        } catch (Exception $e) {
            $debug_info['exception'] = $e->getMessage();
            error_log('BST Sync Error: ' . $e->getMessage() . ' for tour date ID: ' . $tour_date_id);
            
            wp_send_json_error(array(
                'message' => 'Sync failed: ' . $e->getMessage(),
                'debug' => $debug_info
            ));
        }
    }

    /**
     * Make sold_slots field readonly on tour-date edit screen
     */
    public function make_sold_slots_readonly($field) {
        global $post;
        if ($post && $post->post_type === 'tour-date') {
            $field['readonly'] = 1;
            $field['wrapper']['class'] .= ' readonly-field';
            $field['instructions'] = 'This field is automatically calculated from booking records with "Pending", "Booked", "Finalized", and "Completed" status. Updated during sync operations from the tour dates list.';
        }
        return $field;
    }

    /**
     * Make reserved_slots field readonly on tour-date edit screen
     */
    public function make_reserved_slots_readonly($field) {
        global $post;
        if ($post && $post->post_type === 'tour-date') {
            $field['readonly'] = 1;
            $field['wrapper']['class'] .= ' readonly-field';
            $field['instructions'] = 'This field is automatically calculated from booking records with "Reserved" status. Updated during sync operations from the tour dates list.';
        }
        return $field;
    }

    /**
     * Make available_slots field readonly on tour-date edit screen
     */
    public function make_available_slots_readonly($field) {
        global $post;
        if ($post && $post->post_type === 'tour-date') {
            $field['readonly'] = 1;
            $field['wrapper']['class'] .= ' readonly-field';
            $field['instructions'] = 'This field is automatically calculated as: Max Slots - Sold Slots - Offline Sold - Reserved Slots. Updated during sync operations from the tour dates list.';
        }
        return $field;
    }

    /**
     * Limit tour-date limited-vehicle picker to vehicles linked on the parent tour (vehicle_pricing).
     *
     * @param array $args WP_Query args for post_object field.
     * @param array $field ACF field array.
     * @param int|string $post_id Post ID being edited.
     * @return array
     */
    public function acf_limited_vehicle_post_object_query( $args, $field, $post_id ) {
        if ( ! function_exists( 'acf_get_valid_post_id' ) || ! function_exists( 'bst_tour_id_for_tour_date' ) || ! function_exists( 'bst_vehicle_ids_for_tour_date_limited_picker' ) ) {
            return $args;
        }
        $resolved = acf_get_valid_post_id( $post_id );
        if ( ! $resolved || 'tour-date' !== get_post_type( $resolved ) ) {
            return $args;
        }
        $tour_id = bst_tour_id_for_tour_date( $resolved );
        $ids     = bst_vehicle_ids_for_tour_date_limited_picker( (int) $resolved, $tour_id );
        if ( empty( $ids ) ) {
            $args['post__in'] = array( 0 );
        } else {
            $args['post__in'] = $ids;
        }
        unset( $args['meta_query'] );
        if ( function_exists( 'bst_limited_vehicles_row_key_from_prefix' ) && function_exists( 'bst_limited_vehicle_ids_assigned_other_rows' ) && ! empty( $field['prefix'] ) ) {
            $row_key = bst_limited_vehicles_row_key_from_prefix( $field['prefix'] );
            if ( null !== $row_key ) {
                $exclude = bst_limited_vehicle_ids_assigned_other_rows( (int) $resolved, $row_key );
                if ( ! empty( $exclude ) ) {
                    $not_in   = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
                    $args['post__not_in'] = array_values( array_unique( array_merge( $not_in, $exclude ) ) );
                }
            }
        }
        return $args;
    }

    /**
     * On Tour → Vehicle Pricing → Vehicle (CPT): only cars for driving tours, only motorcycles for motorcycle tours.
     *
     * @param array $args WP_Query args for post_object field.
     * @param array $field ACF field array.
     * @param int|string $post_id Post ID being edited.
     * @return array
     */
    public function acf_tour_vehicle_pricing_cpt_query( $args, $field, $post_id ) {
        if ( ! function_exists( 'acf_get_valid_post_id' ) || ! function_exists( 'bst_vehicle_ids_for_tour_pricing_picker' ) ) {
            return $args;
        }
        $resolved = acf_get_valid_post_id( $post_id );
        if ( ! $resolved || 'tour' !== get_post_type( $resolved ) ) {
            return $args;
        }
        $tour_id = (int) $resolved;
        $ids     = bst_vehicle_ids_for_tour_pricing_picker( $tour_id );
        if ( empty( $ids ) ) {
            $args['post__in'] = array( 0 );
        } else {
            $args['post__in'] = $ids;
        }
        unset( $args['meta_query'] );
        return $args;
    }

    /**
     * Make title field readonly for tour-date posts
     */
    public function make_tour_date_title_readonly() {
        global $post;
        if ($post && $post->post_type === 'tour-date') {
            echo '<style>
                #titlediv input[name="post_title"] {
                    background: linear-gradient(135deg, #f9f9f9 0%, #f1f1f1 100%) !important;
                    border: 1px solid #ddd !important;
                    font-weight: 500;
                    cursor: not-allowed;
                }
                #titlediv {
                    position: relative;
                }
                #titlediv::after {
                    content: "🔒 Auto-generated from tour name and date range";
                    position: absolute;
                    bottom: -20px;
                    left: 0;
                    font-size: 12px;
                    color: #666;
                    font-style: italic;
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    $("#title").prop("readonly", true).prop("disabled", false);
                    $("#title").attr("title", "This title is automatically generated from the tour name and date range. It cannot be edited manually.");
                });
            </script>';
        }
    }

    public function bst_get_tour_related_fields() {
        check_ajax_referer('bst_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        $tour_id = intval($_POST['tour_id']);
        $result = [
            'dates' => [],
            'packages' => [],
            'vehicles' => [],
            'base_price' => '',
        ];

        // Tour Dates
        $dates = get_posts([
            'post_type' => 'tour-date',
            'meta_query' => [
                [
                    'key' => 'tour',
                    'value' => $tour_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'start_date',
            'order' => 'ASC'
        ]);
        foreach ($dates as $date) {
            $start = get_field('start_date', $date->ID);
            $end = get_field('end_date', $date->ID);
            $text = (date('M', strtotime($start)) == date('M', strtotime($end)))
                ? date('j', strtotime($start)) . '-' . date('j M Y', strtotime($end))
                : date('j M', strtotime($start)) . '-' . date('j M Y', strtotime($end));
            $result['dates'][] = [
                'id' => $date->ID,
                'text' => $text
            ];
        }

        // Packages (ACF repeater, structure: name, price, id, etc.)
        $packages = get_field('packages', $tour_id);
        if ($packages && is_array($packages)) {
            foreach ($packages as $i => $pkg) {
                // Try to get a unique ID, fallback to index
                $pkg_id = '';
                if (isset($pkg['id']) && $pkg['id']) {
                    $pkg_id = $pkg['id'];
                } elseif (isset($pkg['name']) && $pkg['name']) {
                    $pkg_id = sanitize_title($pkg['name']);
                } elseif (isset($pkg['package_name']) && $pkg['package_name']) {
                    $pkg_id = sanitize_title($pkg['package_name']);
                } else {
                    $pkg_id = $i;
                }
                // Try to get the display text
                $pkg_text = '';
                if (isset($pkg['name']) && $pkg['name']) {
                    $pkg_text = $pkg['name'];
                } elseif (isset($pkg['package_name']) && $pkg['package_name']) {
                    $pkg_text = $pkg['package_name'];
                } else {
                    $pkg_text = 'Package ' . ($i+1);
                }
                // Try to get the price
                $pkg_price = '';
                if (isset($pkg['price'])) {
                    $pkg_price = $pkg['price'];
                } elseif (isset($pkg['package_price'])) {
                    $pkg_price = $pkg['package_price'];
                }
                $result['packages'][] = [
                    'id' => $pkg_id,
                    'text' => $pkg_text,
                    'price' => $pkg_price
                ];
            }
        }

        // Vehicles (ACF repeater or array)
        $vehicles = get_field('vehicles', $tour_id);
        if ($vehicles && is_array($vehicles)) {
            foreach ($vehicles as $veh) {
                if (is_array($veh) && isset($veh['name'])) {
                    $result['vehicles'][] = $veh['name'];
                } else {
                    $result['vehicles'][] = $veh;
                }
            }
        }

        // Base price (if needed)
        $result['base_price'] = get_field('base_price', $tour_id);

        wp_send_json_success($result);
    }

    // Customers list page
    public function bst_customer_list_page() {
        global $title;
        $title = 'Customers';
        include BST_PLUGIN_DIR . 'templates/customer-list.php';
    }
    // Customer add/edit/view page
    public function bst_customer_form_page() {
        global $title;
        $title = 'Customer Form';
        include BST_PLUGIN_DIR . 'templates/customer-form.php';
    }

    /**
     * Sync ACF taxonomy field selections with actual WordPress taxonomy assignments
     */
    public function sync_acf_taxonomy_assignment($post_id) {
        $post_type = get_post_type($post_id);
        
        // Process both tour and tour-type posts
        if ($post_type !== 'tour' && $post_type !== 'tour-type') {
            return;
        }
        
        // Sync tour-type-code taxonomy
        $type_code_field = get_field('type_code', $post_id);
        
        if ($type_code_field && is_object($type_code_field)) {
            // Assign this post to the taxonomy term
            wp_set_object_terms($post_id, array($type_code_field->term_id), 'tour-type-code');
        } else {
            // Remove all taxonomy assignments if no ACF field is set
            wp_set_object_terms($post_id, array(), 'tour-type-code');
        }
        
        // Sync tour-rating taxonomy (only for tour posts)
        if ($post_type === 'tour') {
            $tour_rating_field = get_field('tour_rating', $post_id);
            
            if ($tour_rating_field && is_object($tour_rating_field)) {
                // Assign this tour to the rating taxonomy term
                wp_set_object_terms($post_id, array($tour_rating_field->term_id), 'tour-rating');
            } else {
                // Remove all rating taxonomy assignments if no ACF field is set
                wp_set_object_terms($post_id, array(), 'tour-rating');
            }
        }
    }

    /**
     * Auto-generate tour date title based on tour name and date range
     * Format: "Tour Name (19-27 Oct 2025)" or "Tour Name (28 Sep - 3 Oct 2025)"
     */
    public function auto_generate_tour_date_title($post_id) {
        // Only run for tour-date posts
        if (get_post_type($post_id) !== 'tour-date') {
            return;
        }

        // Avoid infinite loops by checking if we're already updating
        if (get_transient('updating_tour_date_title_' . $post_id)) {
            return;
        }

        // Get the tour, start date, and end date
        $tour_id = get_field('tour', $post_id);
        $start_date = get_field('start_date', $post_id);
        $end_date = get_field('end_date', $post_id);

        // Only proceed if we have all required data
        if (!$tour_id || !$start_date || !$end_date) {
            return;
        }

        // Get tour name
        $tour_post = get_post($tour_id);
        if (!$tour_post) {
            return;
        }
        $tour_name = html_entity_decode($tour_post->post_title, ENT_QUOTES, 'UTF-8');

        // Format the date range using the same logic as throughout the system
        if (date('M', strtotime($start_date)) == date('M', strtotime($end_date))) {
            // Same month: "19-27 Oct 2025"
            $date_text = date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date));
        } else {
            // Different months: "28 Sep - 3 Oct 2025"
            $date_text = date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
        }

        // Create the new title
        $new_title = $tour_name . ' (' . $date_text . ')';

        // Get current title to check if update is needed
        $current_title = get_the_title($post_id);
        
        // Always update if current title is Auto Draft or empty, otherwise only if changed
        $should_update = ($current_title !== $new_title) || 
                        empty($current_title) || 
                        (stripos($current_title, 'auto draft') !== false) ||
                        (stripos($current_title, 'auto-draft') !== false);
        
        if ($should_update) {
            // Set transient to prevent infinite loops
            set_transient('updating_tour_date_title_' . $post_id, true, 60);
            
            // Generate a clean slug from the new title
            $new_slug = sanitize_title($new_title);
            
            // Update the post title and slug
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $new_title,
                'post_name' => $new_slug
            ));
            
            // Clean up transient
            delete_transient('updating_tour_date_title_' . $post_id);
            
            error_log("BST: Auto-generated tour date title and permalink for ID {$post_id}: {$new_title} -> {$new_slug}");
        }
    }

    /**
     * Update tour date permalink during post save process
     */
    public function update_tour_date_permalink_on_save($data, $postarr) {
        // Only handle tour-date posts
        if ($data['post_type'] !== 'tour-date') {
            return $data;
        }
        
        // Skip if this is an auto-save or revision
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $data;
        }
        
        // Skip if post status is auto-draft (new post not yet saved)
        if ($data['post_status'] === 'auto-draft') {
            return $data;
        }
        
        // Check if we have the ACF data available
        $post_id = isset($postarr['ID']) ? $postarr['ID'] : 0;
        if (!$post_id) {
            return $data;
        }
        
        // Get ACF fields - check both $_POST and existing values
        $tour_id = null;
        $start_date = null;
        $end_date = null;
        
        // Try to get from $_POST first (current save)
        if (isset($_POST['acf']) && is_array($_POST['acf'])) {
            foreach ($_POST['acf'] as $field_key => $value) {
                $field = get_field_object($field_key);
                if ($field) {
                    if ($field['name'] === 'tour') {
                        $tour_id = $value;
                    } elseif ($field['name'] === 'start_date') {
                        $start_date = $value;
                    } elseif ($field['name'] === 'end_date') {
                        $end_date = $value;
                    }
                }
            }
        }
        
        // Fallback to existing values if not found in POST
        if (!$tour_id) $tour_id = get_field('tour', $post_id);
        if (!$start_date) $start_date = get_field('start_date', $post_id);
        if (!$end_date) $end_date = get_field('end_date', $post_id);
        
        // Only proceed if we have all required data
        if (!$tour_id || !$start_date || !$end_date) {
            return $data;
        }
        
        // Get tour name
        $tour_post = get_post($tour_id);
        if (!$tour_post) {
            return $data;
        }
        $tour_name = $tour_post->post_title;
        
        // Format the date range
        if (date('M', strtotime($start_date)) == date('M', strtotime($end_date))) {
            // Same month: "19-27 Oct 2025"
            $date_text = date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date));
        } else {
            // Different months: "28 Sep - 3 Oct 2025"
            $date_text = date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
        }
        
        // Create the new title and slug
        $new_title = $tour_name . ' (' . $date_text . ')';
        $new_slug = sanitize_title($new_title);
        
        // Update the data that will be saved
        $data['post_title'] = $new_title;
        $data['post_name'] = $new_slug;
        
        error_log("BST: Updated tour date title and permalink via wp_insert_post_data for ID {$post_id}: {$new_title} -> {$new_slug}");
        
        return $data;
    }

    /**
     * Register custom post statuses
     */
    public function register_custom_post_statuses() {
        register_post_status('cancelled', array(
            'label' => 'Cancelled',
            'public' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>'),
        ));
    }

    /**
     * Check if a tour date has any bookings
     */
    private function tour_date_has_bookings($tour_date_id) {
        global $wpdb;
        
        // Query the tour-booking posts that reference this tour date
        $booking_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'tour_date'
            AND pm.meta_value = %d
            AND p.post_type = 'tour-booking'
            AND p.post_status NOT IN ('trash', 'auto-draft')
        ", $tour_date_id));
        
        return intval($booking_count) > 0;
    }

    /**
     * Handle bulk regeneration of tour date titles via AJAX
     */
    public function handle_regenerate_tour_date_titles() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'bst_regenerate_titles') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        // Get all tour-date posts (including drafts that might have "Auto Draft" titles)
        $tour_dates = get_posts(array(
            'post_type' => 'tour-date',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1
        ));

        $updated_count = 0;
        $total_count = count($tour_dates);
        $errors = array();

        foreach ($tour_dates as $tour_date) {
            try {
                // Get the required fields
                $tour_id = get_field('tour', $tour_date->ID);
                $start_date = get_field('start_date', $tour_date->ID);
                $end_date = get_field('end_date', $tour_date->ID);

                if (!$tour_id || !$start_date || !$end_date) {
                    $errors[] = "ID {$tour_date->ID}: Missing required fields";
                    continue;
                }

                // Get tour name
                $tour_post = get_post($tour_id);
                if (!$tour_post) {
                    $errors[] = "ID {$tour_date->ID}: Invalid tour reference";
                    continue;
                }
                $tour_name = html_entity_decode($tour_post->post_title, ENT_QUOTES, 'UTF-8');

                // Format the date range
                if (date('M', strtotime($start_date)) == date('M', strtotime($end_date))) {
                    // Same month: "19-27 Oct 2025"
                    $date_text = date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date));
                } else {
                    // Different months: "28 Sep - 3 Oct 2025"
                    $date_text = date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                }

                // Create the new title and permalink
                $new_title = $tour_name . ' (' . $date_text . ')';
                $new_slug = sanitize_title($new_title);

                // Check if update is needed - check both title and permalink
                $current_title = $tour_date->post_title;
                $current_slug = $tour_date->post_name;
                
                $title_needs_update = ($current_title !== $new_title) || 
                                     (strpos($current_title, 'Auto Draft') !== false) ||
                                     (strpos($current_title, 'auto-draft') !== false) ||
                                     empty(trim($current_title));
                                     
                $permalink_needs_update = ($current_slug !== $new_slug) ||
                                         (strpos($current_slug, 'auto-draft') !== false) ||
                                         empty(trim($current_slug));
                
                $needs_update = $title_needs_update || $permalink_needs_update;
                
                if ($needs_update) {
                    $result = wp_update_post(array(
                        'ID' => $tour_date->ID,
                        'post_title' => $new_title,
                        'post_name' => $new_slug
                    ));

                    if (!is_wp_error($result)) {
                        $updated_count++;
                    } else {
                        $errors[] = "ID {$tour_date->ID}: " . $result->get_error_message();
                    }
                }
            } catch (Exception $e) {
                $errors[] = "ID {$tour_date->ID}: " . $e->getMessage();
            }
        }

        // Prepare response message
        $message = "Processed {$total_count} tour dates. Updated {$updated_count} titles.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', array_slice($errors, 0, 5)); // Show first 5 errors
            if (count($errors) > 5) {
                $message .= "... and " . (count($errors) - 5) . " more errors.";
            }
        }

        wp_send_json_success($message);
    }

    /**
     * AJAX handler for syncing sold slots and availability
     */
    public function handle_sync_sold_slots_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_sync_sold_slots')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Call the existing sync function from functions.php
        $results = bst_sync_sold_slots();

        if ($results['success']) {
            $total_processed = count($results['log_entries']);
            $updated_count = $results['updated_count'];
            $unchanged_count = $total_processed - $updated_count;
            
            $message = "Sync completed successfully! ";
            $message .= "Processed {$total_processed} tour date(s). ";
            $message .= "Updated: {$updated_count}, Unchanged: {$unchanged_count}.";
            
            // Include error count if any errors occurred
            if (!empty($results['errors'])) {
                $error_count = count($results['errors']);
                $message .= " {$error_count} error(s) encountered.";
            }
            
            wp_send_json_success($message);
        } else {
            $error_count = count($results['errors']);
            $message = "Sync encountered {$error_count} error(s). Please check the logs for details.";
            wp_send_json_error($message);
        }
    }

    /**
     * AJAX handler for release data cleanup
     */
    public function handle_release_data_cleanup() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_release_cleanup_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $current_version = get_option('bst_plugin_version', '1.0.0');
        $force_rerun = isset($_POST['force']) && $_POST['force'] === 'true';
        $repair_repeater = isset($_POST['repair']) && $_POST['repair'] === 'true';

        // Get cleanup tasks for current version
        $cleanup_tasks = bst_get_release_cleanup_tasks();
        if (empty($cleanup_tasks)) {
            wp_send_json_error('No cleanup tasks defined for current version');
            return;
        }

        // Block repeat runs unless force reset and/or re-link from labels is checked.
        if ( ! $force_rerun && ! $repair_repeater && function_exists( 'bst_release_cleanup_is_complete_for_tasks' )
            && bst_release_cleanup_is_complete_for_tasks( $current_version, $cleanup_tasks ) ) {
            wp_send_json_error(
                'Cleanup already completed for version ' . $current_version . ' for all current tasks. Check "Force reset vehicle migration" and/or "Re-link tour repeater from labels" to run again.'
            );
            return;
        }

        global $wpdb;
        $results = array();
        
        try {
            // Execute cleanup tasks based on current version
            $results = bst_execute_release_cleanup_tasks($cleanup_tasks, $current_version, $force_rerun, $repair_repeater);

            if ( function_exists( 'bst_log_release_cleanup_results' ) ) {
                bst_log_release_cleanup_results( $results );
            }
            
            // Mark each task complete under this version (preserves legacy metadata if present).
            $cleanup_status = get_option( 'bst_release_cleanup_status', array() );
            $bucket         = bst_release_cleanup_get_version_bucket( $current_version );
            $ts             = time();
            foreach ( $cleanup_tasks as $task ) {
                if ( empty( $task['name'] ) ) {
                    continue;
                }
                $bucket['tasks'][ bst_release_cleanup_task_slug( $task['name'] ) ] = $ts;
            }
            $cleanup_status[ $current_version ] = $bucket;
            update_option( 'bst_release_cleanup_status', $cleanup_status );
            
            // Success message
            $rerun_note = '';
            if ( $force_rerun ) {
                $rerun_note .= ' (Force reset executed)';
            }
            if ( $repair_repeater ) {
                $rerun_note .= ' (Re-link from labels executed)';
            }
            $message = "Release data cleanup completed successfully for version {$current_version}{$rerun_note}! Full detail is logged to PHP error_log (lines prefixed [BST release cleanup]). " . implode('. ', $results);
            wp_send_json_success(
                array(
                    'message'   => $message,
                    'tools_url' => admin_url( 'admin.php?page=bst_tools_page' ),
                )
            );
            
        } catch (Exception $e) {
            error_log( '[BST release cleanup] [ERROR] ' . $e->getMessage() );
            wp_send_json_error('Cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Tools → add limited-vehicle rows (vehicle + max) on child tour-dates from tours.
     */
    public function handle_sync_limited_vehicles_create() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bst_sync_limited_vehicles_create_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'bst-plugin' ) );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'bst-plugin' ) );
            return;
        }
        if ( ! function_exists( 'bst_migrate_limited_vehicles_create_only_batch' ) ) {
            wp_send_json_error( __( 'Migration is not available.', 'bst-plugin' ) );
            return;
        }
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }
        $packed      = bst_migrate_limited_vehicles_create_only_batch();
        $lines       = isset( $packed['lines'] ) && is_array( $packed['lines'] ) ? $packed['lines'] : array();
        $error_count = isset( $packed['error_count'] ) ? (int) $packed['error_count'] : 0;
        $text        = implode( ' ', array_map( 'wp_strip_all_tags', $lines ) );
        wp_send_json_success(
            array(
                'message'    => $text,
                'has_errors' => $error_count > 0,
            )
        );
    }

    /**
     * Tools → recalculate Sold from bookings; return oversold list.
     */
    public function handle_sync_limited_vehicles_sold() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bst_sync_limited_vehicles_sold_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'bst-plugin' ) );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'bst-plugin' ) );
            return;
        }
        if ( ! function_exists( 'bst_migrate_limited_vehicles_sync_sold_batch' ) ) {
            wp_send_json_error( __( 'Migration is not available.', 'bst-plugin' ) );
            return;
        }
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }
        $packed = bst_migrate_limited_vehicles_sync_sold_batch();
        $lines  = isset( $packed['lines'] ) && is_array( $packed['lines'] ) ? $packed['lines'] : array();
        $errors = isset( $packed['error_count'] ) ? (int) $packed['error_count'] : 0;
        $n      = isset( $packed['rows_updated'] ) ? (int) $packed['rows_updated'] : 0;
        $os     = isset( $packed['oversold'] ) && is_array( $packed['oversold'] ) ? $packed['oversold'] : array();

        /* translators: %d: number of limited-vehicle rows whose sold value changed */
        $msg = sprintf( __( 'Updated sold values for %d limited-vehicle row(s) (where the calculated sold differed from what was stored).', 'bst-plugin' ), $n );
        if ( $errors > 0 ) {
            $msg .= ' ' . implode( ' ', array_map( 'wp_strip_all_tags', $lines ) );
        }

        wp_send_json_success(
            array(
                'message'      => $msg,
                'rows_updated' => $n,
                'oversold'     => $os,
                'has_errors'   => $errors > 0,
            )
        );
    }

    /**
     * Setup BST custom capabilities
     */
    public function setup_bst_capabilities() {
        // No longer needed - security is handled by username check
        // Sensitive operations are only visible to Wayne
    }

    /**
     * AJAX handler to get tour dates for a specific tour
     */
    public function bst_get_tour_dates() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $tour_id = intval($_POST['tour_id']);
        if (!$tour_id) {
            wp_send_json_error('Invalid tour ID');
            return;
        }

        // Get tour dates for this tour
        $tour_dates = get_posts([
            'post_type' => 'tour-date',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => 'tour',
                    'value' => $tour_id,
                    'compare' => '='
                ]
            ],
            'meta_key' => 'start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        ]);

        $dates = [];
        foreach ($tour_dates as $date) {
            // Use standardized tour date title - extract date range from parentheses
            $date_text = $date->post_title; // fallback to full title
            if (preg_match('/\((.*)\)$/', $date->post_title, $matches)) {
                $date_text = $matches[1];
            }
            
            $dates[] = [
                'id' => $date->ID,
                'text' => $date_text
            ];
        }

        wp_send_json_success($dates);
    }

    /**
     * AJAX handler to get tour packages for a specific tour
     */
    public function bst_get_tour_packages() {
        // Log the incoming request
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $tour_id = intval($_POST['tour_id']);
        if (!$tour_id) {
            wp_send_json_error('Invalid tour ID');
            return;
        }

        // Try multiple possible field names for packages
        $packages = get_field('packages', $tour_id);
        
        // If packages field is empty, try package_pricing field
        if (empty($packages)) {
            $package_pricing = get_field('package_pricing', $tour_id);
            
            // Convert package_pricing to packages format if it exists
            if ($package_pricing && is_array($package_pricing)) {
                $packages = [];
                for ($i = 1; $i <= 5; $i++) {
                    if (isset($package_pricing['package_' . $i]) && !empty($package_pricing['package_' . $i])) {
                        $packages[] = [
                            'package_id' => $i,
                            'package_name' => 'Package ' . $i,
                            'price' => floatval($package_pricing['package_' . $i])
                        ];
                    }
                }
                error_log('BST: Converted package_pricing to packages format: ' . print_r($packages, true));
            }
        }
        
        // Try getting all custom fields to see what's available
        $all_fields = get_fields($tour_id);
        
        $result = [];        if ($packages && is_array($packages)) {
            foreach ($packages as $index => $pkg) {
                $pkg_id = isset($pkg['package_id']) ? $pkg['package_id'] : ($index + 1);
                $pkg_name = isset($pkg['package_name']) ? $pkg['package_name'] : '';
                $pkg_price = 0;

                if (isset($pkg['price'])) {
                    $pkg_price = floatval($pkg['price']);
                } elseif (isset($pkg['package_price'])) {
                    $pkg_price = floatval($pkg['package_price']);
                }

                // Skip packages with price of 0
                if ($pkg_name && $pkg_price > 0) {
                    $display_text = $pkg_name;
                    if ($pkg_price > 0) {
                        $display_text .= ' - €' . number_format($pkg_price, 2);
                    }

                    $result[] = [
                        'id' => $pkg_id,
                        'text' => $display_text,
                        'price' => $pkg_price
                    ];
                }
            }
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler to get package configuration data (people, rooms, vehicles)
     */
    public function bst_get_package_config() {
        if (!isset($_POST['package_id']) || empty($_POST['package_id'])) {
            wp_send_json_error('Package ID is required');
            return;
        }

        $package_id = intval($_POST['package_id']);
        
        // Get package configuration from WordPress options
        $config = array(
            'people' => get_option('bst_package_' . $package_id . '_people', 0),
            'rooms' => get_option('bst_package_' . $package_id . '_rooms', 0),
            'vehicles' => get_option('bst_package_' . $package_id . '_vehicles', 0)
        );
        
        wp_send_json_success($config);
    }

    public function update_customer_from_booking() {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'update_customer_from_booking')) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $booking_id = intval($_POST['booking_id']);
        $customer_id = intval($_POST['customer_id']);

        if (!$booking_id || !$customer_id) {
            wp_send_json_error('Booking ID and Customer ID are required');
            return;
        }

        // Get booking data from correct table - include how_heard and source for credit determination
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT guest1_first_name, guest1_last_name, guest1_email, guest1_phone,
                    guest2_first_name, guest2_last_name,
                    booking_commission_percent, booking_commission_reason,
                    how_heard, source
             FROM {$wpdb->prefix}bst_tour_booking 
             WHERE id = %d", 
            $booking_id
        ));

        if (!$booking) {
            wp_send_json_error('Booking not found');
            return;
        }

        // Verify customer exists
        $customer_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bst_customers WHERE id = %d", 
            $customer_id
        ));

        if (!$customer_exists) {
            wp_send_json_error('Customer not found');
            return;
        }

        // Prepare update data - map booking fields to customer fields
        // guest1 = main customer, guest2 = partner
        $update_data = array(
            'first_name' => $booking->guest1_first_name,
            'last_name' => $booking->guest1_last_name,
            'email' => $booking->guest1_email,
            'phone' => $booking->guest1_phone,
            'partner_first' => $booking->guest2_first_name,
            'partner_last' => $booking->guest2_last_name
        );

        // Determine credit using the same logic as the commission calculation
        $credit = null;
        
        // First check if there is a source, look it up in source-code
        if (!empty($booking->source)) {
            $source_code = sanitize_text_field($booking->source);
            $args = array(
                'post_type'      => 'source-code',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'   => 'code',
                        'value' => $source_code,
                        'compare' => '='
                    )
                )
            );
            $query = new WP_Query($args);
            if ($query->have_posts()) {
                $post = $query->posts[0];
                // If "source" is a post meta field:
                $credit = get_field('source', $post->ID); // should be Bill, Claudio, Wayne, Web
            }
        } 

        if (empty($credit) && !empty($booking->how_heard)) {
            // If no source credit found, check 'how heard' for commission
            $how_heard = trim($booking->how_heard);
            $commission_02_values = [
                'Referred by Bill Kniegge',
                'Went on a previous Blue Strada tour'
            ];
            
            if ($how_heard === 'Referred by Wayne Wilson') {
                $credit = "Wayne";
            } elseif ($how_heard === 'Referred by Claudio Angeletti') {
                $credit = "Claudio";
            } elseif (in_array($how_heard, $commission_02_values, true)) {
                $credit = "Bill";
            } else {
                $credit = "Web";
            }
        }
        
        // If still no credit determined, fallback to commission reason mapping
        if (empty($credit) && !empty($booking->booking_commission_reason)) {
            $commission_reason = $booking->booking_commission_reason;
            
            // Map commission reasons to credit recipients
            if (strpos(strtolower($commission_reason), 'bill') !== false) {
                $credit = 'Bill';
            } elseif (strpos(strtolower($commission_reason), 'claudio') !== false) {
                $credit = 'Claudio';
            } elseif (strpos(strtolower($commission_reason), 'wayne') !== false) {
                $credit = 'Wayne';
            } elseif (strpos(strtolower($commission_reason), 'web') !== false || 
                      strpos(strtolower($commission_reason), 'new customer') !== false ||
                      strpos(strtolower($commission_reason), 'repeat customer') !== false) {
                $credit = 'Web';
            }
        }
        
        // Add credit to update data if determined
        if (!empty($credit)) {
            $update_data['credit'] = $credit;
        }

        // Remove null/empty values to avoid overwriting good data
        $update_data = array_filter($update_data, function($value) {
            return $value !== null && $value !== '';
        });

        if (empty($update_data)) {
            wp_send_json_error('No valid data to update from booking');
            return;
        }

        // Update customer
        $result = $wpdb->update(
            $wpdb->prefix . 'bst_customers',
            $update_data,
            array('id' => $customer_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s'), // format for update data
            array('%d') // format for where clause
        );

        if ($result === false) {
            wp_send_json_error('Failed to update customer - database error');
            return;
        }

        wp_send_json_success('Customer updated successfully with ' . count($update_data) . ' fields from booking');
    }

    public function populate_customer_from_email() {
        global $wpdb;
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'populate_customer_from_email')) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $email = sanitize_email($_POST['email']);

        if (!$email || !is_email($email)) {
            wp_send_json_error('Valid email address is required');
            return;
        }

        // Search for customer by email - using same logic as bst_calculate_commission_percent
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_customers WHERE email = %s LIMIT 1", 
            $email
        ));

        if (!$customer) {
            wp_send_json_error('No customer found with email: ' . $email);
            return;
        }

        // Calculate commission using existing function logic
        $commission_percent = 0;
        $commission_reason = 'New Customer';
        
        if (!empty($customer->credit)) {
            $credit_value = floatval($customer->credit);
            if ($credit_value > 0) {
                if ($credit_value >= 500) {
                    $commission_percent = 20;
                    $commission_reason = 'Repeat Customer ($500+)';
                } elseif ($credit_value >= 100) {
                    $commission_percent = 15;
                    $commission_reason = 'Repeat Customer ($100+)';
                } else {
                    $commission_percent = 10;
                    $commission_reason = 'Repeat Customer (under $100)';
                }
            }
        }

        error_log('BST Plugin: Found customer via email populate: ' . $customer->id . ' (' . $customer->first_name . ' ' . $customer->last_name . ') - Commission: ' . $commission_percent . '% (' . $commission_reason . ')');

        wp_send_json_success(array(
            'customer' => $customer,
            'commission' => array(
                'percent' => $commission_percent,
                'reason' => $commission_reason
            )
        ));
    }

    // Dashboard page handler
    function bst_dashboard_page() {
        global $title;
        $title = 'Dashboard';
        include BST_PLUGIN_DIR . 'templates/dashboard.php';
    }

    /**
     * Helper methods for notifications
     */
    public static function add_notification($id, $message, $type = 'info', $dismissible = true, $capabilities = array('manage_options'), $expiry_days = 0) {
        BST_Notifications::add_notice($id, $message, $type, $dismissible, $capabilities, $expiry_days);
    }

    public static function remove_notification($id) {
        BST_Notifications::remove_notice($id);
    }

    public static function get_user_notifications($user_id = null) {
        return BST_Notifications::get_user_notices($user_id);
    }

    /**
     * Cleanup function to remove incorrect availability meta entries
     * Call this function once to migrate from meta to ACF fields
     */
    public function cleanup_availability_meta() {
        global $wpdb;
        
        // Only allow Wayne to run this
        $current_user = wp_get_current_user();
        if ($current_user->user_login !== 'Wayne') {
            wp_die('Unauthorized access.');
        }
        
        // Get all tour dates
        $tour_dates = get_posts([
            'post_type' => 'tour-date',
            'post_status' => 'any',
            'numberposts' => -1
        ]);
        
        $cleaned = 0;
        foreach ($tour_dates as $tour_date) {
            // Check if there's a meta value for availability
            $meta_value = get_post_meta($tour_date->ID, 'availability', true);
            if ($meta_value) {
                // Migrate to ACF field if ACF field is empty
                $acf_value = get_field('available_slots', $tour_date->ID);
                if (empty($acf_value)) {
                    update_field('available_slots', $meta_value, $tour_date->ID);
                }
                
                // Remove the meta entry
                delete_post_meta($tour_date->ID, 'availability');
                $cleaned++;
            }
        }
        
        echo "<div class='notice notice-success'><p>Cleaned up {$cleaned} availability meta entries and migrated to ACF fields.</p></div>";
        
        // Redirect back to the tour edit page after cleanup
        wp_redirect(admin_url('post.php?post=' . $_GET['post'] . '&action=edit&availability_cleaned=1'));
        exit;
    }

    /**
     * Handle the availability cleanup action
     */
    public function handle_availability_cleanup() {
        if (isset($_GET['cleanup_availability']) && $_GET['cleanup_availability'] === '1') {
            $this->cleanup_availability_meta();
        }
    }
    
    /**
     * Custom sorting for tour-date posts in admin
     * When sorting by title, actually sort by tour name and start date
     */
    public function custom_tour_date_sorting($query) {
        // Only run in admin area
        if (!is_admin()) {
            return;
        }
        
        // Only for tour-date post type
        if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'tour-date') {
            return;
        }
        
        // Only when sorting by title
        if (!isset($_GET['orderby']) || $_GET['orderby'] !== 'title') {
            return;
        }
        
        // Get sort order (default to ASC)
        $order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
        
        // Custom sorting: first by tour name, then by start_date
        $query->set('meta_query', array(
            'relation' => 'AND',
            'tour_clause' => array(
                'key' => 'tour',
                'compare' => 'EXISTS'
            ),
            'start_date_clause' => array(
                'key' => 'start_date',
                'compare' => 'EXISTS'
            )
        ));
        
        $query->set('orderby', array(
            'tour_clause' => $order,
            'start_date_clause' => $order
        ));
    }
    
    /**
     * Modify post row actions to add filter preservation for tours and tour-dates
     */
    public function modify_post_row_actions($actions, $post) {
        if (in_array($post->post_type, array('tour', 'tour-date', 'vehicle'), true)) {
            // Keep the default actions but they'll be enhanced by our JavaScript
            // to preserve list state parameters
        }
        if ($post->post_type === 'tour' && current_user_can('edit_posts')) {
            $url = wp_nonce_url(
                add_query_arg(
                    array('action' => 'bst_duplicate_tour', 'post' => $post->ID),
                    admin_url('admin.php')
                ),
                'bst_duplicate_tour_' . $post->ID
            );
            $actions['duplicate'] = '<a href="' . esc_url($url) . '">' . __('Duplicate') . '</a>';
        }
        return $actions;
    }

    public function handle_duplicate_tour() {
        $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
        if (!$post_id || !current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to duplicate this tour.'));
        }
        check_admin_referer('bst_duplicate_tour_' . $post_id);

        $original = get_post($post_id);
        if (!$original || $original->post_type !== 'tour') {
            wp_die(__('Invalid tour.'));
        }

        $new_id = wp_insert_post(array(
            'post_title'   => $original->post_title . ' {Copy}',
            'post_content' => $original->post_content,
            'post_excerpt' => $original->post_excerpt,
            'post_status'  => 'draft',
            'post_type'    => 'tour',
            'post_author'  => get_current_user_id(),
            'menu_order'   => $original->menu_order,
        ), true);

        if (is_wp_error($new_id)) {
            wp_die($new_id->get_error_message());
        }

        // Copy all post meta
        $meta = get_post_meta($post_id);
        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($new_id, $key, maybe_unserialize($value));
            }
        }

        // Copy taxonomies
        $taxonomies = get_object_taxonomies($original->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($new_id, $terms, $taxonomy);
            }
        }

        wp_redirect(admin_url('post.php?action=edit&post=' . $new_id));
        exit;
    }
    
    /**
     * Add JavaScript to modify tour, tour-date, and vehicle edit links with current list state
     */
    public function add_tour_edit_link_script() {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('edit-tour', 'edit-tour-date', 'edit-vehicle'), true)) {
            return;
        }
        
        // Get current list state parameters (tour / tour-date filters are appended server-side via get_edit_post_link).
        $params = array();
        if (isset($_GET['orderby'])) {
            $params['sort_by'] = sanitize_text_field($_GET['orderby']);
        }
        if (isset($_GET['order'])) {
            $params['sort_order'] = sanitize_text_field($_GET['order']);
        }
        if (isset($_GET['s']) && !empty($_GET['s'])) $params['search'] = sanitize_text_field($_GET['s']);
        if (isset($_GET['post_status']) && !empty($_GET['post_status'])) {
            $params['filter_status'] = sanitize_text_field($_GET['post_status']);
        }
        if (isset($_GET['m']) && !empty($_GET['m'])) $params['filter_date'] = sanitize_text_field($_GET['m']);
        if ( 'edit-vehicle' === $screen->id && isset( $_GET['bst_vehicle_type'] ) ) {
            $vtf = sanitize_text_field( wp_unslash( $_GET['bst_vehicle_type'] ) );
            if ( in_array( $vtf, array( 'car', 'motorcycle' ), true ) ) {
                $params['bst_vehicle_type'] = $vtf;
            }
        }
        
        if (empty($params)) {
            return; // No parameters to add
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Parameters to add to edit links
            var listParams = <?php echo json_encode($params); ?>;
            
            // Modify title links in the list table
            $('.wp-list-table .row-title').each(function() {
                var $link = $(this);
                var href = $link.attr('href');
                
                if (href && href.indexOf('action=edit') !== -1) {
                    // Add our parameters to the edit URL
                    var separator = href.indexOf('?') !== -1 ? '&' : '?';
                    var paramString = $.param(listParams);
                    
                    if (paramString) {
                        $link.attr('href', href + separator + paramString);
                    }
                }
            });
            
            // Also modify any other edit links in row actions
            $('.wp-list-table .row-actions .edit a').each(function() {
                var $link = $(this);
                var href = $link.attr('href');
                
                if (href && href.indexOf('action=edit') !== -1) {
                    // Add our parameters to the edit URL
                    var separator = href.indexOf('?') !== -1 ? '&' : '?';
                    var paramString = $.param(listParams);
                    
                    if (paramString) {
                        $link.attr('href', href + separator + paramString);
                    }
                }
            });
            
            // Also modify our custom View buttons in the actions column
            $('.wp-list-table .column-actions .view-booking').each(function() {
                var $link = $(this);
                var href = $link.attr('href');
                
                if (href && href.indexOf('action=edit') !== -1) {
                    // Add our parameters to the edit URL
                    var separator = href.indexOf('?') !== -1 ? '&' : '?';
                    var paramString = $.param(listParams);
                    
                    if (paramString) {
                        $link.attr('href', href + separator + paramString);
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Preserve list state parameters after saving a post (like tour bookings does)
     */
    public function preserve_list_state_after_save($location, $post_id) {
        $post = get_post($post_id);
        
        if (!$post || !in_array($post->post_type, array('tour', 'tour-date', 'vehicle'), true)) {
            return $location;
        }
        
        // Get list state parameters from the current request
        $list_params = array();
        $params_to_preserve = array(
            'sort_by', 'sort_order', 'filter_tour_type', 'filter_tour', 'filter_author', 'search', 'filter_status', 'filter_date'
        );
        
        foreach ($params_to_preserve as $param) {
            if (isset($_POST[$param]) && !empty($_POST[$param])) {
                $list_params[$param] = sanitize_text_field($_POST[$param]);
            } elseif (isset($_GET[$param]) && !empty($_GET[$param])) {
                $list_params[$param] = sanitize_text_field($_GET[$param]);
            }
        }
        if ( isset( $_POST['bst_vehicle_type'] ) ) {
            $vtf = sanitize_text_field( wp_unslash( $_POST['bst_vehicle_type'] ) );
            if ( in_array( $vtf, array( 'car', 'motorcycle' ), true ) ) {
                $list_params['bst_vehicle_type'] = $vtf;
            }
        } elseif ( isset( $_GET['bst_vehicle_type'] ) ) {
            $vtf = sanitize_text_field( wp_unslash( $_GET['bst_vehicle_type'] ) );
            if ( in_array( $vtf, array( 'car', 'motorcycle' ), true ) ) {
                $list_params['bst_vehicle_type'] = $vtf;
            }
        }
        
        // Add list state parameters to the redirect location
        if (!empty($list_params)) {
            $location = add_query_arg($list_params, $location);
        }
        
        return $location;
    }
    
    /**
     * Add navigation elements to edit pages for tours, tour-dates, and vehicles
     */
    public function add_edit_page_navigation($post) {
        if (!in_array($post->post_type, array('tour', 'tour-date', 'vehicle'), true)) {
            return;
        }
        
        // Get list state parameters using tour booking system parameter names
        $list_state = array();
        
        // Map our parameters to WordPress list / query parameters.
        $param_mapping = array(
            'sort_by' => 'orderby',
            'sort_order' => 'order',
            'filter_tour_type' => 'tour_type_filter',
            'filter_tour' => 'meta_tour_filter',
            'search' => 's',
            'filter_status' => 'post_status',
            'filter_date' => 'm',
            // Mine tab: list uses ?author=ID; on post.php we use filter_author=ID to avoid clashes.
            'filter_author' => 'author',
        );

        foreach ( $param_mapping as $our_param => $wp_param ) {
            if ( ! isset( $_GET[ $our_param ] ) || '' === $_GET[ $our_param ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                continue;
            }
            if ( 'filter_author' === $our_param ) {
                $aid = absint( wp_unslash( $_GET['filter_author'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $aid ) {
                    $list_state['author'] = $aid;
                }
                continue;
            }
            $list_state[ $wp_param ] = sanitize_text_field( wp_unslash( $_GET[ $our_param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        // Vehicle list filter (?bst_vehicle_type=car|motorcycle) — same param name on list and edit screen.
        if ( isset( $_GET['bst_vehicle_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $vtf = sanitize_text_field( wp_unslash( $_GET['bst_vehicle_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( in_array( $vtf, array( 'car', 'motorcycle' ), true ) ) {
                $list_state['bst_vehicle_type'] = $vtf;
            }
        }
        
        // Build the back to list URL
        $list_url = admin_url('edit.php?post_type=' . $post->post_type);
        if (!empty($list_state)) {
            $list_url = add_query_arg($list_state, $list_url);
        }
        
        // Try to get position information and navigation buttons
        // If no list_state, create a basic one for navigation
        if (empty($list_state)) {
            $list_state = array(); // Empty state for basic navigation
        }
        
        $record_info = $this->get_record_position_info($post, $list_state);
        $nav_buttons = $this->get_navigation_buttons($post, $list_state);
        
        // Output the navigation HTML
        echo '<div style="background: #f1f1f1; border: 1px solid #ccd0d4; border-radius: 4px; padding: 10px; margin: 10px 0;">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
        
        // Left side: Back to list button
        echo '<div>';
        echo '<a href="' . esc_url($list_url) . '" class="button button-secondary bst-edit-nav-link">';
        echo '<span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle; margin-right: 5px;"></span>';
        echo 'Back to ' . ucfirst(str_replace('-', ' ', $post->post_type ?? '')) . ' List';
        echo '</a>';
        echo '</div>';
        
        // Center: Record position info
        if ($record_info) {
            echo '<div style="font-weight: 500;">' . $record_info . '</div>';
        }
        
        // Right side: Navigation buttons
        if ($nav_buttons) {
            echo '<div>' . $nav_buttons . '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Add hidden form fields to preserve list state during save (like tour bookings)
        if (!empty($list_state)) {
            foreach ($list_state as $wp_param => $value) {
                $our_param = array_search($wp_param, $param_mapping, true);
                if ($our_param) {
                    echo '<input type="hidden" name="' . esc_attr($our_param) . '" value="' . esc_attr($value) . '">';
                }
            }
        }
        if ( ! empty( $list_state['bst_vehicle_type'] ) && in_array( $list_state['bst_vehicle_type'], array( 'car', 'motorcycle' ), true ) ) {
            echo '<input type="hidden" name="bst_vehicle_type" value="' . esc_attr( $list_state['bst_vehicle_type'] ) . '">';
        }

        // Ensure navigation links do not trigger the browser's unsaved-changes dialog
        echo '<script type="text/javascript">';
        echo 'jQuery(document).ready(function($){';
        echo '  $(".bst-edit-nav-link").on("click", function(){';
        echo '    // Clear any beforeunload handlers (ACF / secure-custom-fields, etc.) just for these nav links';
        echo '    $(window).off("beforeunload");';
        echo '    if (typeof window.onbeforeunload !== "undefined") { window.onbeforeunload = null; }';
        echo '  });';
        echo '});';
        echo '</script>';
    }

    /**
     * post_status for edit-screen nav queries: explicit tab from list_state, else same as default admin browse
     * (see bst_wp_admin_default_browse_post_status_slugs) — not `any`, so e.g. cancelled-only rows match the list.
     *
     * @param array $list_state Parsed list parameters (orderby, author, post_status, …).
     * @return string|string[] WP_Query post_status argument.
     */
    private function bst_get_nav_query_post_status( $list_state ) {
        if ( ! empty( $list_state['post_status'] ) ) {
            return $list_state['post_status'];
        }
        if ( function_exists( 'bst_wp_admin_default_browse_post_status_slugs' ) ) {
            return bst_wp_admin_default_browse_post_status_slugs();
        }
        return 'any';
    }
    
    /**
     * Get record position information
     */
    private function get_record_position_info($post, $list_state) {
        // Build a query matching the list state to get total count and position
        $query_args = array(
            'post_type' => $post->post_type,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => $this->bst_get_nav_query_post_status( $list_state ),
        );
        
        // Add search if present
        if (isset($list_state['s']) && !empty($list_state['s'])) {
            $query_args['s'] = $list_state['s'];
        }
        
        // Add tour type filter if present (for tours)
        if (isset($list_state['tour_type_filter']) && !empty($list_state['tour_type_filter'])) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'tour-type-code',
                    'field'    => 'slug',
                    'terms'    => $list_state['tour_type_filter'],
                ),
            );
        }

        // Mine tab: restrict to author (same as ?author= on edit.php list).
        if ( isset( $list_state['author'] ) && $list_state['author'] > 0 ) {
            $query_args['author'] = absint( $list_state['author'] );
        }
        
        // Add tour filter if present (for tour-dates)
        if (isset($list_state['meta_tour_filter']) && !empty($list_state['meta_tour_filter'])) {
            $query_args['meta_query'] = array(
                array(
                    'key' => 'tour',
                    'value' => $list_state['meta_tour_filter'],
                    'compare' => '='
                )
            );
        }

        // Vehicle list type filter (matches edit.php?post_type=vehicle&bst_vehicle_type=…)
        if ( 'vehicle' === $post->post_type && ! empty( $list_state['bst_vehicle_type'] ) ) {
            $vtf = $list_state['bst_vehicle_type'];
            if ( in_array( $vtf, array( 'car', 'motorcycle' ), true ) ) {
                $mq = isset( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();
                $mq[] = array(
                    'key'   => 'vehicle_type',
                    'value' => $vtf,
                );
                $query_args['meta_query'] = $mq;
            }
        }
        
        // Add ordering - for tour-dates we need special handling
        if ($post->post_type === 'tour-date') {
            // For tour-dates, use the same sorting logic as navigation buttons
            $orderby = isset($list_state['orderby']) ? $list_state['orderby'] : '';
            $order = isset($list_state['order']) ? strtoupper($list_state['order']) : 'ASC';
            
            if ($orderby === 'tour') {
                // Sort by tour title using the same logic as the list table
                // Don't set meta_key/orderby since we're using custom SQL
                $query_args['order'] = $order;
                
                // We need to use the same complex join as the list table
                add_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_tour_sort'));
                add_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_tour_sort'));
                
                $query = new WP_Query($query_args);
                $sorted_post_ids = $query->posts;
                
                // Clean up the filters
                remove_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_tour_sort'));
                remove_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_tour_sort'));
                
            } elseif ($orderby === 'start_date') {
                // Sort by start date using the same custom SQL as the list table
                // Don't set meta_key/orderby since we're using custom SQL
                $query_args['order'] = $order;
                
                // Use the same custom join and orderby as the list table
                add_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_start_date_sort'));
                add_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_start_date_sort'));
                
                $query = new WP_Query($query_args);
                $sorted_post_ids = $query->posts;
                
                // Clean up the filters
                remove_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_start_date_sort'));
                remove_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_start_date_sort'));
                
            } elseif ($orderby === 'id') {
                // Sort by post ID
                $query_args['orderby'] = 'ID';
                $query_args['order'] = $order;
                
                $query = new WP_Query($query_args);
                $sorted_post_ids = $query->posts;
                
            } else {
                // Default sort (no specific orderby) - use tour then start_date like the list table
                $query_args['meta_key'] = 'tour';
                $query_args['orderby'] = 'meta_value';
                $query_args['meta_type'] = 'NUMERIC';
                $query_args['order'] = 'ASC';
                
                // Use the same complex join for default sorting
                add_filter('posts_join', array($this, 'custom_nav_tour_date_join_with_start_date'));
                add_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_with_start_date'));
                
                $query = new WP_Query($query_args);
                $sorted_post_ids = $query->posts;
                
                // Clean up the filters
                remove_filter('posts_join', array($this, 'custom_nav_tour_date_join_with_start_date'));
                remove_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_with_start_date'));
            }
            
            if (empty($sorted_post_ids)) {
                return '';
            }
            
            $total = count($sorted_post_ids);
            $position = array_search($post->ID, $sorted_post_ids);
            
            if ($position === false) {
                return "Record not found in current filter";
            }
            
            return sprintf('Record %d of %d', $position + 1, $total);
        } else {
            // For other post types, use simple sorting
            if (isset($list_state['orderby'])) {
                $query_args['orderby'] = $list_state['orderby'];
                $query_args['order'] = isset($list_state['order']) ? $list_state['order'] : 'ASC';
            } else {
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'ASC';
            }
            
            $query = new WP_Query($query_args);
            $post_ids = $query->posts;
            
            if (empty($post_ids)) {
                return '';
            }
            
            $total = count($post_ids);
            $position = array_search($post->ID, $post_ids);
            
            if ($position === false) {
                return "Record not found in current filter";
            }
            
            return sprintf('Record %d of %d', $position + 1, $total);
        }
    }
    
    /**
     * Get navigation buttons for previous/next records
     */
    private function get_navigation_buttons($post, $list_state) {
        // Build a query matching the list state
        $query_args = array(
            'post_type' => $post->post_type,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => $this->bst_get_nav_query_post_status( $list_state ),
        );
        
        // Add search if present
        if (isset($list_state['s']) && !empty($list_state['s'])) {
            $query_args['s'] = $list_state['s'];
        }
        
        // Add tour type filter if present (for tours)
        if (isset($list_state['tour_type_filter']) && !empty($list_state['tour_type_filter'])) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'tour-type-code',
                    'field'    => 'slug',
                    'terms'    => $list_state['tour_type_filter'],
                ),
            );
        }

        if ( isset( $list_state['author'] ) && $list_state['author'] > 0 ) {
            $query_args['author'] = absint( $list_state['author'] );
        }
        
        // Add tour filter if present (for tour-dates)
        if (isset($list_state['meta_tour_filter']) && !empty($list_state['meta_tour_filter'])) {
            $query_args['meta_query'] = array(
                array(
                    'key' => 'tour',
                    'value' => $list_state['meta_tour_filter'],
                    'compare' => '='
                )
            );
        }

        if ( 'vehicle' === $post->post_type && ! empty( $list_state['bst_vehicle_type'] ) ) {
            $vtf = $list_state['bst_vehicle_type'];
            if ( in_array( $vtf, array( 'car', 'motorcycle' ), true ) ) {
                $mq = isset( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();
                $mq[] = array(
                    'key'   => 'vehicle_type',
                    'value' => $vtf,
                );
                $query_args['meta_query'] = $mq;
            }
        }
        
        // Handle sorting - special case for tour-dates
        if ($post->post_type === 'tour-date') {
            // For tour-dates, we need to replicate the complex sorting from the list table
            $orderby = isset($list_state['orderby']) ? $list_state['orderby'] : '';
            $order = isset($list_state['order']) ? strtoupper($list_state['order']) : 'ASC';
            
            if ($orderby === 'tour') {
                // Sort by tour title using the same logic as the list table
                // Don't set meta_key/orderby since we're using custom SQL
                $query_args['order'] = $order;
                
                // We need to use the same complex join as the list table
                add_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_tour_sort'));
                add_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_tour_sort'));
                
                $query = new WP_Query($query_args);
                $post_ids = $query->posts;
                
                // Clean up the filters
                remove_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_tour_sort'));
                remove_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_tour_sort'));
                
            } elseif ($orderby === 'start_date') {
                // Sort by start date using the same custom SQL as the list table
                // Don't set meta_key/orderby since we're using custom SQL
                $query_args['order'] = $order;
                
                // Use the same custom join and orderby as the list table
                add_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_start_date_sort'));
                add_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_start_date_sort'));
                
                $query = new WP_Query($query_args);
                $post_ids = $query->posts;
                
                // Clean up the filters
                remove_filter('posts_join', array($this, 'custom_nav_tour_date_join_for_start_date_sort'));
                remove_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_for_start_date_sort'));
                
            } elseif ($orderby === 'id') {
                // Sort by post ID
                $query_args['orderby'] = 'ID';
                $query_args['order'] = $order;
                
                $query = new WP_Query($query_args);
                $post_ids = $query->posts;
                
            } else {
                // Default sort (no specific orderby) - use tour then start_date like the list table
                $query_args['meta_key'] = 'tour';
                $query_args['orderby'] = 'meta_value';
                $query_args['meta_type'] = 'NUMERIC';
                $query_args['order'] = 'ASC';
                
                // Use the same complex join for default sorting
                add_filter('posts_join', array($this, 'custom_nav_tour_date_join_with_start_date'));
                add_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_with_start_date'));
                
                $query = new WP_Query($query_args);
                $post_ids = $query->posts;
                
                // Clean up the filters
                remove_filter('posts_join', array($this, 'custom_nav_tour_date_join_with_start_date'));
                remove_filter('posts_orderby', array($this, 'custom_nav_tour_date_orderby_with_start_date'));
            }
        } else {
            // For other post types, use simple sorting
            if (isset($list_state['orderby'])) {
                $query_args['orderby'] = $list_state['orderby'];
                $query_args['order'] = isset($list_state['order']) ? $list_state['order'] : 'ASC';
            } else {
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'ASC';
            }
            
            $query = new WP_Query($query_args);
            $post_ids = $query->posts;
        }
        
        if (empty($post_ids)) {
            return '';
        }
        
        $current_position = array_search($post->ID, $post_ids);
        
        if ($current_position === false) {
            return '';
        }
        
        $buttons = array();
        
        // Previous button
        if ($current_position > 0) {
            $prev_id = $post_ids[$current_position - 1];
            // Convert list_state back to our parameter format for the edit URLs
            $edit_params = array();
            foreach ($list_state as $wp_param => $value) {
                switch ($wp_param) {
                    case 'orderby':
                        $edit_params['sort_by'] = $value;
                        break;
                    case 'order':
                        $edit_params['sort_order'] = $value;
                        break;
                    case 'tour_type_filter':
                        $edit_params['filter_tour_type'] = $value;
                        break;
                    case 'meta_tour_filter':
                        $edit_params['filter_tour'] = $value;
                        break;
                    case 'author':
                        $edit_params['filter_author'] = $value;
                        break;
                    case 's':
                        $edit_params['search'] = $value;
                        break;
                    case 'post_status':
                        $edit_params['filter_status'] = $value;
                        break;
                    case 'm':
                        $edit_params['filter_date'] = $value;
                        break;
                    case 'bst_vehicle_type':
                        if ( in_array( (string) $value, array( 'car', 'motorcycle' ), true ) ) {
                            $edit_params['bst_vehicle_type'] = $value;
                        }
                        break;
                }
            }
            
            $prev_url = add_query_arg($edit_params, admin_url('post.php?post=' . $prev_id . '&action=edit'));
            $buttons[] = '<a href="' . esc_url($prev_url) . '" class="button button-secondary bst-edit-nav-link">';
            $buttons[] = '<span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Previous';
            $buttons[] = '</a>';
        }
        
        // Next button
        if ($current_position < count($post_ids) - 1) {
            $next_id = $post_ids[$current_position + 1];
            // Convert list_state back to our parameter format for the edit URLs
            $edit_params = array();
            foreach ($list_state as $wp_param => $value) {
                switch ($wp_param) {
                    case 'orderby':
                        $edit_params['sort_by'] = $value;
                        break;
                    case 'order':
                        $edit_params['sort_order'] = $value;
                        break;
                    case 'tour_type_filter':
                        $edit_params['filter_tour_type'] = $value;
                        break;
                    case 'meta_tour_filter':
                        $edit_params['filter_tour'] = $value;
                        break;
                    case 'author':
                        $edit_params['filter_author'] = $value;
                        break;
                    case 's':
                        $edit_params['search'] = $value;
                        break;
                    case 'post_status':
                        $edit_params['filter_status'] = $value;
                        break;
                    case 'm':
                        $edit_params['filter_date'] = $value;
                        break;
                    case 'bst_vehicle_type':
                        if ( in_array( (string) $value, array( 'car', 'motorcycle' ), true ) ) {
                            $edit_params['bst_vehicle_type'] = $value;
                        }
                        break;
                }
            }
            
            $next_url = add_query_arg($edit_params, admin_url('post.php?post=' . $next_id . '&action=edit'));
            $buttons[] = '<a href="' . esc_url($next_url) . '" class="button button-secondary bst-edit-nav-link" style="margin-left: 5px;">';
            $buttons[] = 'Next <span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle;"></span>';
            $buttons[] = '</a>';
        }
        
        return implode('', $buttons);
    }
    
    /**
     * Custom join for navigation tour sorting (matches list table)
     */
    public function custom_nav_tour_date_join_for_tour_sort($join) {
        global $wpdb;
        
        // Join the tour posts table and start_date meta
        $join .= " LEFT JOIN {$wpdb->postmeta} AS tour_meta ON {$wpdb->posts}.ID = tour_meta.post_id AND tour_meta.meta_key = 'tour'";
        $join .= " LEFT JOIN {$wpdb->posts} AS tour_posts ON tour_meta.meta_value = tour_posts.ID";
        $join .= " LEFT JOIN {$wpdb->postmeta} AS start_date_meta ON {$wpdb->posts}.ID = start_date_meta.post_id AND start_date_meta.meta_key = 'start_date'";
        
        return $join;
    }
    
    /**
     * Custom orderby for navigation tour sorting (matches list table)
     */
    public function custom_nav_tour_date_orderby_for_tour_sort($orderby) {
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
        // Sort by tour title first, then by start_date (using CAST for proper date sorting)
        $new_orderby = "tour_posts.post_title {$order}, CAST(start_date_meta.meta_value AS DATE) {$order}";
        
        return $new_orderby;
    }
    
    /**
     * Custom join for navigation default sorting with start_date (matches list table)
     */
    public function custom_nav_tour_date_join_with_start_date($join) {
        global $wpdb;
        
        // Join the tour posts table and start_date meta
        $join .= " LEFT JOIN {$wpdb->postmeta} AS tour_meta ON {$wpdb->posts}.ID = tour_meta.post_id AND tour_meta.meta_key = 'tour'";
        $join .= " LEFT JOIN {$wpdb->posts} AS tour_posts ON tour_meta.meta_value = tour_posts.ID";
        $join .= " LEFT JOIN {$wpdb->postmeta} AS start_date_meta ON {$wpdb->posts}.ID = start_date_meta.post_id AND start_date_meta.meta_key = 'start_date'";
        
        return $join;
    }
    
    /**
     * Custom orderby for navigation default sorting (matches list table)
     */
    public function custom_nav_tour_date_orderby_with_start_date($orderby) {
        return "tour_posts.post_title ASC, start_date_meta.meta_value ASC";
    }
    
    /**
     * Custom join for navigation start_date sorting (matches list table)
     */
    public function custom_nav_tour_date_join_for_start_date_sort($join) {
        global $wpdb;
        
        // Join start_date meta with a specific alias for sorting
        $join .= " LEFT JOIN {$wpdb->postmeta} AS start_date_sort_meta ON {$wpdb->posts}.ID = start_date_sort_meta.post_id AND start_date_sort_meta.meta_key = 'start_date'";
        
        return $join;
    }
    
    /**
     * Custom orderby for navigation start_date sorting (matches list table)
     */
    public function custom_nav_tour_date_orderby_for_start_date_sort($orderby) {
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
        // Use CAST as DATE for more reliable date sorting - same as list table
        return "CAST(start_date_sort_meta.meta_value AS DATE) {$order}";
    }
    
    /**
     * AJAX handler for deleting tour bookings (legacy method)
     * Delegates to the centralized deletion function
     */
    public function bst_delete_tour_booking() {
        // Delegate to the centralized function
        bst_delete_tour_booking();
    }

    /**
     * AJAX handler for checking real-time tour availability
     * Used to prevent overbooking when users have stale page data
     */
    public function check_tour_availability() {
        // Sanitize input
        $tour_date_id = intval($_POST['tour_date_id']);
        $package_id = intval($_POST['package_id']);

        if (!$tour_date_id || !$package_id) {
            wp_send_json_error('Tour date ID and package ID are required');
            return;
        }

        try {
            // Validate that the tour date post exists
            $tour_date_post = get_post($tour_date_id);
            if (!$tour_date_post || $tour_date_post->post_type !== 'tour-date') {
                wp_send_json_error('Tour date not found');
                return;
            }

            // Get tour ID from the tour date post
            $tour_id = get_field('tour', $tour_date_id);
            if (is_object($tour_id)) {
                $tour_id = $tour_id->ID;
            } elseif (is_array($tour_id)) {
                $tour_id = $tour_id['ID'];
            }

            if (!$tour_id) {
                wp_send_json_error('Tour not found for this date');
                return;
            }

            // Get package configuration from WordPress options (same as bst_get_package_config)
            $package_vehicles = intval(get_option('bst_package_' . $package_id . '_vehicles', 0));
            
            if ($package_vehicles <= 0) {
                wp_send_json_error("Package {$package_id} configuration not found or invalid");
                return;
            }

            // Simple check: get available slots from tour date
            $available_slots = intval(get_field('available_slots', $tour_date_id));

            // Can book if package vehicles needed <= available slots
            $can_book = ($package_vehicles <= $available_slots);

            // Send admin notification if availability check fails
            if (!$can_book) {
                $this->send_availability_failure_notification($tour_date_id, $available_slots, $package_vehicles);
            }

            wp_send_json_success([
                'available_slots' => $available_slots,
                'package_vehicles' => $package_vehicles,
                'can_book' => $can_book
            ]);

        } catch (Exception $e) {
            error_log('BST Availability Check Error: ' . $e->getMessage());
            wp_send_json_error('Error checking availability: ' . $e->getMessage());
        }
    }

    /**
     * Send admin notification when availability check fails
     */
    private function send_availability_failure_notification($tour_date_id, $available_slots, $package_vehicles) {
        // Get tour date information
        $tour_date_post = get_post($tour_date_id);
        $tour_id = get_field('tour', $tour_date_id);
        
        if (is_object($tour_id)) {
            $tour_id = $tour_id->ID;
        } elseif (is_array($tour_id)) {
            $tour_id = $tour_id['ID'];
        }
        
        $tour_title = get_the_title($tour_id);
        $tour_date_title = get_the_title($tour_date_id);
        $start_date = get_field('start_date', $tour_date_id);
        
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Format the email
        $subject = 'Booking Attempt Failed - Insufficient Availability';
        
        $message = "A customer attempted to book a tour but there was insufficient availability.\n\n";
        $message .= "Tour: {$tour_title}\n";
        $message .= "Date: {$tour_date_title}\n";
        $message .= "Start Date: {$start_date}\n";
        $message .= "Available Slots: {$available_slots}\n";
        $message .= "Required Slots: {$package_vehicles}\n\n";
        $message .= "Time: " . current_time('Y-m-d H:i:s') . "\n";
        $message .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n\n";
        $message .= "The customer was shown an error message and the booking was prevented.\n\n";
        $message .= "Admin Panel: " . admin_url('edit.php?post_type=tour-date');
        
        // Send the email
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Schedule daily availability sync if not already scheduled
     */
    public function schedule_daily_availability_sync() {
        // Use transient to check only once per day
        if (get_transient('bst_availability_cron_check')) {
            return;
        }
        set_transient('bst_availability_cron_check', true, DAY_IN_SECONDS);
        
        if (!wp_next_scheduled('bst_daily_availability_sync')) {
            // Schedule to run daily at 3:00 AM
            wp_schedule_event(strtotime('3:00 AM tomorrow'), 'daily', 'bst_daily_availability_sync');
        }
    }

    /**
     * Run the daily availability sync and send notification
     */
    public function run_daily_availability_sync() {
        // Call the existing sync function
        $results = bst_sync_sold_slots();
        
        // Send notification email to admin only if running automated (not manual button click)
        $admin_email = get_option('admin_email');
        
        if ($results['success']) {
            $total_processed = isset($results['processed_count']) ? $results['processed_count'] : count($results['log_entries']);
            $updated_count = $results['updated_count'];
            $unchanged_count = $total_processed - $updated_count;
            
            $subject = 'Daily Tour Availability Sync Completed';
            
            $message = "The daily tour availability sync has completed successfully.\n\n";
            $message .= "SYNC SUMMARY:\n";
            $message .= "Total tour dates processed: {$total_processed}\n";
            $message .= "Tour dates updated: {$updated_count}\n";
            $message .= "Tour dates unchanged: {$unchanged_count}\n\n";
            
            if (!empty($results['errors'])) {
                $error_count = count($results['errors']);
                $message .= "Errors encountered: {$error_count}\n\n";
                $message .= "ERROR DETAILS:\n";
                foreach ($results['errors'] as $error) {
                    $message .= "- {$error}\n";
                }
                $message .= "\n";
            }
            
            $message .= "Time completed: " . current_time('Y-m-d H:i:s') . "\n\n";
            $message .= "This is an automated daily sync. Manual syncs can be performed in the admin panel.\n\n";
            $message .= "Admin Panel: " . admin_url('admin.php?page=bst-settings');
            
            wp_mail($admin_email, $subject, $message);
        } else {
            // Send error notification
            $error_count = count($results['errors']);
            $subject = 'Daily Tour Availability Sync Failed';
            
            $message = "The daily tour availability sync encountered errors and could not complete successfully.\n\n";
            $message .= "ERRORS ENCOUNTERED ({$error_count}):\n";
            foreach ($results['errors'] as $error) {
                $message .= "- {$error}\n";
            }
            $message .= "\nTime: " . current_time('Y-m-d H:i:s') . "\n\n";
            $message .= "Please check the system and run a manual sync if needed.\n\n";
            $message .= "Admin Panel: " . admin_url('admin.php?page=bst-settings');
            
            wp_mail($admin_email, $subject, $message);
        }
    }

    /**
     * Manual trigger for availability sync
     */
    public function run_availability_sync_manual() {
        check_ajax_referer('bst_manual_cron', '_wpnonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Run the sync function
        $results = bst_sync_sold_slots();
        
        if ($results['success']) {
            $message = "Manual availability sync completed successfully.\n";
            $processed_count = isset($results['processed_count']) ? $results['processed_count'] : count($results['log_entries']);
            $message .= "Processed: " . $processed_count . " tour dates\n";
            $message .= "Updated: " . $results['updated_count'] . " tour dates\n";
            if (!empty($results['errors'])) {
                $message .= "Errors: " . count($results['errors']) . "\n";
            }
            wp_send_json_success($message);
        } else {
            wp_send_json_error('Sync failed: ' . implode(', ', $results['errors']));
        }
    }

    /**
     * Manual trigger for exchange rates update
     */
    public function run_exchange_rates_manual() {
        check_ajax_referer('bst_manual_cron', '_wpnonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Run the exchange rates function
        $result = bst_fetch_exchange_rates();
        
        if ($result) {
            wp_send_json_success('Exchange rates updated successfully');
        } else {
            wp_send_json_error('Failed to update exchange rates');
        }
    }

    public function add_overbooking_dashboard_widget() {
        wp_add_dashboard_widget(
            'bst_dashboard_summary_widget',
            __( 'Tours & Bookings Summary', 'bst-plugin' ),
            array($this, 'bst_dashboard_summary_widget_content')
        );
    }

    public function bst_dashboard_summary_widget_content() {
        global $wpdb;
        $plugin_dashboard_url = admin_url('admin.php?page=bst-plugin');
        $booking_table = $wpdb->prefix . 'bst_tour_booking';

        // 1. Overbooked tour dates (slot capacity)
        $overbooked_dates = $this->get_overbooked_tour_dates();

        // 1b. Limited-vehicle oversold (sold from bookings > max on tour-date repeater)
        $lv_oversold_count = 0;
        if ( function_exists( 'bst_limited_vehicle_dashboard_oversold_rows' ) ) {
            $lv_oversold_count = count( bst_limited_vehicle_dashboard_oversold_rows() );
        }

        // 2. Waiting list bookings
        $waiting_list_count = $wpdb->get_var("SELECT COUNT(*) FROM $booking_table WHERE booking_status = 'Waiting List'");

        // 3. Processing payments (SEPA, etc. awaiting confirmation)
        $processing_count = $wpdb->get_var("SELECT COUNT(*) FROM $booking_table WHERE booking_status = 'Processing'");

        // 4. Payment failed
        $payment_failed_count = $wpdb->get_var("SELECT COUNT(*) FROM $booking_table WHERE booking_status = 'Payment Failed'");

        // 5. Bank wire pending (candidate rows; matches templates/dashboard.php query)
        $bank_wire_count = $wpdb->get_var("
            SELECT COUNT(*) FROM $booking_table 
            WHERE (
                booking_status = 'Pending' OR
                (deposit_payment_method = 'Bank Wire' AND (deposit_payment_amount IS NULL OR deposit_payment_amount = 0)) OR
                (balance_payment_method = 'Bank Wire' AND (balance_payment_amount IS NULL OR balance_payment_amount = 0)) OR
                (additional_payment_method = 'Bank Wire' AND (additional_payment_amount IS NULL OR additional_payment_amount = 0)) OR
                (deposit_payment_method = 'Bank Wire' AND deposit_payment_status IN ('Pending', 'Processing')) OR
                (balance_payment_method = 'Bank Wire' AND balance_payment_status IN ('Pending', 'Processing')) OR
                (additional_payment_method = 'Bank Wire' AND additional_payment_status IN ('Pending', 'Processing'))
            )
        ");

        // 6. Reservations not booked
        $reservations_count = $wpdb->get_var("SELECT COUNT(*) FROM $booking_table WHERE booking_status = 'Reserved' AND created_date < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");

        // 7. Finalization needed - match exact dashboard criteria
        $one_twenty_days_from_now = date('Y-m-d', strtotime('+120 days'));
        $finalization_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $booking_table b
            LEFT JOIN {$wpdb->prefix}posts td ON b.tour_date_id = td.ID AND td.post_type = 'tour-date'
            LEFT JOIN {$wpdb->prefix}postmeta td_meta ON td.ID = td_meta.post_id AND td_meta.meta_key = 'start_date'
            WHERE b.booking_method = 'Web'
            AND b.booking_status = 'Booked'
            AND td_meta.meta_value IS NOT NULL
            AND (
                (LENGTH(td_meta.meta_value) = 8 AND STR_TO_DATE(td_meta.meta_value, '%%Y%%m%%d') <= %s AND STR_TO_DATE(td_meta.meta_value, '%%Y%%m%%d') >= CURDATE()) OR
                (LENGTH(td_meta.meta_value) = 10 AND td_meta.meta_value <= %s AND td_meta.meta_value >= CURDATE())
            )
            AND (b.finalization_entry_id IS NULL OR b.finalization_entry_id = 0)
        ", $one_twenty_days_from_now, $one_twenty_days_from_now));

        // 8. Refunds due (negative balance OR pending refund payment)
        $refunds_due_count = $wpdb->get_var("
            SELECT COUNT(*) FROM $booking_table 
            WHERE balance_due < 0
               OR (refund_payment_status = 'Pending' AND COALESCE(refund_payment_amount, 0) > 0)
        ");

        // 9. Web Bookings in last 24 hours (exclude reservations and waiting list)
        $last_24h_count = $wpdb->get_var("
            SELECT COUNT(*) FROM $booking_table 
            WHERE created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND booking_status IN ('Pending', 'Booked', 'Finalized')
            AND booking_entry_id IS NOT NULL 
            AND booking_entry_id != 0
        ");

        // 10. Get last web booking date (exclude reservations and waiting list)
        $last_web_booking = $wpdb->get_row("
            SELECT guest1_first_name, guest1_last_name, guest2_first_name, guest2_last_name, created_date 
            FROM $booking_table 
            WHERE booking_entry_id IS NOT NULL 
            AND booking_entry_id != 0 
            AND booking_status IN ('Pending', 'Booked', 'Finalized')
            ORDER BY created_date DESC 
            LIMIT 1
        ");
        
        echo '<div style="padding: 10px;">';
        
        // Always show all summary tiles - complete overview
        $tiles = array(
            array('🚨', __('Overbooked Tour Dates', 'bst-plugin'), count($overbooked_dates), '#dc3545', true),
            array('🚐', __('Oversold Limited Vehicles', 'bst-plugin'), $lv_oversold_count, '#c05621', true),
            array('💰', __('Refunds Due', 'bst-plugin'), $refunds_due_count, '#dc3232', true),
            array('🏦', 'Bank Transfer Pending', $bank_wire_count, '#dc3232', true),
            array('⏳', 'Processing Payments', $processing_count, '#ff9500', true),
            array('❌', 'Payment Failed', $payment_failed_count, '#dc3232', true),
            array('📋', 'Finalization Needed', $finalization_count, '#8f2ce6', true),
            array('⏰', 'Reservations Not Booked', $reservations_count, '#ff6900', true),
            array('📝', 'Waiting List', $waiting_list_count, '#0073aa', false),
            array('📈', 'Web Bookings in Last 24 hours', $last_24h_count, '#28a745', false)
        );
        
        foreach ($tiles as $tile) {
            list($icon, $title, $count, $color, $urgent) = $tile;
            $badge_color = ($count > 0) ? '#0073aa' : '#999'; // Same blue for all non-zero counts, gray for zero
            
            echo '<div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">';
            echo '<div style="display: flex; align-items: center;">';
            echo '<span style="margin-right: 8px; font-size: 16px;">' . $icon . '</span>';
            echo '<span>' . esc_html($title) . '</span>';
            echo '</div>';
            echo '<span style="background: ' . $badge_color . '; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;">' . $count . '</span>';
            echo '</div>';
        }
        
        // Add last web booking info in a more compact format
        if ($last_web_booking) {
            $booking_date = date('M j, Y', strtotime($last_web_booking->created_date));
            $guest_name = bst_format_guest_name($last_web_booking->guest1_first_name, $last_web_booking->guest1_last_name, $last_web_booking->guest2_first_name ?? '', $last_web_booking->guest2_last_name ?? '');
            
            echo '<div style="padding: 8px 0; border-top: 1px solid #f0f0f0; font-size: 12px; color: #555; text-align: left;">';
            echo '<strong>Last Web booking was on:</strong> ' . esc_html($booking_date);
            if ($guest_name) {
                echo ' (' . esc_html($guest_name) . ')';
            }
            echo '</div>';
        }
        
        echo '<div style="text-align: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">';
        echo '<a href="' . esc_url($plugin_dashboard_url) . '" class="button button-primary">';
        esc_html_e('View full dashboard →', 'bst-plugin');
        echo '</a>';
        echo '</div>';
        
        echo '<div style="text-align: center; margin-top: 5px;">';
        echo '<small style="color: #666;">Last updated: ' . current_time('H:i:s') . '</small>';
        echo '</div>';
        
        echo '</div>';
    }

    private function get_overbooked_tour_dates() {
        $args = array(
            'post_type' => 'tour-date',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'max_slots',
                    'value' => 0,
                    'compare' => '>'
                )
            )
        );
        
        $tour_dates = get_posts($args);
        $overbooked_dates = array();
        
        foreach ($tour_dates as $tour_date) {
            $max_slots = intval(get_post_meta($tour_date->ID, 'max_slots', true));
            $sold_slots = intval(get_post_meta($tour_date->ID, 'sold_slots', true));
            $offline_sold = intval(get_post_meta($tour_date->ID, 'offline_sold_slots', true));
            $reserved_slots = intval(get_post_meta($tour_date->ID, 'reserved_slots', true));
            
            $total_used = $sold_slots + $offline_sold + $reserved_slots;
            
            if ($total_used > $max_slots) {
                $overbooked_dates[] = array(
                    'id' => $tour_date->ID,
                    'title' => html_entity_decode(get_the_title($tour_date->ID), ENT_QUOTES, 'UTF-8'),
                    'max_slots' => $max_slots,
                    'total_used' => $total_used,
                    'sold_slots' => $sold_slots,
                    'offline_sold' => $offline_sold,
                    'reserved_slots' => $reserved_slots
                );
            }
        }
        
        return $overbooked_dates;
    }

    /**
     * AJAX handler for getting count of bookings that would be marked as complete
     */
    public function bst_get_bookings_count_for_completion() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user permissions
        if (!bst_user_can_manage_bookings()) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get filter parameters
        $filter_tour_id = isset($_POST['filter_tour_id']) ? intval($_POST['filter_tour_id']) : 0;
        $filter_tour_date_id = isset($_POST['filter_tour_date_id']) ? intval($_POST['filter_tour_date_id']) : 0;
        $filter_status = isset($_POST['filter_status']) ? trim($_POST['filter_status']) : '';

        global $wpdb;
        $booking_table = $wpdb->prefix . 'bst_tour_booking';

        // Build WHERE clause based on filters
        $where_conditions = array();
        $where_params = array();

        // Only count bookings with Booked or Finalized status
        $where_conditions[] = "booking_status IN ('Booked', 'Finalized')";

        // Apply filters if specified
        if ($filter_tour_id > 0) {
            // Get tour dates for this tour using CPT query
            $tour_dates = get_posts(array(
                'post_type' => 'tour-date',
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'tour',
                        'value' => $filter_tour_id,
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            
            if (!empty($tour_dates)) {
                $placeholders = implode(',', array_fill(0, count($tour_dates), '%d'));
                $where_conditions[] = "tour_date_id IN ($placeholders)";
                $where_params = array_merge($where_params, $tour_dates);
            } else {
                wp_send_json_success(array('count' => 0, 'tour_info' => '', 'date_info' => ''));
                return;
            }
        }

        if ($filter_tour_date_id > 0) {
            $where_conditions[] = "tour_date_id = %d";
            $where_params[] = $filter_tour_date_id;
        }

        // If a status filter is applied and it's not Booked, Finalized, or all_active, no bookings to count
        if (!empty($filter_status) && !in_array($filter_status, array('Booked', 'Finalized', 'all_active'), true)) {
            wp_send_json_success(array('count' => 0, 'tour_info' => '', 'date_info' => ''));
            return;
        }

        $where_clause = implode(' AND ', $where_conditions);
        
        // Count the bookings that would be updated
        $count_query = "SELECT COUNT(*) FROM $booking_table WHERE $where_clause";
        if (!empty($where_params)) {
            $booking_count = $wpdb->get_var($wpdb->prepare($count_query, $where_params));
        } else {
            $booking_count = $wpdb->get_var($count_query);
        }

        // Get tour and date information for display
        $tour_info = '';
        $date_info = '';
        
        if ($filter_tour_id > 0) {
            $tour_post = get_post($filter_tour_id);
            if ($tour_post) {
                $tour_info = $tour_post->post_title;
            }
        }
        
        if ($filter_tour_date_id > 0) {
            $date_post = get_post($filter_tour_date_id);
            if ($date_post) {
                $date_info = $date_post->post_title;
            }
        }

        wp_send_json_success(array(
            'count' => intval($booking_count),
            'tour_info' => $tour_info,
            'date_info' => $date_info
        ));
    }

    /**
     * AJAX handler for marking filtered bookings as complete
     */
    public function bst_mark_bookings_complete() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user permissions
        if (!bst_user_can_manage_bookings()) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get filter parameters
        $filter_tour_id = isset($_POST['filter_tour_id']) ? intval($_POST['filter_tour_id']) : 0;
        $filter_tour_date_id = isset($_POST['filter_tour_date_id']) ? intval($_POST['filter_tour_date_id']) : 0;
        $filter_status = isset($_POST['filter_status']) ? trim($_POST['filter_status']) : '';

        global $wpdb;
        $booking_table = $wpdb->prefix . 'bst_tour_booking';

        // Build WHERE clause based on filters
        $where_conditions = array();
        $where_params = array();

        // Only process bookings with Booked or Finalized status
        $where_conditions[] = "booking_status IN ('Booked', 'Finalized')";

        // Apply filters if specified
        if ($filter_tour_id > 0) {
            // Get tour dates for this tour using CPT query
            $tour_dates = get_posts(array(
                'post_type' => 'tour-date',
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'tour',
                        'value' => $filter_tour_id,
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            
            if (!empty($tour_dates)) {
                $placeholders = implode(',', array_fill(0, count($tour_dates), '%d'));
                $where_conditions[] = "tour_date_id IN ($placeholders)";
                $where_params = array_merge($where_params, $tour_dates);
            } else {
                // No tour dates found for this tour
                wp_send_json_success(array('message' => 'No bookings found for the selected tour.', 'updated_count' => 0));
                return;
            }
        }

        if ($filter_tour_date_id > 0) {
            $where_conditions[] = "tour_date_id = %d";
            $where_params[] = $filter_tour_date_id;
        }

        // If a status filter is applied and it's not Booked, Finalized, or all_active, no bookings to update
        if (!empty($filter_status) && !in_array($filter_status, array('Booked', 'Finalized', 'all_active'), true)) {
            wp_send_json_success(array('message' => 'No Booked or Finalized bookings found in the current selection.', 'updated_count' => 0));
            return;
        }

        $where_clause = implode(' AND ', $where_conditions);
        
        // Get the bookings that will be updated
        $query = "SELECT id, tour_date_id FROM $booking_table WHERE $where_clause";
        if (!empty($where_params)) {
            $bookings_to_update = $wpdb->get_results($wpdb->prepare($query, $where_params));
        } else {
            $bookings_to_update = $wpdb->get_results($query);
        }

        if (empty($bookings_to_update)) {
            wp_send_json_success(array('message' => 'No Booked or Finalized bookings found in the current selection.', 'updated_count' => 0));
            return;
        }

        // Update bookings to Completed status using the existing update function
        $updated_count = 0;
        $errors = array();
        
        foreach ($bookings_to_update as $booking) {
            $result = bst_update_tour_booking($booking->id, array('booking_status' => 'Completed'), 'bulk_complete');
            if ($result['success']) {
                $updated_count++;
            } else {
                $errors[] = "Failed to update booking ID {$booking->id}: " . $result['error'];
            }
        }

        if (!empty($errors)) {
            error_log('BST Bulk Complete Errors: ' . implode('; ', $errors));
        }

        if ($updated_count === 0) {
            wp_send_json_error('Failed to update any booking statuses.');
            return;
        }

        $message = $updated_count . ' booking' . ($updated_count !== 1 ? 's' : '') . ' marked as Completed.';
        wp_send_json_success(array('message' => $message, 'updated_count' => $updated_count));
    }

    /**
     * AJAX handler for getting email addresses from filtered bookings
     */
    public function bst_get_booking_emails() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user permissions
        if (!bst_user_can_manage_bookings()) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get filter parameters
        $filter_tour_id = isset($_POST['filter_tour_id']) ? intval($_POST['filter_tour_id']) : 0;
        $filter_tour_date_id = isset($_POST['filter_tour_date_id']) ? intval($_POST['filter_tour_date_id']) : 0;
        $filter_status = isset($_POST['filter_status']) ? trim($_POST['filter_status']) : '';

        global $wpdb;
        $booking_table = $wpdb->prefix . 'bst_tour_booking';

        // Build WHERE clause based on filters
        $where_conditions = array();
        $where_params = array();

        // Apply filters if specified
        if ($filter_tour_id > 0) {
            // Get tour dates for this tour using CPT query
            $tour_dates = get_posts(array(
                'post_type' => 'tour-date',
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'tour',
                        'value' => $filter_tour_id,
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));
            
            if (!empty($tour_dates)) {
                $placeholders = implode(',', array_fill(0, count($tour_dates), '%d'));
                $where_conditions[] = "tour_date_id IN ($placeholders)";
                $where_params = array_merge($where_params, $tour_dates);
            } else {
                // No tour dates found for this tour
                wp_send_json_success(array('emails' => array()));
                return;
            }
        }

        if ($filter_tour_date_id > 0) {
            $where_conditions[] = "tour_date_id = %d";
            $where_params[] = $filter_tour_date_id;
        }

        if (!empty($filter_status)) {
            if ($filter_status === 'all_active') {
                $where_conditions[] = 'booking_status NOT IN (%s, %s)';
                $where_params[] = 'Waiting List';
                $where_params[] = 'Cancelled';
            } else {
                $where_conditions[] = "booking_status = %s";
                $where_params[] = $filter_status;
            }
        }

        // Exclude certain statuses from email collection (all_active already drops WL/Cancelled)
        if ($filter_status !== 'all_active') {
            $excluded_statuses = array('Cancelled', 'Waiting List', 'Transfer');
            $placeholders = implode(',', array_fill(0, count($excluded_statuses), '%s'));
            $where_conditions[] = "booking_status NOT IN ($placeholders)";
            $where_params = array_merge($where_params, $excluded_statuses);
        } else {
            $where_conditions[] = 'booking_status NOT IN (%s)';
            $where_params[] = 'Transfer';
        }

        $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get the bookings with email addresses
        $query = "SELECT guest1_email, guest2_email, booking_status FROM $booking_table" . $where_clause;
        if (!empty($where_params)) {
            $bookings = $wpdb->get_results($wpdb->prepare($query, $where_params));
        } else {
            $bookings = $wpdb->get_results($query);
        }

        // Collect unique email addresses
        $emails = array();
        foreach ($bookings as $booking) {
            // Add guest1 email if it exists
            if (!empty($booking->guest1_email)) {
                $email = trim($booking->guest1_email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[$email] = true; // Use array key to ensure uniqueness
                }
            }
            
            // Add guest2 email if it exists
            if (!empty($booking->guest2_email)) {
                $email = trim($booking->guest2_email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[$email] = true; // Use array key to ensure uniqueness
                }
            }
        }

        // Convert to indexed array
        $email_list = array_keys($emails);
        $total_bookings = count($bookings);

        wp_send_json_success(array(
            'emails' => $email_list,
            'total_bookings' => $total_bookings,
            'excluded_note' => 'Cancelled, Waiting List, and Transfer bookings excluded'
        ));
    }
    
    /**
     * AJAX handler to send email from booking page
     */
    public function ajax_send_booking_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'bst_email_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $email_type = sanitize_text_field($_POST['email_type']);
        $template_id = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
        
        $email_manager = new BST_Email_Manager();
        
        switch ($email_type) {
            case 'reservation':
                $result = $email_manager->send_reservation_email($booking_id, $template_id);
                break;
            case 'finalization':
                $result = $email_manager->send_finalization_email($booking_id, $template_id);
                break;
            case 'invoice':
                $result = $email_manager->send_invoice_email($booking_id, $template_id);
                break;
            default:
                wp_send_json_error('Invalid email type');
                return;
        }
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Email sent successfully to ' . $result['recipient'],
                'recipient' => $result['recipient'],
                'subject' => $result['subject']
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Initialize email system components
     */
    private function init_email_system() {
        // Create database tables only on admin pages (not on every frontend request)
        if (is_admin()) {
            // Check if tables need to be created (only check once per day)
            $last_check = get_transient('bst_table_creation_check');
            if ($last_check === false) {
                create_tour_booking_tables();
                set_transient('bst_table_creation_check', true, DAY_IN_SECONDS);
            }
        }
        
        // Initialize email manager to register meta boxes
        new BST_Email_Manager();
        
        // Initialize email automation
        new BST_Email_Automation();
        
        // Initialize email log viewer
        new BST_Email_Log_Viewer();
        
        // Create default templates if they don't exist
        add_action('init', 'bst_create_default_email_templates', 10);
    }
    
    /**
     * AJAX Handler: Send finalization email
     */
    public function ajax_send_finalization_email() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_edit_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (empty($booking_id)) {
            wp_send_json_error('Invalid booking ID');
            return;
        }
        
        // Send finalization email
        $email_manager = new BST_Email_Manager();
        $result = $email_manager->send_finalization_email($booking_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Finalization email sent successfully',
                'recipient' => $result['recipient'] ?? '',
                'subject' => $result['subject'] ?? ''
            ));
        } else {
            wp_send_json_error($result['error'] ?? 'Failed to send email');
        }
    }
    
    /**
     * AJAX Handler: Cancel booking
     */
    public function ajax_cancel_booking() {
        global $wpdb;
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_edit_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (empty($booking_id)) {
            wp_send_json_error('Invalid booking ID');
            return;
        }
        
        // Get booking
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
            return;
        }
        
        // Check if already cancelled or completed
        if (in_array($booking->booking_status, ['Cancelled', 'Completed'])) {
            wp_send_json_error('Booking is already ' . $booking->booking_status);
            return;
        }
        
        // Update booking status and reset tour price
        $update_data = array(
            'booking_status' => 'Cancelled',
            'tour_price' => 0,
            'net_tour_price' => 0
        );
        
        // Zero out pending bank wire payment if exists
        if ($booking->balance_payment_method === 'Bank Wire' && 
            (empty($booking->balance_payment_amount) || $booking->balance_payment_amount == 0)) {
            $update_data['balance_due'] = 0;
        }
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'bst_tour_booking',
            $update_data,
            array('id' => $booking_id),
            array('%s', '%f', '%f', '%f'),
            array('%d')
        );
        
        if ($updated === false) {
            wp_send_json_error('Failed to update booking: ' . $wpdb->last_error);
            return;
        }
        
        // Recalculate financials
        require_once BST_PLUGIN_DIR . 'includes/booking-financials.php';
        bst_recalculate_booking_financials($booking_id);
        
        wp_send_json_success(array(
            'message' => 'Booking cancelled successfully'
        ));
    }
    
    /**
     * AJAX handler to get manual-only email templates
     */
    public function ajax_get_manual_email_templates() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Get all published email templates first
        $all_templates = get_posts(array(
            'post_type' => 'email-template',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $formatted_templates = array();
        foreach ($all_templates as $template) {
            $email_type = get_post_meta($template->ID, '_bst_email_type', true);
            $trigger = get_post_meta($template->ID, '_bst_email_trigger', true);
            
            // Include templates with trigger = 'on_demand' OR empty/null (which defaults to on_demand)
            if ($trigger === 'on_demand' || empty($trigger)) {
                $formatted_templates[] = array(
                    'id' => $template->ID,
                    'title' => $template->post_title,
                    'type' => $email_type ? ucfirst($email_type) : 'Ad Hoc'
                );
            }
        }
        
        wp_send_json_success($formatted_templates);
    }
    
    /**
     * AJAX handler to get email template content with merge tags resolved
     */
    public function ajax_get_email_template_content() {
        global $wpdb;
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $template_id = intval($_POST['template_id']);
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (empty($template_id)) {
            wp_send_json_error('Invalid template ID');
            return;
        }
        
        // Get template
        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'email-template') {
            wp_send_json_error('Template not found');
            return;
        }
        
        // Get template content (raw with tags)
        $raw_subject = get_post_meta($template_id, '_bst_email_subject', true);
        $raw_content = $template->post_content;
        
        // If booking_id provided, process merge fields for preview
        if (!empty($booking_id)) {
            // Get booking
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
                $booking_id
            ));
            
            if (!$booking) {
                wp_send_json_error('Booking not found');
                return;
            }
            
            // Process merge fields for preview
            $merge_fields_file = BST_PLUGIN_DIR . 'includes/email-system/class-email-merge-fields.php';
            if (!file_exists($merge_fields_file)) {
                error_log('BST: Merge fields class file not found at: ' . $merge_fields_file);
                wp_send_json_error('Merge fields processor not found');
                return;
            }
            
            require_once $merge_fields_file;
            
            if (!class_exists('BST_Email_Merge_Fields')) {
                error_log('BST: BST_Email_Merge_Fields class does not exist after requiring file');
                wp_send_json_error('Merge fields class not available');
                return;
            }
            
            $merge_fields = new BST_Email_Merge_Fields();
            $processed_subject = $merge_fields->process_merge_fields($raw_subject, $booking);
            $processed_content = $merge_fields->process_merge_fields($raw_content, $booking);
            
            wp_send_json_success(array(
                'subject' => $raw_subject,
                'content' => $raw_content,
                'processed_subject' => $processed_subject,
                'processed_content' => $processed_content
            ));
        } else {
            // No booking - just return raw template
            wp_send_json_success(array(
                'subject' => $raw_subject,
                'content' => $raw_content
            ));
        }
    }
    
    /**
     * AJAX handler to preview email content with merge tags resolved
     */
    public function ajax_preview_email_content() {
        global $wpdb;
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        $content = wp_unslash($_POST['content']);
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $cc = isset($_POST['cc']) ? sanitize_text_field(wp_unslash($_POST['cc'])) : '';
        
        if (empty($booking_id)) {
            wp_send_json_error('Invalid booking ID');
            return;
        }
        
        // Get booking
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bst_tour_booking WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
            return;
        }
        
        // Process merge fields
        require_once BST_PLUGIN_DIR . 'includes/email-system/class-email-merge-fields.php';
        $merge_fields = new BST_Email_Merge_Fields();
        $processed_content = $merge_fields->process_merge_fields($content, $booking);
        $processed_subject = $subject ? $merge_fields->process_merge_fields($subject, $booking) : '';
        $processed_cc = $cc ? $merge_fields->process_merge_fields($cc, $booking) : '';
        
        wp_send_json_success(array(
            'content' => $processed_content,
            'subject' => $processed_subject,
            'cc' => $processed_cc
        ));
    }
    
    /**
     * AJAX handler to get bookings for a tour date that need finalization
     */
    public function ajax_get_finalization_bookings() {
        global $wpdb;
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $tour_date_id = intval($_POST['tour_date_id']);
        
        if (empty($tour_date_id)) {
            wp_send_json_error('Invalid tour date ID');
            return;
        }
        
        // Get all bookings for this tour date that need finalization
        // (no finalization entry and confirmed status)
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                b.id,
                b.guest1_name,
                b.guest1_email,
                b.guest2_name,
                b.guest2_email
            FROM {$wpdb->prefix}bst_tour_booking b
            LEFT JOIN {$wpdb->prefix}bst_finalization_entry f ON b.id = f.booking_id
            WHERE b.tour_date_id = %d
            AND b.status = 'confirmed'
            AND f.id IS NULL
            AND b.guest1_email IS NOT NULL
            AND b.guest1_email != ''
            ORDER BY b.guest1_name",
            $tour_date_id
        ));
        
        if (empty($bookings)) {
            wp_send_json_error('No bookings found');
            return;
        }
        
        wp_send_json_success($bookings);
    }
    
    /**
     * AJAX handler to get available merge fields
     */
    public function ajax_get_merge_fields() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Get merge fields from the email merge fields class
        require_once BST_PLUGIN_DIR . 'includes/email-system/class-email-merge-fields.php';
        $merge_fields_class = new BST_Email_Merge_Fields();
        $available_fields = $merge_fields_class->get_available_fields();
        
        // Convert to format expected by JavaScript
        $formatted_fields = array();
        foreach ($available_fields as $category => $fields) {
            $formatted_fields[$category] = array();
            foreach ($fields as $name => $description) {
                $formatted_fields[$category][] = array(
                    'name' => $name,
                    'description' => $description
                );
            }
        }
        
        wp_send_json_success(array('fields' => $formatted_fields));
    }
    
    /**
     * AJAX handler to save a new email template from the compose modal
     */
    public function ajax_save_email_template() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bst_tour_bookings_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $template_name = sanitize_text_field(wp_unslash($_POST['template_name']));
        $subject = sanitize_text_field(wp_unslash($_POST['subject']));
        $content = wp_kses_post(wp_unslash($_POST['content']));
        $email_type = sanitize_text_field($_POST['email_type']);
        
        if (empty($template_name)) {
            wp_send_json_error('Template name is required');
            return;
        }
        
        if (empty($subject)) {
            wp_send_json_error('Email subject is required');
            return;
        }
        
        if (empty($content)) {
            wp_send_json_error('Email content is required');
            return;
        }
        
        // Create new email template post
        $post_id = wp_insert_post(array(
            'post_title' => $template_name,
            'post_content' => $content,
            'post_type' => 'email-template',
            'post_status' => 'publish'
        ));
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create template: ' . $post_id->get_error_message());
            return;
        }
        
        // Save meta data
        update_post_meta($post_id, '_bst_email_subject', $subject);
        update_post_meta($post_id, '_bst_email_type', strtolower($email_type));
        update_post_meta($post_id, '_bst_email_trigger', 'on_demand'); // Always manual
        update_post_meta($post_id, '_bst_email_to_type', 'booking_field');
        update_post_meta($post_id, '_bst_email_to_field', 'guest1_email');
        update_post_meta($post_id, '_bst_email_cc_type', 'none');
        update_post_meta($post_id, '_bst_email_bcc_type', 'none');
        
        wp_send_json_success(array(
            'template_id' => $post_id,
            'message' => 'Template saved successfully'
        ));
    }
}

