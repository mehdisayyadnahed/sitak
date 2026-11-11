<?php
/**
 * اتصال محدود و بومی سای‌تک به SMS.ir.
 * فقط Verify و UltraFast استفاده می‌شود؛ هیچ gateway دیگری از Digits وارد نشده است.
 */
final class SiTakSmsIr
{
    private $apiKey;
    private $baseUrl = 'https://api.sms.ir/v1';
    private $sandbox = false;

    public function __construct($apiKey, $sandbox = false)
    {
        $this->apiKey = trim($apiKey);
        $this->sandbox = (bool)$sandbox;
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('کلید API پیامک تنظیم نشده است.');
        }
    }

    public function sendTemplate($mobile, $templateId, array $parameters)
    {
        if (!preg_match('/^09\d{9}$/', $mobile) || $templateId < 1) {
            throw new InvalidArgumentException('شماره موبایل یا شناسه قالب پیامک نامعتبر است.');
        }
        $payload = ['mobile' => $mobile, 'templateId' => $templateId, 'parameters' => []];
        foreach ($parameters as $name => $value) {
            $payload['parameters'][] = ['name' => (string)$name, 'value' => (string)$value];
        }
        return $this->post('/send/verify', $payload);
    }

    private function post($path, array $payload)
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'x-api-key: ' . $this->apiKey],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $error) throw new RuntimeException('ارتباط با SMS.ir برقرار نشد.');
        $result = json_decode($body, true);
        if (!is_array($result)) throw new RuntimeException('پاسخ نامعتبر از SMS.ir دریافت شد.');
        if ($code >= 400 || (($result['status'] ?? true) === false)) {
            $message = $result['message'] ?? $result['Message'] ?? 'ارسال پیامک ناموفق بود.';
            throw new RuntimeException((string)$message);
        }
        return $result;
    }
}

function sitak_sms_send($mobile, $settingKey, array $parameters = [])
{
    global $conn;
    $sandbox = getSetting($conn, 'sms_sandbox', '1') === '1';
    if (!$sandbox && getSetting($conn, 'sms_enabled', '0') !== '1') return false;
    $templateId = (int)getSetting($conn, $settingKey, '');
    $apiKey = trim(getSetting($conn, 'sms_api_key', ''));
    if ($settingKey === 'sms_otp_template_id' && $sandbox && $templateId < 1) {
        $templateId = 123456;
    }
    if ($templateId < 1 || $apiKey === '') {
        return false;
    }
    try {
        (new SiTakSmsIr($apiKey, $sandbox))->sendTemplate($mobile, $templateId, $parameters);
        return true;
    } catch (Throwable $e) {
        error_log('[SiTak SMS.ir] ' . $e->getMessage());
        return false;
    }
}
