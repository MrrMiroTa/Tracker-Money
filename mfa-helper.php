<?php
/**
 * mfa-helper-v2.php - Upgraded Multi-Factor Authentication (MFA) Helper
 * 
 * ឯកសារជំនួយសម្រាប់ការគ្រប់គ្រងការផ្ទៀងផ្ទាត់ពីរជំហាន (MFA) ដោយប្រើប្រាស់ Google Authenticator TOTP។
 * កែសម្រួលឡើងវិញ៖ ប្រើប្រាស់ QR Server API ដែលមានស្ថេរភាពខ្ពស់ជំនួស Google Charts API ចាស់។
 */

class MFAHelper {
    
    /**
     * បង្កើត random 16-character Base32 Secret Key សម្រាប់គណនីនីមួយៗ
     */
    public static function generateSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * មុខងារបំប្លែង Base32 ទៅជាអក្សរធម្មតា (Binary)
     */
    private static function base32Decode($secret) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $buf = '';
        $val = 0;
        $val_len = 0;
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $c = $secret[$i];
            $v = strpos($alphabet, $c);
            if ($v === false) continue;
            $val = ($val << 5) | $v;
            $val_len += 5;
            if ($val_len >= 8) {
                $val_len -= 8;
                $buf .= chr(($val >> $val_len) & 0xFF);
            }
        }
        return $buf;
    }

    /**
     * ផ្ទៀងផ្ទាត់លេខកូដ ៦ ខ្ទង់ពី Google Authenticator
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $code = trim($code);
        if (strlen($code) !== 6 || !is_numeric($code)) {
            return false;
        }

        $key = self::base32Decode($secret);
        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $timeSlice = $currentTimeSlice + $i;
            $time_bytes = pack('N*', 0) . pack('N*', $timeSlice);
            $hash = hash_hmac('sha1', $time_bytes, $key, true);
            $offset = ord($hash[19]) & 0xf;
            
            $otp = (
                (ord($hash[$offset+0]) & 0x7f) << 24 |
                (ord($hash[$offset+1]) & 0xff) << 16 |
                (ord($hash[$offset+2]) & 0xff) << 8 |
                (ord($hash[$offset+3]) & 0xff)
            ) % 1000000;

            $calculatedCode = str_pad($otp, 6, '0', STR_PAD_LEFT);

            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * បង្កើត QR Code URL ថ្មីដោយប្រើប្រាស់សេវាកម្មមានស្ថេរភាពខ្ពស់ (api.qrserver.com)
     */
    public static function getQRUrl($username, $secret, $issuer = 'KhmerPaymentTracker') {
        $encodedUsername = rawurlencode($username);
        $encodedIssuer = rawurlencode($issuer);
        
        // ទម្រង់ស្តង់ដារ OTPAuth URL
        $otpauthUrl = "otpauth://totp/{$encodedIssuer}:{$encodedUsername}?secret={$secret}&issuer={$encodedIssuer}";
        
        // ប្រើប្រាស់ QR Server API ដែលមានស្ថេរភាព និងលឿនជាង Google Charts API ចាស់
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauthUrl);
    }
}
?>