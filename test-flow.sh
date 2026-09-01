#!/bin/bash
# Automated Test Script for Maker-Checker Dual Authorization Flow (api-v2.php)
# This script simulates the entire lifecycle of an Admin promotion request and verifies security constraints.

API_URL="http://localhost/Tracker/api-v2.php" # កែសម្រួល URL ស្របតាម Server របស់អ្នក
COOKIE_JAR="cookies.txt"

# Code ពណ៌សម្រាប់បង្ហាញព័ត៌មានលម្អិត
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=====================================================${NC}"
echo -e "${YELLOW}   ចាប់ផ្តើមដំណើរការការតេស្តស្វ័យប្រវត្តយន្តការ Maker-Checker   ${NC}"
echo -e "${YELLOW}=====================================================${NC}\n"

# សម្អាត File Cookie ចាស់បើមាន
rm -f $COOKIE_JAR

# ១. ចូលប្រើប្រាស់ជា Maker (admin_sophors)
echo -e "${CYAN}[តេស្តទី ១]៖ ចូលប្រើប្រាស់ជា Maker (admin_sophors) ${NC}"
LOGIN_MAKER=$(curl -s -X POST -H "Content-Type: application/json" \
  -d '{"username":"admin_sophors", "password":"admin123"}' \
  -c $COOKIE_JAR "$API_URL?action=login")

if [[ $LOGIN_MAKER == *"success"* ]]; then
    echo -e "  └─ ${GREEN}ជោគជ័យ៖ Login ជោគជ័យក្នុងតួនាទី Admin (Maker)${NC}"
else
    echo -e "  └─ ${RED}បរាជ័យ៖ មិនអាចចូលប្រើប្រាស់បានទេ${NC}"
    echo "  Response: $LOGIN_MAKER"
    exit 1
fi
echo ""

# ២. ផ្ញើសំណើសុំតម្លើងសិទ្ធិអ្នកប្រើប្រាស់ ID 3 (Maker Action)
echo -e "${CYAN}[តេស្តទី ២]៖ បញ្ជូនសំណើសុំតម្លើងសិទ្ធិអ្នកប្រើប្រាស់ ID 3 ឱ្យក្លាយជា Admin ${NC}"
REQ_PROMO=$(curl -s -X POST -H "Content-Type: application/json" \
  -d '{"target_user_id": 3}' \
  -b $COOKIE_JAR -c $COOKIE_JAR "$API_URL?action=request_admin")

if [[ $REQ_PROMO == *"success"* ]]; then
    echo -e "  └─ ${GREEN}ជោគជ័យ៖ សំណើត្រូវបានបញ្ជូនទៅកាន់បញ្ជីរង់ចាំ (Pending Status)${NC}"
else
    echo -e "  └─ ${RED}បរាជ័យ៖ មិនអាចបង្កើតសំណើបានទេ${NC}"
    echo "  Response: $REQ_PROMO"
    exit 1
fi
echo ""

# ៣. តេស្តសន្តិសុខ៖ សាកល្បងអនុម័តសំណើខ្លួនឯង (Separation of Duties Validation)
echo -e "${CYAN}[តេស្តទី ៣]៖ សាកល្បងអនុម័តសំណើខ្លួនឯង (តេស្តច្បាប់សន្តិសុខ Separation of Duties) ${NC}"
SELF_APP_RESPONSE=$(curl -s -b $COOKIE_JAR -c $COOKIE_JAR \
  -X POST -H "Content-Type: application/json" \
  -d '{"request_id": 1, "decision": "approve"}' \
  "$API_URL?action=approve_admin")

# ពិនិត្យមើលថាតើប្រព័ន្ធរារាំង (Error 403) ដែរឬទេ
if [[ $SELF_APP_RESPONSE == *"Security Violation"* ]] || [[ $SELF_APP_RESPONSE == *"Forbidden"* ]]; then
    echo -e "  └─ ${GREEN}ជោគជ័យ (ប្រព័ន្ធការពារបានល្អ)៖ ប្រព័ន្ធបានរារាំង និងបដិសេធសំណើដោយជោគជ័យ!${NC}"
    echo -e "  └─ Response ពីប្រព័ន្ធ៖ ${YELLOW}$SELF_APP_RESPONSE${NC}"
else
    echo -e "  └─ ${RED}បរាជ័យ (ចន្លោះប្រហោងសន្តិសុខ)៖ ប្រព័ន្ធអនុញ្ញាតឱ្យអនុម័តសំណើខ្លួនឯងបាន!${NC}"
    echo "  Response: $SELF_APP_RESPONSE"
    exit 1
fi
echo ""

# ៤. ចាកចេញពីគណនី Maker
echo -n "៤. កំពុងចាកចេញពីគណនី Maker... "
curl -s -X POST -b $COOKIE_JAR -c $COOKIE_JAR "$API_URL?action=logout" > /dev/null
echo -e "${GREEN}រួចរាល់${NC}\n"

# ៥. ចូលប្រើប្រាស់ជា Checker (superadmin_cambodia)
echo -e "${CYAN}[តេស្តទី ៤]៖ ចូលប្រើប្រាស់ជា Checker (superadmin_cambodia) ${NC}"
LOGIN_CHECKER=$(curl -s -X POST -H "Content-Type: application/json" \
  -d '{"username":"superadmin_cambodia", "password":"admin123"}' \
  -c $COOKIE_JAR "$API_URL?action=login")

if [[ $LOGIN_CHECKER == *"success"* ]]; then
    echo -e "  └─ ${GREEN}ជោគជ័យ៖ Login ជោគជ័យក្នុងតួនាទី Super Admin (Checker)${NC}"
else
    echo -e "  └─ ${RED}បរាជ័យ៖ មិនអាចចូលប្រើប្រាស់បានទេ${NC}"
    echo "  Response: $LOGIN_CHECKER"
    exit 1
fi
echo ""

# ៦. អនុម័តសំណើ ID 1 (Checker Action)
echo -e "${CYAN}[តេស្តទី ៥]៖ អនុម័តសំណើ ID 1 ដោយ Checker ម្នាក់ផ្សេងទៀត ${NC}"
APP_PROMO=$(curl -s -X POST -H "Content-Type: application/json" \
  -d '{"request_id": 1, "decision": "approve"}' \
  -b $COOKIE_JAR -c $COOKIE_JAR "$API_URL?action=approve_admin")

if [[ $APP_PROMO == *"success"* ]]; then
    echo -e "  └─ ${GREEN}ជោគជ័យ៖ សំណើត្រូវបានអនុម័តរួចរាល់ ហើយអ្នកប្រើប្រាស់ត្រូវបានតម្លើងជា Admin${NC}"
else
    echo -e "  └─ ${RED}បរាជ័យ៖ មិនអាចអនុម័តបានទេ${NC}"
    echo "  Response: $APP_PROMO"
    exit 1
fi
echo ""

# ៧. ពិនិត្យកំណត់ហេតុសវនកម្ម (Audit Logs)
echo -e "${CYAN}[តេស្តទី ៦]៖ ពិនិត្យមើលកំណត់ហេតុសវនកម្មសន្តិសុខ (Audit Logs) ${NC}"
AUDIT_LOGS=$(curl -s -X GET -b $COOKIE_JAR "$API_URL?action=audit_logs")

if [[ $AUDIT_LOGS == *"success"* ]] && [[ $AUDIT_LOGS == *"APPROVE_ADD_ADMIN"* ]]; then
    echo -e "  └─ ${GREEN}ជោគជ័យ៖ រកឃើញកំណត់ត្រាសន្តិសុខការអនុម័ត Admin ថ្មីនៅក្នុងកំណត់ហេតុសវនកម្ម (Audit logs)${NC}"
else
    echo -e "  └─ ${RED}បរាជ័យ៖ មិនមានកំណត់ត្រាសវនកម្ម ឬទាញយកមិនបាន${NC}"
    echo "  Response: $AUDIT_LOGS"
    exit 1
fi

# សម្អាត File Cookie បណ្តោះអាសន្ន
rm -f $COOKIE_JAR

echo -e "\n${GREEN}=====================================================${NC}"
echo -e "${GREEN}   ការតេស្តស្វ័យប្រវត្តទាំងអស់ត្រូវបានបញ្ចប់ដោយជោគជ័យ ១០០%!   ${NC}"
echo -e "${GREEN}=====================================================${NC}"
