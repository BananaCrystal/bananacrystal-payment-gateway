<?php 

add_action( 'banana_crystal_subscription_new_cron', 'bc_renew_subscription' );

function bc_renew_subscription() {
    global $wpdb;
    $banana_crystal_settings = WC()->payment_gateways->payment_gateways()['wo_banana_crystal']->settings;
    $subscription_key = $banana_crystal_settings['subscription_key'];
    $store_username = $banana_crystal_settings['store_username'];
    //get all subscriptions that are expiring today
    $table_name = $wpdb->prefix . 'banana_crystal_subscriptions';
    $subscriptions = $wpdb->get_results("SELECT * FROM ".$table_name." WHERE subscription_status='ACTIVE' AND deleted_at IS NULL AND DATE(expired_at)='".date('Y-m-d')."' ORDER BY created_at DESC");
    if (count($subscriptions) > 0) {
        foreach ($subscriptions as $subscription) { 
            $data = [
                'sender' => $subscription->buyer_user_name,
                'amount' => $subscription->subscription_amount,
                'note' => $subscription->subscription_title,
                'subscriber_user_id' => $subscription->user_id,
                'subscription_id' => $subscription->subscription_plan_id,
                'subscriber_username' => $subscription->buyer_user_name,
                'store_username' => $store_username
            ];
            $result = banana_crystal_charge_payment($data, $subscription_key);
            if ( is_wp_error( $result ) ) { 
                $wpdb->update($table_name, ['subscription_status' => 'EXPIRED'], ['subscription_id' => $subscription->subscription_id]);
            } else {
                //if payment failed update status to Expired
                if (!isset($result->id)) {
                    $wpdb->update($table_name, ['subscription_status' => 'EXPIRED'], ['subscription_id' => $subscription->subscription_id]);
                } else {
                    $new_expiry_date = get_banana_crystal_expiry_date_by_occurence($subscription->subscription_occurrence);
                    $wpdb->update($table_name, ['expired_at' => $new_expiry_date], ['subscription_id' => $subscription->subscription_id]);
                }
            }
        }
    }
}

/**
 * Charge Recurring Payment Bananacrystal
 * 
 * @params (array)$data
 * @return (mixed)
 **/
function banana_crystal_charge_payment($data, $key) {
    $endpoint = 'https://app.bananacrystal.com/api/v1/payment_subscriptions';
    $postdata = json_encode($data);

    $request = array(
        'method'      => 'POST',
        'timeout'     => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Authorization' => 'Bearer '.$key
        ),
        'data_format' => 'body',
        'body'        => $postdata,
        'cookies'     => array(),
        'sslverify'   => true
    );
    $response = wp_remote_post( $endpoint, $request);

    return $response;
}
