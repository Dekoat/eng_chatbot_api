"""ทดสอบว่า priority rules ทำงานถูกต้องหรือไม่"""
import requests

test_cases = [
    ('มีทุนให้ไหม', 'ask_loan'),
    ('ถ้าสอบตกต้องทำยังไง', 'ask_grade'),
    ('ดูผลสอบได้แล้วหรือยัง', 'ask_grade'),
    ('ติดต่ออาจารย์ได้ที่ไหน', 'ask_contact'),
    ('เบอร์โทรคณะเท่าไหร่', 'ask_contact'),
    ('มีที่จอดรถไหม', 'ask_facility'),
    ('มีข่าวอะไรบ้างวันนี้', 'ask_news'),
    ('วิศวะไฟฟ้าเรียนอะไรบ้าง', 'ask_department'),
]

print("=" * 80)
print("ทดสอบ Priority-Based Keyword Matching")
print("=" * 80)
print()

correct = 0
for q, expected in test_cases:
    try:
        r = requests.post('http://localhost:5000/predict', json={'question': q}, timeout=2)
        result = r.json()
        intent = result['intent']
        conf = result['confidence']
        method = result['method']
        
        is_correct = (intent == expected)
        if is_correct:
            correct += 1
        
        ok = '✅' if is_correct else '❌'
        print(f'{ok} "{q}"')
        print(f'   → ได้: {intent} ({conf:.1%}) [{method}]')
        if not is_correct:
            print(f'   → ควรได้: {expected}')
        print()
    except requests.exceptions.ConnectionError:
        print('❌ API ไม่ทำงาน! กรุณาเปิด API ก่อน')
        break
    except Exception as e:
        print(f'❌ ERROR: {e}')
        break

print("=" * 80)
print(f"✅ ถูกต้อง: {correct}/{len(test_cases)}")
print(f"🎯 Accuracy: {correct/len(test_cases)*100:.1f}%")
print("=" * 80)
