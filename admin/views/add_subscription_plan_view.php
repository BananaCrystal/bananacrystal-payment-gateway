<style>
.plan_occurrence, .form-table input{
    width: 100%;
    max-width: 25rem;
}
</style>
<div class="wrap">
            <h2>Add Subscription Plan</h2>
            <p>Create new subscription plans to be used with BananaCrystal payment gateway. All fields are manadatory.</p>
            <form method="post" action="">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Title:</th>
                                <td>
                                    <input type="text" name="plan_title" min="10" required
                                           value=""/>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Description/Features:</th>
                                <td>
                                   <?php wp_editor( '', 'editsometxt', array('required'=>true, 'textarea_name'=>'plan_description','media_buttons'=>true,'tinymce'=>true,'textarea_rows'=>10,'wpautop'=>false)); ?>
                                </td>
                            </tr>

							<tr valign="top">
                                <th scope="row">Recurring Amount:</th>
                                <td>
                                    <input type="text" name="plan_recurring_amount" min="10" required
                                           value="1"/>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">Occurrence:</th>
                                <td>
                                    <select name="plan_occurrence" class="plan_occurrence" required>
                                        <option value="monthly" selected>Monthly</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="quaterly">Quaterly</option>
                                        <option value="six_months">Six Months</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" class="button-primary" name="add_btn" value="<?php _e( 'Create' ) ?>"/>
                        </p>


    </form>
</div>