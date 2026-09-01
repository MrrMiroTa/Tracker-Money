# សៀវភៅណែនាំអំពីការរួមបញ្ចូលប្រព័ន្ធសន្តិសុខ និងគ្រប់គ្រងសិទ្ធិ Admin
## Integration & Implementation Guide: Secure User Authorization

ឯកសារនេះណែនាំពីរបៀបរួមបញ្ចូលឯកសារ `schema-v2.sql` និង `api-v2.php` ចូលទៅក្នុងគម្រោង **System-Track** ដើម្បីដំឡើងប្រព័ន្ធគ្រប់គ្រងសិទ្ធិ (RBAC) និងយន្តការអនុម័តពីរជំហាន (Dual Control / Maker-Checker) ប្រកបដោយសុវត្ថិភាពខ្ពស់បំផុត។

---

### ១. ការរៀបចំ Database (Database Setup)
ដើម្បីចាប់ផ្តើម អ្នកត្រូវដំណើរការកូដ SQL នៅក្នុងឯកសារ `schema-v2.sql` ទៅកាន់កម្មវិធីគ្រប់គ្រង MySQL database របស់អ្នក (ដូចជា phpMyAdmin)។
* វានឹងបង្កើតតារាងថ្មីៗចំនួនបី៖
  1. `users` (រួមបញ្ចូលទាំងជួរឈរ `role` និង `status`)
  2. `audit_logs` (សម្រាប់កត់ត្រារាល់សកម្មភាព Admin ទាំងអស់)
  3. `admin_approvals` (សម្រាប់ដំណើរការស្នើសុំ និងអនុម័តគណនី Admin ថ្មី)
* គណនីគំរូសម្រាប់សាកល្បង៖
  * **Super Admin**: `superadmin_cambodia` (ពាក្យសម្ងាត់៖ `admin123`)
  * **Admin**: `admin_sophors` (ពាក្យសម្ងាត់៖ `admin123`)
  * **User**: `khmer_user1` (ពាក្យសម្ងាត់៖ `user123`)

---

### ២. ការការពារ API ជាមួយការត្រួតពិនិត្យសិទ្ធិ (Protecting API Actions)
នៅក្នុងឯកសារ `api-v2.php` គោលការណ៍ **Least Privilege (សិទ្ធិអប្បបរមា)** ត្រូវបានអនុវត្តដោយបែងចែកសិទ្ធិយ៉ាងលម្អិត៖
```php
$role_permissions = [
    'super_admin' => [
        'view_dashboard', 'add_transaction', 'edit_transaction', 'delete_transaction',
        'view_users', 'add_admin_request', 'approve_admin', 'delete_user', 'view_audit_logs'
    ],
    'admin' => [
        'view_dashboard', 'add_transaction', 'edit_transaction',
        'view_users', 'add_admin_request'
    ],
    'user' => [
        'view_dashboard', 'add_transaction'
    ]
];
```

* **របៀបប្រើប្រាស់មុខងារត្រួតពិនិត្យសិទ្ធិ (How to Enforce)**៖
  មុននឹងអនុញ្ញាតឱ្យប្រតិបត្តិការសំខាន់ៗណាមួយដំណើរការ អ្នកគ្រាន់តែហៅមុខងារ `checkPermission('permission_name')`៖
  ```php
  // ឧទាហរណ៍៖ ការលុបប្រតិបត្តិការហិរញ្ញវត្ថុ (តម្រូវឱ្យមានសិទ្ធិ delete_transaction ដែលមានតែ Super Admin ប៉ុណ្ណោះ)
  if ($action === 'delete_transaction') {
      checkPermission('delete_transaction');
      // បន្តទៅកាន់កូដលុបទិន្នន័យ...
  }
  ```

---

### ៣. ដំណើរការអនុម័តទ្វេភាគី សម្រាប់ការបន្ថែម Admin ថ្មី (Maker-Checker Workflow)
ដើម្បីការពារការបង្កើតគណនី Admin ដោយឯកតោភាគី ឬករណីមានគេលួចគណនី Admin ធម្មតារួចបង្កើតគណនី Admin ថ្មីផ្សេងទៀត៖

```
[Admin ធម្មតា (Maker)] ──> បញ្ជូនសំណើ (Request Promotion) ──> រង់ចាំក្នុង Database (Pending)
                                                                 │
[Super Admin (Checker)] <── ពិនិត្យឃើញសកម្មភាព និងអនុម័ត ◄───────┘
```

1. **ជំហានទី ១ (Maker)**៖ Admin ធម្មតា (ឧ. `admin_sophors`) ហៅ API `/api-v2.php?action=request_admin` ដើម្បីស្នើសុំតម្លើងសិទ្ធិអ្នកប្រើប្រាស់ធម្មតាម្នាក់ឱ្យក្លាយជា Admin។ សំណើនេះនឹងត្រូវកត់ត្រាចូលក្នុង `admin_approvals` ជាមួយស្ថានភាព `pending`។
2. **ជំហានទី ២ (Checker)**៖ Super Admin (ឧ. `superadmin_cambodia`) ពិនិត្យមើលបញ្ជីសំណើតាមរយៈ API `/api-v2.php?action=approvals`។
3. **ជំហានទី ៣ (Approval)**៖ Super Admin ហៅ API `/api-v2.php?action=approve_admin` ដើម្បីសម្រេចចិត្ត (អនុម័ត ឬបដិសេធ)។
   * **ច្បាប់សន្តិសុខដាច់ខាត (Separation of Duties)**៖ ប្រព័ន្ធនឹងបដិសេធភ្លាមៗ ប្រសិនបើ Admin ដែលជាអ្នកស្នើសុំ (Maker) ព្យាយាមអនុម័តសំណើរបស់ខ្លួនឯង។

---

### ៤. កំណត់ហេតុសវនកម្ម (Audit Trail Logging)
រាល់សកម្មភាពសំខាន់ៗរបស់ Admin ទាំងអស់ (ការចូលប្រើប្រាស់ ការស្នើសុំបន្ថែម Admin ការអនុម័ត ឬបដិសេធ) នឹងត្រូវបានកត់ត្រាទុកយ៉ាងម៉ត់ចត់នៅក្នុងតារាង `audit_logs` ជាមួយព័ត៌មានរួមមាន៖
* **Operator ID**: អត្តសញ្ញាណអ្នកធ្វើសកម្មភាព
* **Action**: ប្រភេទសកម្មភាព (ឧ. `APPROVE_ADD_ADMIN`)
* **IP Address**: អាសយដ្ឋាន IP របស់ឧបករណ៍ដែលបានស្នើសុំ
* **Timestamp**: ពេលវេលាជាក់លាក់នៃប្រតិបត្តិការ

ការកត់ត្រានេះធានាបាននូវការត្រួតពិនិត្យឡើងវិញ និងការការពារហានិភ័យបានទាន់ពេលវេលាស្របតាមស្តង់ដារសន្តិសុខ។
