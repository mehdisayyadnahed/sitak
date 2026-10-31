<?php
/**
 * کلاسی مستقل و سبک برای API v4 زرین‌پال، بازطراحی‌شده برای سای‌تک.
 * واحد قیمت‌های فروشگاه سای‌تک تومان است و API زرین‌پال ریال می‌گیرد.
 */
final class SiTakZarinPal
{
    private string $merchantId;
    private bool $sandbox;

    public function __construct(string $merchantId, bool $sandbox = true)
    {
        $this->merchantId = trim($merchantId);
        $this->merchantId = trim($this->merchantId, "{} \t\n\r\0\x0B");
        $this->sandbox = $sandbox;
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $this->merchantId)) {
            throw new InvalidArgumentException('مرچنت‌کد زرین‌پال باید یک UUID کامل ۳۶ کاراکتری باشد.');
        }
    }

    private function post(string $endpoint, array $payload): array
    {
        $host = $this->sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        $ch = curl_init($host . '/pg/v4/payment/' . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $error) throw new RuntimeException('ارتباط با زرین‌پال برقرار نشد.');
        $result = json_decode($body, true);
        if (!is_array($result)) throw new RuntimeException('پاسخ نامعتبر از زرین‌پال دریافت شد.');
        return $result;
    }

    public function request(int $amountToman, string $callbackUrl, string $description, string $mobile = ''): string
    {
        $result = $this->post('request.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amountToman * 10,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'metadata' => ['mobile' => $mobile],
        ]);
        if ((int)($result['data']['code'] ?? 0) !== 100 || empty($result['data']['authority'])) {
            throw new RuntimeException($result['errors']['message'] ?? 'ایجاد درخواست پرداخت ناموفق بود.');
        }
        return (string)$result['data']['authority'];
    }

    public function verify(string $authority, int $amountToman): array
    {
        $result = $this->post('verify.json', [
            'merchant_id' => $this->merchantId,
            'authority' => $authority,
            'amount' => $amountToman * 10,
        ]);
        $code = $result['data']['code'] ?? null;
        if ((int)$code !== 100 && (int)$code !== 101) {
            throw new RuntimeException($result['errors']['message'] ?? 'تأیید پرداخت ناموفق بود.');
        }
        return $result['data'];
    }

    public function startUrl(string $authority): string
    {
        $host = $this->sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        return $host . '/pg/StartPay/' . rawurlencode($authority);
    }
}
