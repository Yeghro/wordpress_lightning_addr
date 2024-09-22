<?php

class InvoiceGenerator {
    const API_ENDPOINT = '/api/v1/payments';

    private $api_url;
    private $api_key;

    public function set_api_credentials($api_url, $api_key) {
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid API URL');
        }
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
    }

    public function generate_invoice($amount, $memo) {
        $amount = filter_var($amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $memo = sanitize_text_field($memo);

        try {
            // 1. Validate input parameters
            if (!is_numeric($amount) || $amount <= 0) {
                throw new InvalidArgumentException('Invalid amount');
            }

            if (empty($memo)) {
                throw new InvalidArgumentException('Memo cannot be empty');
            }

            // 2. Prepare the request data
            $data = array(
                'amount' => $amount,
                'memo' => $memo,
                'out' => false,
                'unit' => 'sat'
            );

            // 3. Set up cURL request to LNbits API
            $ch = curl_init($this->api_url . self::API_ENDPOINT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-Api-Key: ' . $this->api_key
            ));

            // 4. Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 5. Handle the response
            if ($http_code !== 200) {
                throw new Exception('Failed to generate invoice. HTTP Code: ' . $http_code);
            }

            $result = json_decode($response, true);
            if (!$result || !isset($result['payment_hash']) || !isset($result['payment_request'])) {
                throw new Exception('Invalid response from LNbits API');
            }

            // 6. Return the invoice details
            return array(
                'payment_hash' => $result['payment_hash'],
                'payment_request' => $result['payment_request']
            );
        } catch (Exception $e) {
            Logger::log('Error generating invoice: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    public function verify_payment($payment_hash) {
        $ch = curl_init($this->api_url . '/api/v1/payments/' . $payment_hash);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Api-Key: ' . $this->api_key
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to verify payment. HTTP Code: ' . $http_code);
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['paid'])) {
            throw new Exception('Invalid response from LNbits API');
        }

        return $result['paid'];
    }

    // ... rest of the class ...
}
