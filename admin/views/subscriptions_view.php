<div class="wrap">
            <h1 class="wp-heading-inline">Subscriptions Listing</h1>
            <hr class="wp-header-end"/>
 
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
                $table_name = $wpdb->prefix . 'banana_crystal_subscriptions WHERE deleted_at IS NULL';
                $result = $wpdb->get_results("SELECT * FROM $table_name");
                if (count($result) > 0) {
                    foreach ($result as $plan) {
                        echo "
                        <tr>
                            <td width='20%'>$plan->subscription_plan_id</td>
                            <td width='20%'>$plan->subscription_plan_title</td>
                            <td width='20%'>$plan->subscription_plan_amount</td>
                            <td width='20%'>$plan->subscription_plan_occurrence</td>
                            <td width='20%'><a class='button' href='admin.php?page=update-bc-subscription-plan&upt=$plan->subscription_plan_id'>Edit</a> <a  class='button' href='admin.php?page=bc-subscription-plans&del=$plan->subscription_plan_id' onclick='return confirm(\"Are you sure?\");'>Delete</a></td>
                        </tr>
                        ";
                    }
                } else {
                    echo "
                        <tr>
                            <td colspan='5'>No subscriptions found!</td>
                        </tr>
                    ";
                }
                ?>
                </tbody>
            </table>
</div>