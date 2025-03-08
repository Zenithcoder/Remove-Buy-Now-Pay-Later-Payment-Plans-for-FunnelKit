<?php
/**
 * Plugin Name: Remove Buy Now Pay Later Payment Plans for FunnelKit
 * Plugin URI:  https://github.com/zenithcoder
 * Description: Disables Buy Now Pay Later (BNPL) payment gateways from FunnelKit's Stripe integration when a subscription product with 2 or more installments or an ongoing subscription is in the cart.
 * Version:     1.1
 * Author:      zenithcoder
 * Author URI:  https://github.com/zenithcoder
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Remove_BNPL_For_FunnelKit
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disables Buy Now Pay Later payment gateways for subscription products
 * Works on both cart and checkout pages
 */

// Function to check if BNPL should be disabled
function should_disable_bnpl()
{
    if (!is_object(WC()->cart) || WC()->cart->is_empty()) {
        return false;
    }

    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];

        if (!is_object($product)) {
            continue;
        }

        $is_subscription = (
            $product->is_type('subscription') ||
            $product->is_type('variable-subscription') ||
            $product->is_type('subscription_variation')
        );

        if ($is_subscription) {
            $subscription_length = get_post_meta($product->get_id(), '_subscription_length', true);
            if (empty($subscription_length) || intval($subscription_length) >= 2) {
                return true;
            }
        }
    }
    return false;
}

// Filter for checkout page
add_filter('woocommerce_available_payment_gateways', 'disable_bnpl_for_subscriptions', 20, 1);

function disable_bnpl_for_subscriptions($available_gateways)
{
    if (should_disable_bnpl()) {
        foreach ($available_gateways as $gateway_id => $gateway) {
            if (in_array($gateway->id, ['fkwcs_stripe_affirm', 'fkwcs_stripe_klarna', 'fkwcs_stripe_afterpay'])) {
                unset($available_gateways[$gateway_id]);
            }
        }
    }
    return $available_gateways;
}

// Filter for cart page
add_filter('woocommerce_cart_needs_payment', 'modify_cart_payment_display', 10, 2);

function modify_cart_payment_display($needs_payment, $cart)
{
    if (should_disable_bnpl() && is_cart()) {
        // Hide payment section in cart if only BNPL options would be available
        // add_action('woocommerce_before_cart', 'add_bnpl_cart_notice');

        // Optional: Remove any BNPL-related elements from cart page
        add_action('wp_footer', 'remove_bnpl_elements_cart');
    }
    return $needs_payment;
}

// Add notice to cart page
function add_bnpl_cart_notice()
{
    wc_print_notice(
        __(
            'Buy Now Pay Later payment options are not available for subscriptions of 6 months or longer.',
            'your-theme-text-domain'
        ),
        'notice'
    );
}

// Remove BNPL elements from cart page using JavaScript
function remove_bnpl_elements_cart()
{
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            // Select all elements with the class "p-CondensedMultiPromotionView"
            var elements = document.querySelectorAll('[data-testid="pmme-initial-messaging-condensed-multi-promotion-view"]');
            // Loop through the elements and hide each one
            for (var i = 0; i < elements.length; i++) {
                elements[i].style.display = 'none';
            }

        });
    </script>
    <?php
}


// Debug endpoint
add_action('init', function () {
    if (isset($_GET['debug_bnpl']) && current_user_can('manage_options')) {
        error_log('Current Page: ' . (is_cart() ? 'Cart' : (is_checkout() ? 'Checkout' : 'Other')));
        error_log('Should Disable BNPL: ' . (should_disable_bnpl() ? 'Yes' : 'No'));

        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        error_log('Available Gateways: ' . print_r($gateways, true));

        $cart_items = [];
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $cart_items[] = [
                'product_id' => $product->get_id(),
                'is_subscription' => $product->is_type(
                    ['subscription', 'variable-subscription', 'subscription_variation']
                ),
                'length' => get_post_meta($product->get_id(), '_subscription_length', true),
                'subscription_period' => get_post_meta($product->get_id(), '_subscription_period', true)
            ];
        }
        error_log('Cart Contents: ' . print_r($cart_items, true));
    }
});