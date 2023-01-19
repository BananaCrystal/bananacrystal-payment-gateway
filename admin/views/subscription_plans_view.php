<div class="wrap">
            <h1 class="wp-heading-inline">Manage Subscription Plans</h1>
            <a href="<?php echo self_admin_url( "admin.php?page=add-bc-subscription-plans" ); ?>" class="page-title-action">Add Subscription Plan</a>
            <hr class="wp-header-end"/>
            <p>Note: Use <code>[banana-crystal-subscription-plans]</code> to show the plans on the site.</p>
            <?php 
                //show success messages
                if (isset($_GET['added']) || isset($_GET['updated']) || isset($_GET['deleted'])) { 
            ?>
            <div id="message" class="updated notice notice-success is-dismissible">
                <p><?php if (isset($_GET['added'])) {
                    echo 'Subscription plan added successfully!';
                } else if (isset($_GET['updated'])) {
                    echo 'Subscription plan updated successfully!';
                } else {
                    echo 'Subscription plan deleted successfully!';
                } ?></p>
                <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
            </div>
            <?php } ?>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th width="20%">Plan ID</th>
                        <th width="20%">Title</th>
                        <th width="20%">Amount</th>
                        <th width="20%">Occurrence</th>
                        <th width="20%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'banana_crystal_subscription_plans WHERE deleted_at IS NULL';
                $result = $wpdb->get_results("SELECT * FROM $table_name");
                if (count($result) > 0) {
                    foreach ($result as $plan) {
                        echo "
                        <tr>
                            <td width='20%'>$plan->subscription_plan_id</td>
                            <td width='20%'>".esc_html($plan->subscription_plan_title)."</td>
                            <td width='20%'>".number_format($plan->subscription_plan_amount, 2)."</td>
                            <td width='20%'>".esc_html(banana_crystal_format_occurrence($plan->subscription_plan_occurrence))."</td>
                            <td width='20%'><a class='button' href='admin.php?page=add-bc-subscription-plans&upt=$plan->subscription_plan_id'>Edit</a> <a  class='button' href='admin.php?page=bc-subscription-plans&del=$plan->subscription_plan_id' onclick='return confirm(\"Are you sure?\");'>Delete</a></td>
                        </tr>
                        ";
                    }
                } else {
                    echo "
                        <tr>
                            <td colspan='5'>No subscription plans found!</td>
                        </tr>
                    ";
                }
                ?>
                </tbody>
            </table>
</div>