import re

KEYWORD_RULES = {
    'ask_loan': [r'กยศ\.?', r'กรอ\.?', r'กู้เงิน', r'กู้ได้', r'ทุนการศึกษา', r'คืนเงิน.*กยศ', r'สมัคร.*กยศ', r'เงื่อนไข.*กู้', r'รายได้ครอบครัว'],
    'ask_tuition': [r'ค่าเทอม', r'ค่าธรรมเนียม', r'จ่ายเงิน', r'ชำระ.*เงิน', r'ผ่อน.*ค่า', r'ค่าหนังสือ', r'ค่าใช้จ่าย', r'ส่วนลด.*เทอม'],
    'ask_admission': [r'TCAS', r'รับสมัคร', r'สมัครเข้า', r'สอบเข้า', r'Portfolio', r'GPAX', r'GAT', r'PAT', r'รอบ.*สมัคร', r'สอบ.*ต้อง', r'คะแนน.*สมัคร'],
    'ask_department': [r'สาขา', r'ภาควิชา', r'อาจารย์', r'คณะ', r'มีสาขา'],
}

def check_keywords(question):
    """Check if question matches keyword rules"""
    question_lower = question.lower()
    for intent, patterns in KEYWORD_RULES.items():
        for pattern in patterns:
            if re.search(pattern, question_lower):
                return (intent, 0.95)
    return (None, 0)

# Test
test_questions = [
    'TCAS คืออะไร',
    'กยศ. คืออะไร',
    'ค่าเทอมเท่าไหร่',
    'สมัคร กยศ. ทำยังไง',
    'กู้ได้เท่าไหร่'
]

for q in test_questions:
    intent, conf = check_keywords(q)
    print(f"{q:30s} -> {intent if intent else 'NO MATCH':15s} ({conf:.2%})")
