<?php 
class Banana_Crystal_Subscription_Plan {

    private $table_name = '';
    private $table_name_subscription = '';

    function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'banana_crystal_subscription_plans';
        $this->table_name_subscription = $wpdb->prefix . 'banana_crystal_subscriptions';

        //create table on activation
        register_activation_hook( __FILE__, 'init_table');
        $this->init_table();
        //add in menu
        add_action('admin_menu',  array( $this, 'add_plan_admin_menu'));
        //add shortcode to show subscription plans
        add_shortcode('banana-crystal-subscription-plans', array( $this, 'show_subscription_plans_shortcode'));
        //add shortcode to show subscription plans
        add_shortcode('banana-crystal-current-subscription', array( $this, 'show_current_subscription_shortcode'));
    }

    /**
     * Create subscriptions schema
     * 
     * @returns void
     */
    function init_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE `$this->table_name` (
        `subscription_plan_id` int(11) NOT NULL AUTO_INCREMENT,
        `subscription_plan_title` varchar(255) NOT NULL,
        `subscription_plan_description` text NOT NULL,
        `subscription_plan_occurrence` varchar(255) NOT NULL,
        `subscription_plan_amount` decimal(11,2) NOT NULL,
        `created_at` timestamp DEFAULT now(),
        `deleted_at` timestamp DEFAULT NULL,
        PRIMARY KEY(subscription_plan_id)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        ";
        if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $sql = "CREATE TABLE `$this->table_name_subscription` (
            `subscription_id` int(11) NOT NULL AUTO_INCREMENT,
            `subscription_plan_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `subscription_title` varchar(255) NOT NULL,
            `subscription_occurrence` varchar(255) NOT NULL,
            `subscription_amount` decimal(11,2) NOT NULL,
            `buyer_user_name` varchar(255) NOT NULL,
            `payload` text DEFAULT NULL,
            `subscription_status` varchar(255) DEFAULT 'PENDING',
            `created_at` timestamp DEFAULT now(),
            `expired_at` timestamp NOT NULL,
            `deleted_at` timestamp DEFAULT NULL,
            PRIMARY KEY(subscription_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
            ";


        if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name_subscription'") != $this->table_name_subscription) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    
    /**
     * Add main & sub menu for module
     * 
     * @return void
     */
    function add_plan_admin_menu() {
        add_menu_page(
            'Manage BananaCrystal Subscription Plans', 
            'Manage Subscription Plans', 
            'manage_options', 
            'bc-subscription-plans', 
            array($this,'subscriptionPlanPage'), 
            'dashicons-calendar', 
            27
        );
        add_submenu_page(
            'bc-subscription-plans',
            'Add BananaCrystal Subscription Plan',
            'Add Subscription Plan',
            'manage_options',
            'add-bc-subscription-plans',
            array($this,'addSubscriptionPage')
        );
        add_submenu_page(
            'bc-subscription-plans',
            'BananaCrystal Subscriptions',
            'Subscriptions/Transactions',
            'manage_options',
            'bc-subscriptions',
            array($this,'subscriptionsPage')
        );
    }

    /**
     * Handle Create/Update/Delete operation after form submission
     * 
     * @return void
     */
    function handle_actions() {
        global $wpdb;
        //add new subscription plan
        if (isset($_POST['add_btn'])) {
            $data = [
                'subscription_plan_title' => sanitize_text_field($_POST['plan_title']),
                'subscription_plan_description' => sanitize_text_field(htmlspecialchars($_POST['plan_description'])),
                'subscription_plan_amount' => number_format(sanitize_text_field($_POST['plan_recurring_amount']),2),
                'subscription_plan_occurrence' => sanitize_text_field($_POST['plan_occurrence'])
            ];
            $wpdb->insert($this->table_name, $data);
            
            wp_redirect( self_admin_url( "admin.php?page=bc-subscription-plans&added=1" ) );
            exit;
        }

        //update subscription plan
        if (isset($_POST['update_btn'])) {
            $id = (int)sanitize_text_field($_POST['plan_id']);
            $data = [
                'subscription_plan_title' => sanitize_text_field($_POST['plan_title']),
                'subscription_plan_description' => sanitize_text_field(htmlspecialchars($_POST['plan_description'])),
                'subscription_plan_amount' => number_format(sanitize_text_field($_POST['plan_recurring_amount']),2),
                'subscription_plan_occurrence' => sanitize_text_field($_POST['plan_occurrence'])
            ];
            $wpdb->update($this->table_name, $data, ['subscription_plan_id' => $id]);

            wp_redirect( self_admin_url( "admin.php?page=bc-subscription-plans&updated=1" ) );
            exit;
        }

        //delete subscription plan
        if (isset($_GET['del'])) {
            $del_id = (int)sanitize_text_field($_GET['del']);
            $wpdb->update($this->table_name, ['deleted_at' => date('Y-m-d H:i:s')], ['subscription_plan_id' => $del_id]);
 
            wp_redirect( self_admin_url( "admin.php?page=bc-subscription-plans&deleted=1" ) );
            exit;
        }
    }

    /**
     * Listing view of subscription plans
     * 
     * @return mixed/string
     */
    public function subscriptionPlanPage() {
        $this->handle_actions();
        include_once __DIR__.'/views/subscription_plans_view.php';
    }


    /**
     * Create view of subscription plan
     * 
     * @return mixed/string
     */
    public function addSubscriptionPage() {
        $this->handle_actions();

        include_once __DIR__.'/views/add_subscription_plan_view.php';
    }

    /**
     * Listing view of subscriptions/transactions
     * 
     * @return mixed/string
     */
    public function subscriptionsPage() {
        include_once __DIR__.'/views/subscriptions_view.php';
    }

    /**
     * Implement shortcode for subscription plans
     * 
     * @return mixed/string
     */
    public function show_subscription_plans_shortcode() {
        $banana_crystal_settings = WC()->payment_gateways->payment_gateways()['wo_banana_crystal']->settings;
        if ( $banana_crystal_settings['subscriptions_enabled'] == 'yes') {
            ob_start();
            @include(__DIR__.'/views/subscription_plans_front.php');
            return ob_get_clean();
        }

        return  '<strong>Subscriptions is not enabled.</strong>';
    }

    /**
     * Implement shortcode for current subscription plan
     * 
     * @return mixed/string
     */
    public function show_current_subscription_shortcode() {
        $banana_crystal_settings = WC()->payment_gateways->payment_gateways()['wo_banana_crystal']->settings;
        if ( $banana_crystal_settings['subscriptions_enabled'] == 'yes') {
            ob_start();
            @include(__DIR__.'/views/current_subscription_front.php');
            return ob_get_clean();
        }

        return  '<strong>Subscriptions is not enabled.</strong>';
    }
}
