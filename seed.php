<?php
// seed.php - បញ្ចូលគណនីតេស្តសាកល្បងសន្តិសុខសម្រាប់ប្រព័ន្ធ Maker-Checker
require_once 'config.php';

// កំណត់ទិន្នន័យតភ្ជាប់ទៅកាន់ MySQL របស់ XAMPP លំនាំដើម
$host = "localhost";
$db_name = "payment_tracker";
$username = "root";
$password = "";

try {
    $db = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // សម្អាតទិន្នន័យចាស់ក្នុងតារាង approvals និង users ដើម្បីកុំឱ្យជាន់គ្នាពេលតេស្ត
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("TRUNCATE TABLE admin_approvals;");
    $db->exec("TRUNCATE TABLE users;");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // បង្កើត BCRYPT Hash នៃពាក្យសម្ងាត់ "admin123" ឱ្យស្របតាម api-v2.php 
    $password_hash = password_hash('admin123', PASSWORD_BCRYPT);

    // ១. បញ្ចូលគណនី Checker (Super Admin - ID: 1)
    $stmt1 = $db->prepare("INSERT INTO users (id, username, password_hash, role, status) VALUES (1, 'superadmin_cambodia', ?, 'super_admin', 'active')");
    $stmt1->execute([$password_hash]);

    // ២. បញ្ចូលគណនី Maker (Admin - ID: 2)
    $stmt2 = $db->prepare("INSERT INTO users (id, username, password_hash, role, status) VALUES (2, 'admin_sophors', ?, 'admin', 'active')");
    $stmt2->execute([$password_hash]);

    // ៣. បញ្ចូលគណនី User ធម្មតា (ID: 3) សម្រាប់យកទៅតេស្តតម្លើងសិទ្ធិ
    $stmt3 = $db->prepare("INSERT INTO users (id, username, password_hash, role, status) VALUES (3, 'khmer_user1', ?, 'user', 'active')");
    $stmt3->execute([$password_hash]);

    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: auto; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9;'>";
    echo "<h2 style='color: #10b981;'>🎉 រៀបចំទិន្នន័យតេស្ត (Database Seeding) ជោគជ័យ!</h2>";
    echo "<p>គណនីតេស្តសន្តិសុខខាងក្រោមត្រូវបានបង្កើតឡើងក្នុង Database រួចរាល់៖</p>";
    echo "<ul>";
    echo "<li><strong>superadmin_cambodia</strong> (Password: <code>admin123</code>, តួនាទី: Super Admin - សម្រាប់ធ្វើជា Checker)</li>";
    echo "<li><strong>admin_sophors</strong> (Password: <code>admin123</code>, តួនាទី: Admin - សម្រាប់ធ្វើជា Maker)</li>";
    echo "<li><strong>khmer_user1</strong> (Password: <code>admin123</code>, តួនាទី: User - សម្រាប់សាកល្បងតម្លើងសិទ្ធិ)</li>";
    echo "</ul>";
    echo "<p style='color: #4b5563;'>👉 ឥឡូវនេះ លោកអ្នកអាចត្រឡប់ទៅកាន់ Git Bash ក្នុង VS Code រួចរត់ <code>./test-flow.sh</code> ម្តងទៀត។</p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<h2 style='color:red; font-family: sans-serif;'>❌ បរាជ័យក្នុងការតភ្ជាប់ Database៖ " . $e->getMessage() . "</h2>";
}