<style>
.pack_content_wrapper {
    width: 100%;
    text-align: center;
    max-width: 100% !important;
    align-self: center;
}

.product_pack_item {
    border: 1px solid #cecece;
    padding: 10px;
    border-radius: 10px;
    background: #ffffff;
    display: inline-block;
    margin-left: 40px;
    min-height: 320px;
    min-width: 270px;
}

.product_pack_item h2{ 
    font-size: 28px !important;
}

.pack_price {
    padding-top: 5px;
    color: #FFFFFF !important;
    border-color: #000000 !important;
    background-color: #000000 !important;
}

.buy_product_pack {
    display: inline-block;
    margin-bottom: 0;
    font-weight: normal;
    text-align: center;
    vertical-align: middle;
    touch-action: manipulation;
    cursor: pointer;
    background-image: none;
    border: 1px solid transparent;
    white-space: nowrap;
    background-color: #eee;
    color: #444;
    padding: 6px 12px;
    font-size: 14px;
    line-height: 1.42857143;
    border-radius: 3px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    -o-user-select: none;
    user-select: none;
}
</style>
<div class="pack_content_wrapper">
<?php
    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'banana_crystal_subscriptions';
    $plan = $wpdb->get_row("SELECT * FROM ".$table_name." WHERE user_id=".$user_id." AND subscription_status='ACTIVE' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1");
    if ($plan) { ?>

                    <div class="product_pack_item ">
                            <div class="pack_price">

                                <span class="dps-amount">
                                    <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">
                                    <?php echo get_woocommerce_currency(); ?>  
                                    </span><?php echo number_format($plan->subscription_amount, 2); ?></bdi></span>                                </span> 
                                    <span class="dps-rec-period">
                                        <span class="sep">/</span><?php echo esc_html(banana_crystal_format_occurrence($plan->subscription_occurrence)); ?>                                   
                                    </span>
                            </div><!-- .pack_price -->

                            <div class="pack_content">
                                <h2><?php echo esc_html($plan->subscription_title); ?></h2>
                                
                                <div class="pack_data_option">
                                    Expires at: <?php echo esc_html(banana_crystal_format_date($plan->expired_at, 'M/d/Y')); ?>
                                </div><!-- .pack_data_option -->
                            </div><!-- .pack_content -->

                            <div class="buy_pack_button">
                          <form action="" method="post">
							  <input type="hidden" name="bc_subscription_id" value="<?php echo $plan->subscription_id; ?>">
                              <button type="submit" class="buy_product_pack" name="bc_subscription_cancel_btn" onclick="return confirm('Are you sure this can not be reverted?');">Cancel Subscription</button>
                          </form>
                           
                        </div><!-- .buy_pack_button -->
                    </div><!-- .product_pack_item -->   
                <?php    
    } else { ?>
        <div class="product_pack_item ">
            <div class="pack_content">
                <h2>No Subscriptions found!</h2>
            </div>
        </div>
    <?php } ?>
