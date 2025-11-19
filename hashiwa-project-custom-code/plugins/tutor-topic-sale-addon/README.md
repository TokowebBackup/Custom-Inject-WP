# Tutor LMS - Topic Based Payment Addon (Enhanced)

## What's included
- Per-topic price meta box (creates/updates WooCommerce product automatically)
- Frontend "Buy this topic" button (AJAX add to cart)
- Checkout flow via WooCommerce
- Grant topic access on order complete
- Protection: redirect from lesson page to course if topic not purchased
- Minimal CSS/JS for UX improvements

## Installation
1. Copy the `tutor-topic-sale-addon` folder into `wp-content/plugins/`.
2. Activate Tutor LMS and WooCommerce.
3. Activate this plugin.
4. Edit a Topic and set a price; saving will create linked WooCommerce product.
5. Visit a course curriculum; priced topics show "Buy this topic".
6. Purchase through WooCommerce; after order complete, topic access is granted.

## Notes & Next steps
- Test on staging first.
- Handle guest checkout (map email to user or create account on purchase).
- Integrate with Tutor progress/certificate logic if needed.
- Add refund handling and revoke access on refunds.
