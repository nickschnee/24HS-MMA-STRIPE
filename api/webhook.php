<?php
require 'config.php';
require '../library/init.php';

\Stripe\Stripe::setApiKey('sk_test');

$endpoint_secret = 'whsec_'; // You'll get this from Stripe dashboard

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Handle the checkout.session.completed event
if ($event->type == 'checkout.session.completed') {
    $session = $event->data->object;
    
    try {
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert into sales table with address information
        $stmt = $pdo->prepare("
            INSERT INTO sales 
            (stripe_session_id, customer_email, customer_name, street, postal_code, city, country, total_amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Get address details from the shipping or billing address
        $address = $session->shipping ?? $session->customer_details->address;
        
        $stmt->execute([
            $session->id,
            $session->customer_details->email,
            $session->customer_details->name,
            $address->line1,
            $address->postal_code,
            $address->city,
            $address->country,
            $session->amount_total / 100
        ]);
        
        $saleId = $pdo->lastInsertId();
        
        // Insert line items
        $lineItems = \Stripe\Checkout\Session::retrieve([
            'id' => $session->id,
            'expand' => ['line_items'],
        ])->line_items;
        
        $stmt = $pdo->prepare("
            INSERT INTO sale_items 
            (sale_id, product_name, quantity, price_per_unit) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($lineItems->data as $item) {
            $stmt->execute([
                $saleId,
                $item->description,
                $item->quantity,
                $item->price->unit_amount / 100
            ]);
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Webhook Error: ' . $e->getMessage());
        http_response_code(500);
        exit();
    }
}

http_response_code(200);
?>