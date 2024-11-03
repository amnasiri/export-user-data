<?php
/*
Plugin Name: User Click Tracker with Export
Description: Tracks button clicks and exports user data including username, name, phone number, email, and order history for users who clicked the button.
Version: 1.0.0
Author: Amir MOhammad Nasiri
Author url : amnasiri.com
*/

add_action('wp_enqueue_scripts', 'uct_enqueue_scripts');

function uct_enqueue_scripts() {
    wp_enqueue_script('uct-click-tracker', plugin_dir_url(__FILE__) . 'click-tracker.js', array('jquery'), '1.0', true);

    wp_localize_script('uct-click-tracker', 'uct_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}

add_action('wp_ajax_uct_record_click', 'uct_record_click');
add_action('wp_ajax_nopriv_uct_record_click', 'uct_record_click');

function uct_record_click() {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_clicks';
        $wpdb->replace($table_name, array(
            'user_id' => $user_id,
            'username' => $user_info->user_login,
            'email' => $user_info->user_email,
            'clicked_at' => current_time('mysql')
        ));

        wp_send_json_success('کلیک موفقیت آمیز');
    } else {
        wp_send_json_error('کاربر وارد نشده است');
    }
}

register_activation_hook(__FILE__, 'uct_create_table');

function uct_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_clicks';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        username varchar(60) NOT NULL,
        email varchar(100) NOT NULL,
        clicked_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_action('admin_menu', 'uct_add_admin_menu');

function uct_add_admin_menu() {
    add_menu_page('لیست درخواست خروج لاکر', 'لیست درخواست خروج لاکر', 'manage_options', 'user-click-export', 'uct_export_page', 'dashicons-download', 20);
}

function uct_export_page() {
    ?>
    <div class="wrap">
        <h1>لیست خروجی از لاکر</h1>
        <form method="post" action="">
            <input type="hidden" name="uct_export_csv" value="1">
            <?php submit_button('لیست خروجی از لاکر'); ?>
        </form>
    </div>
    <?php

    if (isset($_POST['uct_export_csv'])) {
        uct_export_user_data();
    }
}

function uct_export_user_data() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'user_clicks';
    $clicked_users = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    if (empty($clicked_users)) {
        echo '<div class="notice notice-error"><p>No users have clicked the button.</p></div>';
        return;
    }

    ob_clean();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="clicked_user_data.csv"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array('Username', 'Name', 'Last Name', 'Mobile Phone', 'Email', 'Subject' , 'Other Subject' , 'Order History', 'Order ID History', 'Date of Click'));

    foreach ($clicked_users as $clicked_user) {
        $user_id = $clicked_user['user_id'];
        $user_info = get_userdata($user_id);
        $username = $clicked_user['username'];
        $first_name = $user_info->first_name;
        $last_name = $user_info->last_name;
        $email = $clicked_user['email'];
        $subject_list = isset($user_meta['subject_list']) ? $user_meta['subject_list'] : '';
        $other = isset($user_meta['other']) ? $user_meta['other'] : '';



        $mobile_phone = get_user_meta($user_id, 'mobile_number', true);
		$subject_list = get_user_meta($user_id, 'subject_list', true);
		$other = get_user_meta($user_id, 'other', true);

        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
        ));

        $order_history = [];
        $order_id_history = [];
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $order_total = $order->get_total();
            $order_date = $order->get_date_created()->date('Y-m-d');
            $order_history[] = "Order Total: $order_total, Date: $order_date";
            $order_id_history[] = $order_id;
        }

        $order_history_str = implode(' | ', $order_history);
        $order_id_history_str = implode(', ', $order_id_history);

        $date_of_click = $clicked_user['clicked_at'];

        fputcsv($output, array($username, $first_name, $last_name, $mobile_phone, $email, $order_history_str, $order_id_history_str, $date_of_click));
    }

    fclose($output);
    exit;
}
?>