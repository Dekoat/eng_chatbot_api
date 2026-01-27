import json

# อ่านไฟล์ FAQ
with open('ai/data/faq_person4_sime_history_20260125.json', 'r', encoding='utf-8') as f:
    faqs = json.load(f)

# ตรวจสอบคำถามซ้ำ
questions = {}
duplicates = []

for idx, faq in enumerate(faqs, 1):
    question = faq['question']
    if question in questions:
        duplicates.append({
            'question': question,
            'first_index': questions[question],
            'duplicate_index': idx
        })
    else:
        questions[question] = idx

# แสดงผล
print(f"จำนวนคำถามทั้งหมด: {len(faqs)} คำถาม")
print(f"จำนวนคำถามไม่ซ้ำ: {len(questions)} คำถาม")
print(f"จำนวนคำถามซ้ำ: {len(duplicates)} คำถาม")
print()

if duplicates:
    print("พบคำถามที่ซ้ำกัน:")
    print("="*80)
    for dup in duplicates:
        print(f"\nคำถามซ้ำ:")
        print(f"  คำถาม: {dup['question']}")
        print(f"  ตำแหน่งแรก: รายการที่ {dup['first_index']}")
        print(f"  ตำแหน่งซ้ำ: รายการที่ {dup['duplicate_index']}")
else:
    print("ไม่พบคำถามที่ซ้ำกัน! ทุกคำถามมีความเป็นเอกลักษณ์")
