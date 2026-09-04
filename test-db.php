<?php
// test-db.php
require_once 'config.php';

try {
    $db = getSecureDBConnection();
    if ($db) {
        echo "<div style='font-family: sans-serif; padding: 20px; text-align: center;'>";
        echo "<h2 style='color: #10b981;'>✅ ការតភ្ជាប់ Database ទទួលបានជោគជ័យឥតខ្ចោះ!</h2>";
        echo "<p style='color: #4b5563;'>ឯកសារ config.php របស់លោកអ្នកដំណើរការត្រូវគ្នាជាមួយ MySQL រួចរាល់ហើយ។</p>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; padding: 20px;'>";
    echo "<h2 style='color: #ef4444;'>❌ បរាជ័យក្នុងការតភ្ជាប់ទៅកាន់ Database!</h2>";
    echo "<p style='color: #374151; font-weight: bold;'>សារបង្ហាញកំហុសពី MySQL៖</p>";
    echo "<pre style='background: #fee2e2; padding: 15px; border-radius: 6px; color: #991b1b; overflow-x: auto;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p style='color: #4b5563;'>សូមពិនិត្យមើលព័ត៌មានសម្ងាត់នៅក្នុងឯកសារ <strong>config.php</strong> ឡើងវិញ។</p>";
    echo "</div>";
}
?>