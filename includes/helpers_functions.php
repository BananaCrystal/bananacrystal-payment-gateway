<?php

/**
 * Format subscription occurrence
 * 
 * @param (string) $occur
 * @return (string)
 */
function banana_crystal_format_occurrence($occur) {
    $occur = str_replace('_', ' ', $occur);
    return ucwords($occur);
}


/**
 * Format date time to different format
 * 
 * @param (string) $date
 * @param (string) $format
 * @return (string)
 */
function banana_crystal_format_date($date, $format = 'M/d/Y h:i a') {
    return date($format, strtotime($date));
}


/**
 * Get subscription plan by id from db
 * 
 * @param (int) $plan_id
 * @return (object/null)
 */
function get_banana_crystal_subscription_plan($plan_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'banana_crystal_subscription_plans';
    $result = $wpdb->get_row("SELECT * FROM $table_name  WHERE deleted_at IS NULL AND subscription_plan_id=".$plan_id);
    return $result;
}


/**
 * Get expiry date by occurence from current date
 * 
 * @param (string) $occur
 * @return (string)
 */
function get_banana_crystal_expiry_date_by_occurence($occur) {
    $expiry_date = '';
    switch($occur) {
        case 'monthly': 
            $expiry_date = date('Y-m-d H:i:s', strtotime('+1 MONTH'));
            break;
        case 'weekly': 
            $expiry_date = date('Y-m-d H:i:s', strtotime('+1 WEEK'));
            break;
        case 'quaterly': 
            $expiry_date = date('Y-m-d H:i:s', strtotime('+3 MONTH'));
            break;
        case 'six_months': 
            $expiry_date = date('Y-m-d H:i:s', strtotime('+6 MONTH'));
            break;
        case 'yearly': 
            $expiry_date = date('Y-m-d H:i:s', strtotime('+1 YEAR'));
            break;
    }

    return $expiry_date;
}