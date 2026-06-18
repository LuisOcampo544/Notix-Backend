<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

class StripeController
{
    public function createSession()
    {
        $userId = authenticate();
        $user = findUserById($userId);
        
        if ($user['is_premium']) {
            errorResponse('Ya eres usuario premium', 400);
        }

        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET']);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $_ENV['STRIPE_PRICE_ID'],
                'quantity' => 1,
            ]],
            'success_url' => $_ENV['FRONTEND_URL'] . '/notes?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $_ENV['FRONTEND_URL'] . '/premium',
            'client_reference_id' => $userId,
        ]);

        jsonResponse(['url' => $session->url]);
    }

    public function webhook()
    {
        $payload = @file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET']);
        $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'];

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            http_response_code(400);
            exit;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            exit;
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $userId = (int) $session->client_reference_id;
            if ($userId) {
                $db = getConnection();
                $stmt = $db->prepare('UPDATE users SET is_premium = 1 WHERE id = :id');
                $stmt->execute(['id' => $userId]);
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }
}