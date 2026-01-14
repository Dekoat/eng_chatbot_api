#!/bin/bash
# RMUTP Chatbot API Test Suite
# Test the chatbot API with various scenarios

API_URL="http://localhost/rmutp-chatbot/backend/chatbot.php"
SESSION_ID="test_session_$(date +%s)"

echo "🧪 RMUTP Chatbot API Test Suite"
echo "API URL: $API_URL"
echo "Session ID: $SESSION_ID"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0

# Test function
run_test() {
    local test_name=$1
    local message=$2
    local expected_contains=$3
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo -e "${YELLOW}Test $TOTAL_TESTS: $test_name${NC}"
    echo "Message: $message"
    
    # Make API request
    response=$(curl -s -X POST "$API_URL" \
        -H "Content-Type: application/json" \
        -d "{\"session_id\": \"$SESSION_ID\", \"message\": \"$message\"}")
    
    echo "Response: $response"
    
    # Check if response contains expected text (if provided)
    if [ -n "$expected_contains" ]; then
        if echo "$response" | grep -q "$expected_contains"; then
            echo -e "${GREEN}✅ PASSED${NC}\n"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        else
            echo -e "${RED}❌ FAILED - Expected to contain: $expected_contains${NC}\n"
        fi
    else
        # Just check if we got valid JSON response
        if echo "$response" | grep -q '"answer"'; then
            echo -e "${GREEN}✅ PASSED${NC}\n"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        else
            echo -e "${RED}❌ FAILED - Invalid response${NC}\n"
        fi
    fi
    
    sleep 1
}

# Test 1: Basic FAQ query
run_test \
    "Basic FAQ Query" \
    "มีทุนการศึกษาอะไรบ้าง" \
    "answer"

# Test 2: Staff information query
run_test \
    "Staff Information Query" \
    "ติดต่ออาจารย์ที่ปรึกษาที่ไหน" \
    "answer"

# Test 3: News query
run_test \
    "News Query" \
    "ข่าวสารล่าสุดของคณะ" \
    "answer"

# Test 4: Empty message (should fail gracefully)
run_test \
    "Empty Message" \
    "" \
    "error"

# Test 5: Unknown query
run_test \
    "Unknown Query" \
    "ราคาขายหุ้นวันนี้เท่าไหร่" \
    "answer"

# Test 6: Thai language query with special characters
run_test \
    "Thai Special Characters" \
    "วิศวกรรมศาสตร์ มีหลักสูตรอะไรบ้าง?" \
    "answer"

# Test 7: Long query
run_test \
    "Long Query" \
    "ผมอยากทราบว่าคณะวิศวกรรมศาสตร์ มหาวิทยาลัยเทคโนโลยีราชมงคลพระนคร มีหลักสูตรการเรียนการสอนอะไรบ้าง และมีค่าเทอมเท่าไหร่ รวมถึงมีทุนการศึกษาให้นักศึกษาหรือไม่" \
    "answer"

# Summary
echo "========================================"
echo -e "${YELLOW}Test Summary${NC}"
echo "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$((TOTAL_TESTS - PASSED_TESTS))${NC}"

if [ $PASSED_TESTS -eq $TOTAL_TESTS ]; then
    echo -e "\n${GREEN}🎉 All tests passed!${NC}"
    exit 0
else
    echo -e "\n${RED}⚠️  Some tests failed${NC}"
    exit 1
fi
