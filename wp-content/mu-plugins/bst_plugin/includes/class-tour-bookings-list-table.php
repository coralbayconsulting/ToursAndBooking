<?php
/**
 * Tour Bookings List Table Class
 * 
 * Extends WP_List_Table to provide a WordPress-standard admin table
 * for tour bookings with pagination, sorting, and filtering.
 */

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class BST_Tour_Bookings_List_Table extends WP_List_Table {
    
    private $selected_tour = 0;
    private $selected_date = 0;
    private $selected_status = '';
    
    public function __construct() {
        parent::__construct([
            'singular' => 'booking',
            'plural'   => 'bookings',
            'ajax'     => false
        ]);
        
        // Get filter parameters
        $this->selected_tour = isset($_GET['filter_tour_id']) ? intval($_GET['filter_tour_id']) : 0;
        $this->selected_date = isset($_GET['filter_tour_date_id']) ? intval($_GET['filter_tour_date_id']) : 0;
        $this->selected_status = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
    }
    
    /**
     * Override table classes to match CSS styling
     */
    protected function get_table_classes() {
        return array('widefat', 'fixed', 'striped', $this->_args['plural']);
    }
    
    /**
     * Define the columns for the table
     */
    public function get_columns() {
        return [
            'id' => 'ID',
            'booking_id' => 'Book',
            'finalization_id' => 'Final',
            'created_date' => 'Date',
            'name' => 'Name',
            'tour' => 'Tour',
            'tour_price' => 'Price',
            'coupon_amount' => 'Coup',
            'net_tour_price' => 'Net Price',
            'additional_charge' => 'Add\'l',
            'total_paid' => 'Paid',
            'balance_due' => 'Balance',
            'tour_currency' => 'CUR',
            'booking_status' => 'Status',
            'actions' => ''
        ];
    }
    
    /**
     * Override single_row_columns to add correct CSS classes
     */
    protected function single_row_columns($item) {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();
        
        foreach ($columns as $column_name => $column_display_name) {
            $classes = "$column_name column-$column_name";
            if ($primary === $column_name) {
                $classes .= ' has-row-actions column-primary';
            }
            
            // Map column names to CSS class names
            $css_class_map = [
                'id' => 'id-column',
                'booking_id' => 'booking-id-column',
                'finalization_id' => 'finalization-id-column',
                'created_date' => 'booking-date-column',
                'name' => 'name-column',
                'tour' => 'tour-column',
                'auto' => 'auto-column',
                'tour_price' => 'tour-price-column',
                'coupon_amount' => 'coupon-amount-column',
                'net_tour_price' => 'price-column',
                'additional_charge' => 'additional-charge-column',
                'total_paid' => 'total-paid-column',
                'balance_due' => 'balance-due-column',
                'tour_currency' => 'currency-column',
                'booking_status' => 'status-column',
                'actions' => 'actions-column'
            ];
            
            if (isset($css_class_map[$column_name])) {
                $classes .= ' ' . $css_class_map[$column_name];
            }
            
            if (in_array($column_name, $hidden)) {
                $classes .= ' hidden';
            }
            
            // Get column content
            if (method_exists($this, '_column_' . $column_name)) {
                $content = call_user_func(array($this, '_column_' . $column_name), $item, $classes, '', $column_display_name);
            } elseif (method_exists($this, 'column_' . $column_name)) {
                $content = call_user_func(array($this, 'column_' . $column_name), $item);
            } else {
                $content = $this->column_default($item, $column_name);
            }
            
            echo "<td class='$classes'>$content</td>";
        }
    }
    
    /**
     * Override print_column_headers to add correct CSS classes to headers
     */
    public function print_column_headers($with_id = true) {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();
        
        // Map column names to CSS class names
        $css_class_map = [
            'id' => 'id-column',
            'booking_id' => 'booking-id-column',
            'finalization_id' => 'finalization-id-column',
            'created_date' => 'booking-date-column',
            'name' => 'name-column',
            'tour' => 'tour-column',
            'tour_price' => 'tour-price-column',
            'coupon_amount' => 'coupon-amount-column',
            'net_tour_price' => 'price-column',
            'additional_charge' => 'additional-charge-column',
            'total_paid' => 'total-paid-column',
            'balance_due' => 'balance-due-column',
            'tour_currency' => 'currency-column',
            'booking_status' => 'status-column',
            'actions' => 'actions-column'
        ];

        $current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $current_url = remove_query_arg('paged', $current_url);

        if (isset($_GET['orderby'])) {
            $current_orderby = $_GET['orderby'];
        } else {
            $current_orderby = '';
        }

        if (isset($_GET['order']) && 'desc' === $_GET['order']) {
            $current_order = 'desc';
        } else {
            $current_order = 'asc';
        }

        if (!empty($columns['cb'])) {
            static $cb_counter = 1;
            $columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __('Select All') . '</label>'
                . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
            $cb_counter++;
        }

        foreach ($columns as $column_key => $column_display_name) {
            $class = array('manage-column', "column-$column_key");

            if (in_array($column_key, $hidden)) {
                $class[] = 'hidden';
            }

            if ('cb' === $column_key) {
                $class[] = 'check-column';
            } elseif (in_array($column_key, array('posts', 'comments', 'links'))) {
                $class[] = 'num';
            }

            if ($column_key === $primary) {
                $class[] = 'column-primary';
            }
            
            // Add CSS class mapping
            if (isset($css_class_map[$column_key])) {
                $class[] = $css_class_map[$column_key];
            }

            if (isset($sortable[$column_key])) {
                list($orderby, $desc_first) = $sortable[$column_key];

                if ($current_orderby === $orderby) {
                    $order   = 'asc' === $current_order ? 'desc' : 'asc';
                    $class[] = 'sorted';
                    $class[] = $current_order;
                } else {
                    $order   = $desc_first ? 'desc' : 'asc';
                    $class[] = 'sortable';
                    $class[] = $desc_first ? 'asc' : 'desc';
                }

                $column_display_name = '<a href="' . esc_url(add_query_arg(compact('orderby', 'order'), $current_url)) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
            }

            $tag   = ('cb' === $column_key) ? 'td' : 'th';
            $scope = ('th' === $tag) ? 'scope="col"' : '';
            $id    = $with_id ? "id='$column_key'" : '';

            if (!empty($class)) {
                $class = "class='" . join(' ', $class) . "'";
            }

            echo "<$tag $scope $id $class>$column_display_name</$tag>";
        }
    }
    
    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return [
            'id' => ['id', true],
            'created_date' => ['created_date', false],
            'name' => ['name', false],
            'tour' => ['tour', false]
        ];
    }
    
    /**
     * Prepare the data for display
     */
    public function prepare_items() {
        global $wpdb;
        
        // Get screen options
        $per_page = $this->get_items_per_page('bst_tour_bookings_per_page', 20);
        $current_page = $this->get_pagenum();
        
        // Get sorting parameters
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'id';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';
        
        // Build the query
        $table_name = $wpdb->prefix . 'bst_tour_booking';
        $where_conditions = ['1=1'];
        $where_values = [];
        
        // Apply filters
        if ($this->selected_tour > 0) {
            $where_conditions[] = 'tour_id = %d';
            $where_values[] = $this->selected_tour;
        }
        
        if ($this->selected_date > 0) {
            $where_conditions[] = 'tour_date_id LIKE %s';
            $where_values[] = $this->selected_date . '%';
        }
        
        if (!empty($this->selected_status)) {
            $where_conditions[] = 'booking_status = %s';
            $where_values[] = $this->selected_status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build ORDER BY clause
        $valid_orderby = ['id', 'created_date', 'name', 'tour'];
        if (!in_array($orderby, $valid_orderby)) {
            $orderby = 'id';
        }
        
        $order_clause = '';
        switch ($orderby) {
            case 'id':
                $order_clause = 'id';
                break;
            case 'created_date':
                $order_clause = 'created_date';
                break;
            case 'name':
                $order_clause = 'guest1_first_name, guest1_last_name';
                break;
            case 'tour':
                $order_clause = 'tour_id';
                break;
        }
        
        $order_clause .= ' ' . ($order === 'desc' ? 'DESC' : 'ASC');
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }
        
        // Get the data
        $offset = ($current_page - 1) * $per_page;
        $data_query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$order_clause} LIMIT %d OFFSET %d";
        
        $query_params = array_merge($where_values, [$per_page, $offset]);
        $this->items = $wpdb->get_results($wpdb->prepare($data_query, $query_params));
        

        
        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        // Set column headers
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];
    }
    
    /**
     * Display when no items are found
     */
    public function no_items() {
        echo 'No tour bookings found.';
    }
    
    /**
     * Default column display
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return esc_html($item->id);
            case 'tour_price':
                return esc_html(number_format($item->tour_price, 0));
            case 'coupon_amount':
                return ($item->coupon_amount > 0) ? esc_html(number_format($item->coupon_amount, 2)) : '';
            case 'net_tour_price':
                return esc_html(number_format($item->net_tour_price, 0));
            case 'additional_charge':
                return (($item->additional_charge ?? 0) > 0) ? esc_html(number_format($item->additional_charge, 0)) : '';
            case 'total_paid':
                return esc_html(number_format($item->total_paid, 2));
            case 'balance_due':
                return esc_html(number_format($item->balance_due, 2));
            case 'tour_currency':
                return esc_html($item->tour_currency ?? 'EUR');
            case 'booking_status':
                return esc_html($item->booking_status ?? '');
            default:
                return '';
        }
    }
      /**
     * Booking ID column with link
     */
    public function column_booking_id($item) {
        return sprintf(
            '<a href="%s" target="_blank">%s</a>',
            esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=9&lid=' . $item->booking_entry_id)),
            esc_html($item->booking_entry_id)
        );
    }

    /**
     * Finalization ID column with link
     */
    public function column_finalization_id($item) {
        if ($item->booking_status === 'Booked' && empty($item->finalization_entry_id)) {
            return sprintf(
                '<a href="%s" target="_blank">url</a>',
                esc_url(bst_get_finalization_url($item->id))
            );
        } elseif (!empty($item->finalization_entry_id)) {
            return sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=10&lid=' . $item->finalization_entry_id)),
                esc_html($item->finalization_entry_id)
            );
        }
        return '';
    }

    /**
     * Created date column with formatting
     */
    public function column_created_date($item) {
        $created_date = $item->created_date;
        
        if (strpos($created_date, ' 00:00:00') !== false) {
            return esc_html(date('Y-m-d', strtotime($created_date)));
        } elseif (strpos($created_date, ' ') !== false) {
            return esc_html(date('Y-m-d H:i', strtotime($created_date)));
        } else {
            return esc_html($created_date);
        }
    }
    
    /**
     * Name column with guest logic
     */
    public function column_name($item) {
        $guest1_first = esc_html($item->guest1_first_name);
        $guest1_last = esc_html($item->guest1_last_name);
        $guest2_first = isset($item->guest2_first_name) ? esc_html($item->guest2_first_name) : '';
        $guest2_last = isset($item->guest2_last_name) ? esc_html($item->guest2_last_name) : '';
        
        if (empty($guest2_first)) {
            return $guest1_first . ' ' . $guest1_last;
        } else {
            if (empty($guest2_last) || $guest1_last === $guest2_last) {
                return $guest1_first . ' & ' . $guest2_first . ' ' . $guest1_last;
            } else {
                return $guest1_first . ' ' . $guest1_last . ' & ' . $guest2_first . ' ' . $guest2_last;
            }
        }
    }
    
    /**
     * Tour column with date and package info
     */
    public function column_tour($item) {
        $tour_label = function_exists('bst_live_tour_title') ? bst_live_tour_title($item->tour_id ?? 0) : '';
        $tour_date_id = $item->tour_date_id;
        $tour_date_text = function_exists('bst_live_tour_date_text') ? bst_live_tour_date_text($item->tour_date_id ?? 0) : '';
        $paren = '';
        
        $date_label = $tour_date_text !== '' ? $tour_date_text : $tour_date_id;
        if ($date_label) {
            $paren = $date_label;
        }
        
        // Build the tour display text (no auto suffix)
        $package_label = function_exists('bst_live_package_name') ? bst_live_package_name($item->tour_package_id ?? 0) : '';
        $tour_display = esc_html($tour_label . ($paren ? ' (' . $paren . ')' : '') . ' - ' . $package_label);
        return $tour_display;
    }

    /**
     * Actions column
     */
    public function column_actions($item) {
        return sprintf(
            '<a href="%s" class="button view-booking" title="View Booking">View</a>',
            esc_url(admin_url('admin.php?page=view_booking&id=' . $item->id))
        );
    }
    
    /**
     * Display the filter controls above the table
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            $this->display_filters();
        }
    }
    
    /**
     * Display the filter form
     */
    private function display_filters() {
        global $wpdb;
        
        // Build filter data from all bookings (not just current page)
        $all_bookings = $wpdb->get_results("SELECT tour_id, tour_date_id, booking_status FROM {$wpdb->prefix}bst_tour_booking");
        
        $tours_with_bookings = array();
        $tour_dates_data = array();
        
        foreach ($all_bookings as $booking) {
            $tour_id = intval($booking->tour_id);
            $tour_date_id = !empty($booking->tour_date_id) ? intval(explode('|', $booking->tour_date_id)[0]) : 0;
            
            if ($tour_id > 0) {
                if (!isset($tours_with_bookings[$tour_id])) {
                    $tours_with_bookings[$tour_id] = get_the_title($tour_id);
                }
                
                if ($tour_date_id > 0) {
                    if (!isset($tour_dates_data[$tour_id])) {
                        $tour_dates_data[$tour_id] = array();
                    }
                    
                    if (!isset($tour_dates_data[$tour_id][$tour_date_id])) {
                        $tour_date_post = get_post($tour_date_id);
                        if ($tour_date_post && $tour_date_post->post_type === 'tour-date') {
                            $start_date = get_post_meta($tour_date_id, 'start_date', true);
                            $end_date = get_post_meta($tour_date_id, 'end_date', true);
                            if ($start_date && $end_date) {
                                $tour_date_text = (date('M', strtotime($start_date)) == date('M', strtotime($end_date)))
                                    ? date('j', strtotime($start_date)) . '-' . date('j M Y', strtotime($end_date))
                                    : date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                            } elseif ($start_date) {
                                $tour_date_text = date('j M Y', strtotime($start_date));
                            } else {
                                $tour_date_text = $tour_date_post->post_title;
                            }
                            $tour_dates_data[$tour_id][$tour_date_id] = array(
                                'text' => $tour_date_text,
                                'sort_date' => $start_date ? $start_date : '9999-12-31'
                            );
                        }
                    }
                }
            }
        }
        
        // Sort tours and dates
        asort($tours_with_bookings);
        foreach ($tour_dates_data as $tour_id => $dates) {
            uasort($tour_dates_data[$tour_id], function($a, $b) {
                return strcmp($a['sort_date'], $b['sort_date']);
            });
        }
        
        // Get status options from current filtered bookings
        $status_query = "SELECT DISTINCT booking_status FROM {$wpdb->prefix}bst_tour_booking WHERE booking_status IS NOT NULL AND booking_status != ''";
        $status_params = array();
        
        if ($this->selected_tour > 0) {
            $status_query .= " AND tour_id = %d";
            $status_params[] = $this->selected_tour;
        }
        
        if ($this->selected_date > 0) {
            $status_query .= " AND tour_date_id LIKE %s";
            $status_params[] = $this->selected_date . '|%';
        }
        
        $status_query .= " ORDER BY booking_status";
        
        if (!empty($status_params)) {
            $statuses_with_bookings = $wpdb->get_col($wpdb->prepare($status_query, $status_params));
        } else {
            $statuses_with_bookings = $wpdb->get_col($status_query);
        }
        
        // Only show status filter if there are multiple statuses
        $show_status_filter = count($statuses_with_bookings) > 1;
        
        ?>
        <div class="alignleft actions">
            <form method="get" id="bst-tour-filter-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                
                <label for="filter_tour_id" class="screen-reader-text">Filter by Tour</label>
                <select name="filter_tour_id" id="filter_tour_id">
                    <option value="0">All Tours</option>
                    <?php foreach ($tours_with_bookings as $tour_id => $tour_name): ?>
                        <option value="<?php echo esc_attr($tour_id); ?>" <?php selected($this->selected_tour, $tour_id); ?>>
                            <?php echo esc_html($tour_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <span id="date-filter-container" style="<?php echo $this->selected_tour == 0 ? 'display:none;' : ''; ?>">
                    <label for="filter_tour_date_id" class="screen-reader-text">Filter by Date</label>
                    <select name="filter_tour_date_id" id="filter_tour_date_id">
                        <option value="0">All Dates</option>
                        <?php if ($this->selected_tour > 0 && isset($tour_dates_data[$this->selected_tour])): ?>
                            <?php foreach ($tour_dates_data[$this->selected_tour] as $date_id => $date_info): ?>
                                <option value="<?php echo esc_attr($date_id); ?>" <?php selected($this->selected_date, $date_id); ?>>
                                    <?php echo esc_html($date_info['text']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </span>
                
                <?php if ($show_status_filter): ?>
                <label for="filter_status" class="screen-reader-text">Filter by Status</label>
                <select name="filter_status" id="filter_status">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses_with_bookings as $status): ?>
                        <option value="<?php echo esc_attr($status); ?>" <?php selected($this->selected_status, $status); ?>>
                            <?php echo esc_html($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                
                <?php submit_button('Filter', 'secondary', 'filter_action', false); ?>
            </form>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tourDatesData = <?php echo json_encode($tour_dates_data); ?>;
            
            const tourSelect = document.getElementById('filter_tour_id');
            const dateSelect = document.getElementById('filter_tour_date_id');
            const statusSelect = document.getElementById('filter_status');
            const filterForm = document.getElementById('bst-tour-filter-form');
            const dateContainer = document.getElementById('date-filter-container');
            
            if (!tourSelect || !filterForm) {
                return;
            }
            
            function updateDateOptions() {
                const tourId = tourSelect.value;
                
                if (tourId == '0') {
                    if (dateContainer) dateContainer.style.display = 'none';
                    if (dateSelect) dateSelect.value = '0';
                } else {
                    if (dateContainer) dateContainer.style.display = 'inline';
                    if (dateSelect) {
                        dateSelect.innerHTML = '<option value="0">All Dates</option>';
                        
                        if (tourDatesData[tourId]) {
                            const entries = Object.entries(tourDatesData[tourId]);
                            entries.sort(function(a, b) {
                                const aDate = (a[1] && a[1].sort_date) ? a[1].sort_date : '';
                                const bDate = (b[1] && b[1].sort_date) ? b[1].sort_date : '';
                                return aDate.localeCompare(bDate);
                            });
                            
                            entries.forEach(function(entry) {
                                const dateId = entry[0];
                                const dateInfo = entry[1];
                                const option = document.createElement('option');
                                option.value = dateId;
                                option.textContent = dateInfo.text;
                                dateSelect.appendChild(option);
                            });
                        }
                    }
                }
            }
            
            // Tour filter change - just update date options, don't auto-submit
            tourSelect.addEventListener('change', function() {
                updateDateOptions();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get statuses organized by filter type for JavaScript
     */
    private function get_statuses_by_filter() {
        global $wpdb;
        
        $result = [
            'all' => [],
            'tours' => [],
            'dates' => []
        ];
        
        // Get all statuses
        $all_statuses = $wpdb->get_col("SELECT DISTINCT booking_status FROM {$wpdb->prefix}bst_tour_booking WHERE booking_status IS NOT NULL AND booking_status != '' ORDER BY booking_status");
        $result['all'] = $all_statuses;
        
        // Get statuses by tour
        $tour_statuses = $wpdb->get_results("SELECT DISTINCT tour_id, booking_status FROM {$wpdb->prefix}bst_tour_booking WHERE booking_status IS NOT NULL AND booking_status != '' ORDER BY tour_id, booking_status");
        foreach ($tour_statuses as $row) {
            if (!isset($result['tours'][$row->tour_id])) {
                $result['tours'][$row->tour_id] = [];
            }
            $result['tours'][$row->tour_id][] = $row->booking_status;
        }
        
        // Get statuses by date (extract date ID from tour_date_id field)
        $date_statuses = $wpdb->get_results("SELECT DISTINCT tour_date_id, booking_status FROM {$wpdb->prefix}bst_tour_booking WHERE booking_status IS NOT NULL AND booking_status != '' AND tour_date_id IS NOT NULL ORDER BY tour_date_id, booking_status");
        foreach ($date_statuses as $row) {
            if (!empty($row->tour_date_id)) {
                $date_id = intval(explode('|', $row->tour_date_id)[0]);
                if (!isset($result['dates'][$date_id])) {
                    $result['dates'][$date_id] = [];
                }
                $result['dates'][$date_id][] = $row->booking_status;
            }
        }
        
        return $result;
    }
}
