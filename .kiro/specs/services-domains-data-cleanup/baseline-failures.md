# Baseline Test Failures

Recorded for task 1.1, before any code change. Later checkpoints compare against this list so existing failures are not treated as regressions.

## How it was run

1. `php artisan test` with the repository as-is: **102 failed, 133 passed**. 73 of those failures are `MissingAppKeyException` ("No application encryption key has been specified"). `phpunit.xml` does not set `APP_KEY` and the local `.env` does not provide a usable key to the test run, so this is an environment problem and hides the real failures.
2. Same command with a throwaway key exported in the shell (`APP_KEY="base64:$(openssl rand -base64 32)" php artisan test`). No file was changed. Result: **29 failed, 206 passed**. This is the working baseline below.

**Every later checkpoint must run with `APP_KEY` set the same way**, or compare against run 1 knowing the MissingAppKey noise is present.

## Expected failures (confirmed)

### `Tests\Unit\Services\CheckoutServiceTest` (13 tests, all `ArgumentCountError`)
`Too few arguments to function App\Services\CheckoutService::__construct()`. The test builds the service with three arguments (stripe, booking, notification) and the constructor takes four.

- test_only_one_off_items_creates_checkout_session_not_subscription
- test_only_recurring_items_creates_subscriptions_not_checkout_session
- test_mixed_cart_calls_both_checkout_session_and_subscriptions
- test_empty_cart_calls_neither_checkout_session_nor_subscription
- test_property_partitioning_call_counts_match_item_counts
- test_one_off_items_line_items_contain_correct_price_data
- test_recurring_items_each_create_individual_subscription_with_metadata
- test_hosting_items_are_treated_as_recurring
- test_equipment_rental_items_are_treated_as_recurring
- test_checkout_decrements_stock_by_purchased_quantity
- test_checkout_fails_when_stock_insufficient
- test_checkout_skips_decrement_for_unlimited_stock
- test_checkout_only_decrements_stock_for_one_off_products

### `Tests\Feature\FulfilmentServiceTest` (3 tests, rental assertions)
- test_property_equipment_rental_purchase_decrements_stock_by_exactly_one: `Failed asserting that 10 matches expected 9` (line 128). Stock is not decremented by `handleEquipmentRentalPurchase`.
- test_property_equipment_rental_decrements_stock: `Failed asserting that 7 matches expected 6` (line 595). Same cause.
- test_property_equipment_rental_creates_active_booking: expected `'active'`, got `'confirmed'` (line 507). **Not named in the design.** It is a booking status assertion, not a stock one, and fails today.

## Other failures not named in the design

### `Tests\Feature\Portal\CheckoutFlowTest` (5 tests)
- test_property_customer_without_stripe_id_has_ensure_customer_called
- test_property_customer_with_existing_stripe_id_ensure_customer_still_called
- test_property_ensure_customer_always_called_before_payment_processing
- test_property_failed_checkout_with_multiple_items_preserves_entire_cart

  These four: Mockery `InvalidCountException`, `StripeService::createCheckoutSession` expected 1 (or 3) times but called 0 times.
- test_successful_checkout_clears_cart_and_redirects: redirect is `http://localhost/portal/cart`, expected `https://checkout.stripe.com/pay/cs_success`. So the checkout does not reach the mocked Stripe call in these tests. This file exercises the code Phase 2 rewrites, so watch it at the Phase 2 checkpoint.

### Starter-kit auth and page tests, missing `dashboard` route or related behaviour (8 tests)
- `Tests\Feature\Auth\AuthenticationTest::test_users_can_authenticate_using_the_login_screen`: `RouteNotFoundException: Route [dashboard] not defined.`
- `Tests\Feature\Auth\EmailVerificationTest::test_email_can_be_verified`: `Route [dashboard] not defined.`
- `Tests\Feature\ProfileTest::test_profile_page_is_displayed`: 500, `Route [dashboard] not defined.`
- `Tests\Feature\Auth\RegistrationTest::test_new_users_can_register`: `The user is not authenticated`.
- `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response`: expected 200, got 302.
- `Tests\Feature\Auth\PasswordResetTest::test_reset_password_link_can_be_requested`, `::test_reset_password_screen_can_be_rendered`, `::test_password_can_be_reset_with_valid_token`: expected `ResetPassword` notification was not sent.

These come from the Breeze scaffolding no longer matching the app's routes. They are unrelated to this spec.

## Totals (with APP_KEY set)
| Group | Failing |
|---|---|
| CheckoutServiceTest | 13 |
| FulfilmentServiceTest | 3 |
| CheckoutFlowTest | 5 |
| Auth / Profile / Example scaffolding | 8 |
| **Total** | **29** (206 passed) |

No code changes were made for this task.
