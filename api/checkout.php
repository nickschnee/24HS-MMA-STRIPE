<?php

require '../library/init.php';

\Stripe\Stripe::setApiKey('sk_test_');

header('Content-Type: application/json');

// Product ID to Price ID mapping
$productIdToPriceId = [
    'prod_1' => 'price_1QFEtnAJLVj2gF4RA56eLel9',
    'prod_2' => 'price_1QFEwqAJLVj2gF4RP6APNi2L',
    'prod_3' => 'price_1QFEuQAJLVj2gF4R919J1ad9'
];

// Retrieve the cart from POST data
$input = file_get_contents('php://input');
$cart = json_decode($input, true);

// Validate the cart data
if (json_last_error() !== JSON_ERROR_NONE || !is_array($cart)) {
    echo json_encode(['error' => 'Invalid cart data received']);
    http_response_code(400);
    exit;
}

$line_items = [];

// Map products to Stripe line items
foreach ($cart as $item) {
    if (isset($productIdToPriceId[$item['productId']])) {
        $line_items[] = [
            'price' => $productIdToPriceId[$item['productId']],
            'quantity' => $item['quantity'],
        ];
    }
}

// Ensure there are line items before creating a session
if (empty($line_items)) {
    echo json_encode(['error' => 'Cart is empty or contains invalid items']);
    http_response_code(400);
    exit;
}

try {
    // Create Stripe checkout session
    $YOUR_DOMAIN = 'http://stripe.crazy-internet.ch';
    $checkout_session = \Stripe\Checkout\Session::create([
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => $YOUR_DOMAIN . '/success.html',
        'cancel_url' => $YOUR_DOMAIN . '/cancel.html',
    ]);

    echo json_encode(['url' => $checkout_session->url]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Catch Stripe API errors
    echo json_encode(['error' => 'Stripe API error: ' . $e->getMessage()]);
    http_response_code(500);

} catch (Exception $e) {
    // Catch any other general errors
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    http_response_code(500);
}
