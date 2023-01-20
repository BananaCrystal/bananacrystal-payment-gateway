<?php
    $plan_row = null;
    if (isset($_GET['upt'])) {
        $plan_row = get_banana_crystal_subscription_plan((int)sanitize_text_field($_GET['upt']));
    }
?>
<style>
.plan_occurrence, .form-table input{
    width: 100%;
    max-width: 25rem;
}
</style>
<div class="wrap">
        <?php if(!isset($_GET['upt'])){ ?>
            <h2>Add Subscription Plan</h2>
            <p>Create new subscription plans to be used with BananaCrystal payment gateway. All fields are manadatory.</p>
        <?php } else { ?>
            <h2>Update Subscription Plan</h2>
            <p>Update subscription plans to be used with BananaCrystal payment gateway. All fields are manadatory.</p>
        <?php } ?>
            <form method="post" action="">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Title:</th>
                                <td>
                                    <input type="text" name="plan_title" min="10" required
                                           value="<?php echo $plan_row ? esc_html($plan_row->subscription_plan_title) : ''; ?>"/>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Description/Features:</th>
                                <td>
                                   <?php wp_editor( ($plan_row ? htmlspecialchars_decode($plan_row->subscription_plan_description) : ''), 'editsometxt', array('required'=>true, 'textarea_name'=>'plan_description','media_buttons'=>true,'tinymce'=>true,'textarea_rows'=>10,'wpautop'=>false)); ?>
                                </td>
                            </tr>

							<tr valign="top">
                                <th scope="row">Recurring Amount:</th>
                                <td>
                                    <input type="text" name="plan_recurring_amount" min="10" required
                                           value="<?php echo $plan_row ? number_format($plan_row->subscription_plan_amount, 2) : ''; ?>"/>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Occurrence:</th>
                                <td>
                                <?php $selected_occur = $plan_row ? $plan_row->subscription_plan_occurrence : ''; ?>
                                    <select name="plan_occurrence" class="plan_occurrence" required>
                                        <option value="monthly" <?php echo $selected_occur == 'monthly' ? 'selected':'' ?>>Monthly</option>
                                        <option value="weekly" <?php echo $selected_occur == 'weekly' ? 'selected':'' ?>>Weekly</option>
                                        <option value="quaterly" <?php echo $selected_occur == 'quaterly' ? 'selected':'' ?>>Quaterly</option>
                                        <option value="six_months" <?php echo $selected_occur == 'six_months' ? 'selected':'' ?>>Six Months</option>
                                        <option value="yearly" <?php echo $selected_occur == 'yearly' ? 'selected':'' ?>>Yearly</option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                        <?php if(!isset($_GET['upt'])){ ?>
                            <input type="submit" class="button-primary" name="add_btn" value="<?php _e( 'Create' ) ?>"/>
                        <?php } else { ?>
                            <input type="hidden" value="<?php echo $plan_row ? $plan_row->subscription_plan_id : ''; ?>"  name="plan_id"/>
                            <input type="submit" class="button-primary" name="update_btn" value="<?php _e( 'Update' ) ?>"/>
                        <?php } ?>
                        </p>


    </form>
</div>