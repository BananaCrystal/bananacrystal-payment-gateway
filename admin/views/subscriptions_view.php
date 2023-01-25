<div class="wrap">
            <h1 class="wp-heading-inline">Subscriptions Listing</h1>
            <hr class="wp-header-end"/>
 
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th width="15%">Created Date</th>
                        <th width="20%">User name</th>
                        <th width="5%">Plan ID</th>
                        <th width="20%">Title</th>
                        <th width="10%">Amount</th>
                        <th width="10%">Occurrence</th>
                        <th width="10%">Expires At</th>
                        <th width="10%">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'banana_crystal_subscriptions';
                $result = $wpdb->get_results("SELECT bcs.*, u.display_name, u.user_email FROM $table_name bcs INNER JOIN ".$wpdb->prefix."users u ON u.ID=bcs.user_id WHERE bcs.deleted_at IS NULL ORDER BY bcs.created_at DESC");
                if (count($result) > 0) {
                    foreach ($result as $plan) {
                        echo "
                        <tr>
                            <td width='15%'>".esc_html(banana_crystal_format_date($plan->created_at))."</td>
                            <td width='20%'>".esc_html($plan->display_name)." (".esc_html($plan->user_email).")</td>
                            <td width='5%'>$plan->subscription_plan_id</td>
                            <td width='20%'>".esc_html($plan->subscription_title)."</td>
                            <td width='10%'>".number_format($plan->subscription_amount, 2)."</td>
                            <td width='10%'>".esc_html(banana_crystal_format_occurrence($plan->subscription_occurrence))."</td>
                            <td width='10%'>".esc_html(banana_crystal_format_date($plan->expired_at))."</td>
                            <td width='10%'>".esc_html($plan->subscription_status)."</td>
                        </tr>
                        ";
                    }
                } else {
                    echo "
                        <tr>
                            <td colspan='5'>Currently you do not have any active subscriptions.</td>
                        </tr>
                    ";
                }
                ?>
                </tbody>
            </table>
</div>
