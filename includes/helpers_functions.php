<?php

function format_occurrence($occur) {
    $occur = str_replace('_', ' ', $occur);
    return ucwords($occur);
}

function format_date($date, $format = 'M/d/Y h:i a') {
    return date($format, strtotime($date));
}

function get_subscription_plan($plan_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'banana_crystal_subscription_plans';
    $result = $wpdb->get_row("SELECT * FROM $table_name  WHERE deleted_at IS NULL AND subscription_plan_id=".$plan_id);
    return $result;
}

function get_expiry_date_by_occurence($occur) {
    $days = 0;
    switch($occur) {
        case 'monthly': 
            $days = 30;
            break;
        case 'weekly': 
            $days = 7;
            break;
        case 'quaterly': 
            $days = 90;
            break;
        case 'six_months': 
            $days = 180;
            break;
        case 'yearly': 
            $days = 365;
            break;
    }

    return date('Y-m-d H:i:s', strtotime('+'.$days.' days'));
}