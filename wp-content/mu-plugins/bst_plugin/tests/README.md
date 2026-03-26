# BST plugin — automated tests

## Setup (once)

From this folder (`bst_plugin`):

```bash
composer install
```

## Run

```bash
composer exec phpunit
```

Or:

```bash
vendor/bin/phpunit
```

## Scope

- **Current:** PHPUnit loads `includes/booking-payment-status.php` with minimal WordPress stubs (`ABSPATH`, `sanitize_text_field`, `add_action`).
- **Later:** Add more bootstrap stubs, or switch to `wp-phpunit` / a full WP test install for integration tests that touch the database or Gravity Forms.
