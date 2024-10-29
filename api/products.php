<?php

require '../library/init.php';

\Stripe\Stripe::setApiKey('sk_test');

header('Content-Type: application/json');

// Initialize Stripe Client
$stripe = new \Stripe\StripeClient('sk_test_');

try {
    // Fetch all products
    $products = $stripe->products->all();

    // Output the products as JSON
    echo json_encode($products);

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Handle any Stripe API errors
    echo json_encode(['error' => 'Stripe API error: ' . $e->getMessage()]);
    http_response_code(500);

} catch (Exception $e) {
    // Handle other errors
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    http_response_code(500);
}
