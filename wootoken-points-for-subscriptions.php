<?php
/**
 * Plugin Name: WooToken Points for Subscriptions
 * Description: Elevate your WooCommerce subscription experience with WooToken Points. This plugin seamlessly integrates a dynamic token point system, offering customizable token accrual and redemption options for Woo Subscriptions plugin.
 * Version: 1.0.1
 */



if ( ! defined( 'CT_WOO_INTEGRATION_VERSION' ) ) {
    define( 'CT_WOO_INTEGRATION_VERSION', '1.0.1' );
}


// Ensure WooCommerce and WooCommerce Subscriptions are active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) &&
    in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    // Add tokens for specific subscription product
    function add_tokens_for_specific_subscription_product( $subscription ) {
        // The $subscription object is an instance of WC_Subscription
        $product_title_to_check = "Monthly Subscription 19.99";

        // Get the latest order related to this subscription
        $last_order = $subscription->get_last_order();
        $order = wc_get_order( $last_order );

        // Check if the order contains the specific product and get its line total
        $product_line_total = 0;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product && $product->get_name() == $product_title_to_check ) {
                $product_line_total = $item->get_total();
                break;
            }
        }

        if ( $product_line_total > 0 ) {
            $user_id = $subscription->get_user_id();

            // Determine the number of tokens to award
            $tokens_to_award = 0;
            if ( $product_line_total == 19.99 ) {
                $tokens_to_award = 30;
            } else {
                $rate = 1.5;  // number of tokens per dollar
                $tokens_to_award = $product_line_total * $rate;
            }

            // Get current token balance
            $current_balance = get_user_meta( $user_id, 'user_token_balance', true );
            $current_balance = empty( $current_balance ) ? 0 : $current_balance;

            // Add the determined tokens to the current balance
            $new_balance = $current_balance + $tokens_to_award;

            // Update user token balance
            update_user_meta( $user_id, 'user_token_balance', $new_balance );

            // Log the token addition
            ct_log_token_transaction( $user_id, $tokens_to_award, 'Tokens awarded for order ID: ' . $last_order );
        }
    }

    add_action( 'woocommerce_subscription_payment_complete', 'add_tokens_for_specific_subscription_product' );



    // Admin function to deduct tokens
    function ct_deduct_tokens( $user_id, $amount, $reason ) {
        $current_balance = get_user_meta( $user_id, 'user_token_balance', true );
        $new_balance = intval($current_balance) - $amount;  // Cast current_balance to integer

        // Ensure the new balance is not negative
        if ( $new_balance < 0 ) {
            return false;  // Deduction not possible
        }

        // Update user token balance
        update_user_meta( $user_id, 'user_token_balance', $new_balance );

        // Log the transaction
        ct_log_token_transaction( $user_id, -$amount, $reason );

        return true;  // Deduction successful
    }


    // Function to log token transactions
    function ct_log_token_transaction( $user_id, $amount, $reason = '' ) {
        $logger = wc_get_logger();
        $context = array( 'source' => 'ct-token-transactions' );

        $user_info = get_userdata( $user_id );
        $log_message = sprintf(
            'Token Transaction for user %s (ID: %d): Amount: %d, Reason: %s',
            $user_info->user_login,
            $user_id,
            $amount,
            $reason
        );

        $logger->info( $log_message, $context );
    }

    global $ct_my_page_hook;

    // Add a submenu page under WooCommerce
    function ct_add_submenu_page() {
        global $ct_my_page_hook;
        $ct_my_page_hook = add_submenu_page(
            'woocommerce',
            'Deduct Tokens',
            'Deduct Tokens',
            'manage_woocommerce', // Capability required
            'ct-deduct-tokens',
            'ct_render_deduct_tokens_page'
        );
    }

    add_action( 'admin_menu', 'ct_add_submenu_page' );


    function ct_render_deduct_tokens_page() {
        $form_submitted = false;

        // add jquery for select2
        wp_enqueue_script('jquery');

        // Render the form
        ?>
        <h1>Token Management Dashboard</h1>
        <div class="wrap">
            <p class="ct-h2">Deduct Tokens</p>

            <?php
            // Handle form submission
            if ( isset( $_POST['ct_deduct_tokens_nonce'] ) && wp_verify_nonce( $_POST['ct_deduct_tokens_nonce'], 'ct_deduct_tokens' ) ) {
                $user_id = intval( $_POST['user_id'] );
                $amount = intval( $_POST['amount'] );
                $reason = sanitize_text_field( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : 'Admin deduction';

                if ($amount <= 0) {
                    wp_die("Please enter a positive amount for deduction.");
                }

                if ( $user_id && $amount ) {
                    $deducted = ct_deduct_tokens( $user_id, $amount, $reason );
                    if ( $deducted ) {
                        $user_info = get_userdata($user_id);
                        $user_display_name = $user_info->first_name . ' ' . $user_info->last_name;
                        echo '<div class="notice notice-success is-dismissible"><p>Tokens deducted successfully from ' . $user_display_name . '! Amount deducted: ' . $amount . ' tokens.</p></div>';
                        $form_submitted = true;
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>Failed to deduct tokens. Maybe the user doesn’t have enough tokens.</p></div>';
                        $form_submitted = true;
                    }
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error.</p></div>';
                    $form_submitted = true;
                }
            }
            ?>
            <?php  if (!$form_submitted): ?>
                <form method="post">
                    <?php wp_nonce_field( 'ct_deduct_tokens', 'ct_deduct_tokens_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                        <tr>
                            <th scope="row"><label for="user_id">Select User</label></th>
                            <td>
                                <?php
                                $users = get_users( array( 'orderby' => 'ID' ) );
                                echo '<select name="user_id" id="user_dropdown">';
                                echo '<option value="">- Select User -</option>';

                                // Users with positive balance
                                echo '<optgroup label="Users With Balance">';
                                foreach ( $users as $user ) {
                                    $balance = get_user_meta( $user->ID, 'user_token_balance', true );
                                    if ( $balance > 0 ) {
                                        $displayName = $user->ID . ' - ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->user_login . ')';
                                        echo '<option value="' . $user->ID . '" data-userid="' . $user->ID . '" data-balance="' . $balance . '" ' . selected( isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0, $user->ID, false ) . '>' . $displayName . '</option>';
                                    }
                                }
                                echo '</optgroup>';

                                // Users with zero balance or no balance set
                                echo '<optgroup label="Users Without Balance">';
                                foreach ( $users as $user ) {
                                    $balance = get_user_meta( $user->ID, 'user_token_balance', true );
                                    if ( empty($balance) || $balance == 0 ) {
                                        $displayName = $user->ID . ' - ' . $user->first_name . ' ' . $user->last_name . ' (' . $user->user_login . ')';
                                        echo '<option value="' . $user->ID . '" data-userid="' . $user->ID . '" data-balance="' . $balance . '" ' . selected( isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0, $user->ID, false ) . '>' . $displayName . '</option>';
                                    }
                                }
                                echo '</optgroup>';

                                echo '</select>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="amount">Current balance</label></th>
                            <td><span id="user_balance_display"></span></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="amount">Amount to Deduct</label></th>
                            <td><input type="number" name="amount" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="reason">Reason (optional)</label></th>
                            <td><input type="text" name="reason"></td>
                        </tr>
                    </table>
                    <p class="submit"><input type="submit" class="button button-primary" value="Deduct Tokens"></p>
                </form>
            <?php else: ?>
                <a href="<?php echo admin_url('admin.php?page=ct-deduct-tokens'); ?>" class="button">⬅️ Go Back</a>
            <?php endif; ?>


            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    let dropdown = document.getElementById("user_dropdown");
                    let balanceDisplay = document.getElementById("user_balance_display");

                    dropdown.addEventListener("change", function() {
                        let selectedOption = dropdown.options[dropdown.selectedIndex];
                        let balance = selectedOption.getAttribute("data-balance");
                        let userID = selectedOption.getAttribute("data-userid");

                        if (balance) {
                            balanceDisplay.textContent = "UserID " + userID + " has balance: " + balance + " tokens" ;
                        } else {
                            balanceDisplay.textContent = "[No Subscription]";
                        }
                    });
                });
            </script>

        </div>
        <?php
    }


    function ct_display_user_token_balance( $subscription ) {
        // Get the user ID associated with the subscription
        $user_id = $subscription->get_user_id();
        $user = get_userdata( $user_id );

        // Fetch the user's token balance
        $token_balance = get_user_meta( $user_id, 'user_token_balance', true );

        // Fetch the rebill date
        $rebill_date = $subscription->get_date( 'next_payment' );

        // Display the member card
        echo '
    <h2>Member card</h2>
    <div class="card">
        <div class="card-header">
            <h2>Member card</h2>
        </div>
        <div class="client-logo">
            <img src="/wp-content/uploads/2023/07/logo.png" alt="">
        </div>
        <div class="card-content">
            <p class="card-text">Client\'s Name: ' . esc_html( $user->first_name . ' ' . $user->last_name ) . '</p>
            <p class="card-text">Amount of Tokens: ' . esc_html( $token_balance ) . ' </p>
            <p class="card-text">Member Number: ' . esc_html( $user_id ) . '</p>
            <p class="card-text">Rebill Date: ' . esc_html( date_i18n( wc_date_format(), strtotime( $rebill_date ) ) ) . '</p>
        </div>
    </div>
    <br><bR>
    ';
    }

    add_action( 'woocommerce_subscription_details_after_subscription_table', 'ct_display_user_token_balance' );


    function ct_enqueue_custom_plugin_styles() {
        $plugin_url = plugin_dir_url( __FILE__ );

        wp_enqueue_style( 'ct-custom-plugin-style',  $plugin_url . 'assets/style.css', array(), CT_WOO_INTEGRATION_VERSION);

    }

    add_action( 'wp_enqueue_scripts', 'ct_enqueue_custom_plugin_styles' );


}

