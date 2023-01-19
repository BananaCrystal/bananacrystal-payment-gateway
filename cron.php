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
                'sender_ref' => $subscription->user_id,
                'subscription_id' => $subscription->subscription_plan_id,
                'subscriber_username' => $subscription->buyer_user_name,
                'store_username' => $store_username
            ];
            $result = bananaCrystalChargePayment($data, $subscription_key);

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

/**
 * Charge Recurring Payment Bananacrystal
 * 
 * @params (array)$data
 * @return (mixed)
 **/
private function bananaCrystalChargePayment($data, $key) {
    $endpoint = 'https://app.bananacrystal.com/api/v1/payment_subscriptions';
    $postdata = json_encode($data);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL =>  $endpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$postdata,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Bearer '.$key
      ),
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}