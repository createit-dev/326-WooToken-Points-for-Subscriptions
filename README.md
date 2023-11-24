# WooToken Points for Subscriptions

Elevate your WooCommerce subscription experience with WooToken Points. This plugin seamlessly integrates a dynamic token point system, offering customizable token accrual and redemption options for Woo Subscriptions.

## Features

- **Token Rewards**: Automatically award tokens for specific subscription products.
- **Admin Token Management**: Easily manage and deduct tokens from the WordPress admin area.
- **Token Transaction Logging**: Keep track of all token transactions for auditing and user insights.

## Installation

1. Download the plugin from the GitHub repository.
2. Upload the plugin files to your `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.

## Usage

The `add_tokens_for_specific_subscription_product` method is central to the WooToken Points for Subscriptions plugin, awarding tokens to users for specific subscription products. Here's a brief explanation and customization guide:

**Functionality Overview:**
- The method is activated when a subscription payment is completed.
- It checks the latest order against a specified subscription product.
- Tokens are calculated and awarded based on the product's line total.
- The user's token balance is updated, and the transaction is logged.

**Customization Guide:**

1. **Modifying the Product Title:**
    - The `$product_title_to_check` variable determines which subscription product triggers token rewards. Change this to the title of your desired product. For example, replacing "Monthly Subscription 19.99" with "Annual Subscription 99.99" will shift the focus to a different subscription package.

2. **Adjusting Token Calculation Logic:**
    - The token calculation is governed by a conditional statement. You can modify this logic to change how many tokens are awarded. For instance, altering the fixed token amount or adjusting the token-per-dollar rate allows you to customize the reward scale to fit different pricing strategies or promotional activities.

By tweaking these two parameters, you can effectively customize the plugin to suit various subscription products and token reward strategies, aligning with your specific business goals and customer engagement plans.

## Admin screen

![WooToken-Points-for-Subscriptions.jpg](imgs%2FWooToken-Points-for-Subscriptions.jpg)

## My account subscription screen

![WooToken-Points-for-Subscriptions-My-Account.jpg](imgs%2FWooToken-Points-for-Subscriptions-My-Account.jpg)
