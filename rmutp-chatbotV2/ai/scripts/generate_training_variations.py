#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
สร้าง Training Variations จาก FAQ Database
แปลง FAQ 107 รายการ → Training Data 300-500 examples
"""

import mysql.connector
import csv
import random
from datetime import datetime

class TrainingDataGenerator:
    def __init__(self):
        # Mapping category → intent (ตรงกับ intents.json)
        self.category_to_intent = {
            "admission": "ask_admission",
            "tuition": "ask_tuition",
            "loan": "ask_loan",
            "department": "ask_department",
            "program": "ask_department",  # หลักสูตร → ask_department
            "facility": "ask_facility",
            "activity": "ask_news",  # กิจกรรม → ask_news
            "registration": "ask_grade",
            "qualification": "ask_admission",  # คุณสมบัติ → ask_admission
            "contact": "ask_contact"
        }
        
        # Variation patterns สำหรับแต่ละ intent
        self.variation_patterns = {
            "prefix": [
                "", "อยากทราบว่า", "ช่วยบอกหน่อยว่า", "สงสัยว่า", 
                "อยากถาม", "รบกวนถามว่า", "ขอถามว่า", "ต้องการทราบว่า"
            ],
            "suffix": [
                "", "ครับ", "ค่ะ", "หน่อยครับ", "หน่อยค่ะ", "บ้าง", 
                "ได้ไหม", "ได้ไหมครับ", "ได้ไหมค่ะ", "มั้ย"
            ],
            "question_words": {
                "อะไร": ["อะไร", "อะไรบ้าง", "ยังไง", "อย่างไร"],
                "ที่ไหน": ["ที่ไหน", "ตรงไหน", "แห่งไหน"],
                "เมื่อไหร่": ["เมื่อไหร่", "เมื่อไหร่ครับ", "ช่วงไหน", "เวลาไหน"],
                "ยังไง": ["ยังไง", "อย่างไร", "วิธีไหน", "ทำไง"],
                "เท่าไหร่": ["เท่าไหร่", "เท่าไหร่ครับ", "กี่บาท", "ราคาเท่าไหร่"]
            }
        }
        
        self.conn = None
        self.faqs = []
        
    def connect_db(self):
        """เชื่อมต่อ database"""
        try:
            self.conn = mysql.connector.connect(
                host="localhost",
                user="root",
                password="",
                database="eng_chatbot",
                charset='utf8mb4',
                collation='utf8mb4_unicode_ci'
            )
            print("✅ เชื่อมต่อ database สำเร็จ")
            return True
        except Exception as e:
            print(f"❌ ไม่สามารถเชื่อมต่อ database: {e}")
            return False
    
    def load_faqs(self):
        """โหลด FAQ จาก database"""
        cursor = self.conn.cursor(dictionary=True)
        query = "SELECT id, question, category FROM faq ORDER BY id"
        cursor.execute(query)
        self.faqs = cursor.fetchall()
        cursor.close()
        print(f"📦 โหลด {len(self.faqs)} FAQ จาก database")
        return len(self.faqs)
    
    def generate_variations(self, question, num_variations=3):
        """สร้าง variations จากคำถามเดิม"""
        variations = [question]  # เก็บคำถามเดิมไว้ด้วย
        
        # Variation 1: เพิ่ม prefix
        if random.random() > 0.3:
            prefix = random.choice(self.variation_patterns["prefix"])
            if prefix:
                var = f"{prefix}{question}"
                if var not in variations:
                    variations.append(var)
        
        # Variation 2: เพิ่ม suffix
        if random.random() > 0.3:
            suffix = random.choice(self.variation_patterns["suffix"])
            if suffix:
                var = f"{question}{suffix}"
                if var not in variations:
                    variations.append(var)
        
        # Variation 3: เปลี่ยนคำถาม
        for original, replacements in self.variation_patterns["question_words"].items():
            if original in question:
                replacement = random.choice(replacements)
                var = question.replace(original, replacement)
                if var not in variations and var != question:
                    variations.append(var)
                    break
        
        # Variation 4: ทั้ง prefix และ suffix
        if len(variations) < num_variations + 1:
            prefix = random.choice([p for p in self.variation_patterns["prefix"] if p])
            suffix = random.choice([s for s in self.variation_patterns["suffix"] if s])
            var = f"{prefix}{question}{suffix}"
            if var not in variations:
                variations.append(var)
        
        # ตัดให้เหลือจำนวนที่ต้องการ
        return variations[:num_variations + 1]  # +1 เพราะมีต้นฉบับด้วย
    
    def generate_training_data(self, variations_per_faq=5):
        """สร้าง training data จาก FAQ"""
        print(f"\n🔄 กำลังสร้าง training variations...")
        print(f"   - จำนวน FAQ: {len(self.faqs)}")
        print(f"   - Variations ต่อ FAQ: {variations_per_faq}")
        print(f"   - เป้าหมาย: {len(self.faqs) * variations_per_faq} examples\n")
        
        training_data = []
        category_count = {}
        
        for i, faq in enumerate(self.faqs, 1):
            question = faq['question']
            category = faq['category']
            intent = self.category_to_intent.get(category, "other")
            
            # สร้าง variations
            variations = self.generate_variations(question, variations_per_faq - 1)
            
            # เพิ่มเข้า training data
            for var in variations:
                training_data.append({
                    'text': var,
                    'intent': intent
                })
            
            # นับจำนวนแต่ละ category
            category_count[intent] = category_count.get(intent, 0) + len(variations)
            
            if i % 20 == 0:
                print(f"   ✅ ประมวลผลแล้ว {i}/{len(self.faqs)} FAQ...")
        
        print(f"\n✅ สร้าง training data เสร็จสิ้น: {len(training_data)} examples")
        print(f"\n📊 แบ่งตาม Intent:")
        for intent, count in sorted(category_count.items(), key=lambda x: x[1], reverse=True):
            print(f"   - {intent}: {count} examples")
        
        return training_data
    
    def save_to_csv(self, training_data, filename="ai/data/training_data.csv"):
        """บันทึก training data เป็น CSV"""
        print(f"\n💾 บันทึกไฟล์: {filename}")
        
        # เปลี่ยน 'text' เป็น 'question' เพื่อให้ตรงกับ train_model.py
        formatted_data = [{'question': row['text'], 'intent': row['intent']} for row in training_data]
        
        with open(filename, 'w', encoding='utf-8-sig', newline='') as f:
            writer = csv.DictWriter(f, fieldnames=['question', 'intent'])
            writer.writeheader()
            writer.writerows(formatted_data)
        
        print(f"✅ บันทึกสำเร็จ: {len(training_data)} rows")
        
        # สำรอง backup
        backup_file = filename.replace('.csv', f'_backup_{datetime.now().strftime("%Y%m%d_%H%M%S")}.csv')
        with open(backup_file, 'w', encoding='utf-8-sig', newline='') as f:
            writer = csv.DictWriter(f, fieldnames=['question', 'intent'])
            writer.writeheader()
            writer.writerows(formatted_data)
        print(f"💾 สำรอง backup: {backup_file}")
    
    def close(self):
        """ปิดการเชื่อมต่อ"""
        if self.conn:
            self.conn.close()
            print("\n✅ ปิดการเชื่อมต่อ database")

def main():
    print("="*80)
    print("🚀 Training Data Generator - สร้าง Training Variations จาก FAQ")
    print("="*80)
    
    generator = TrainingDataGenerator()
    
    # เชื่อมต่อและโหลดข้อมูล
    if not generator.connect_db():
        return
    
    if generator.load_faqs() == 0:
        print("⚠️  ไม่พบ FAQ ใน database")
        generator.close()
        return
    
    # สร้าง training data (เพิ่มเป็น 5 variations)
    training_data = generator.generate_training_data(variations_per_faq=5)
    
    # บันทึกไฟล์
    generator.save_to_csv(training_data)
    
    # ปิดการเชื่อมต่อ
    generator.close()
    
    print("\n" + "="*80)
    print("✅ เสร็จสิ้น! พร้อม re-train model")
    print("   คำสั่งถัดไป: python ai/scripts/train_model.py")
    print("="*80)

if __name__ == "__main__":
    main()
