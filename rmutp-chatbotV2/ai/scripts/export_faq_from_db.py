#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Export FAQ from Database to Training Data
ดึงข้อมูล FAQ จากฐานข้อมูลและสร้าง training data
"""

import mysql.connector
import pandas as pd
import os
from datetime import datetime

# การตั้งค่าฐานข้อมูล
DB_CONFIG = {
    'host': 'localhost',
    'port': 3306,
    'database': 'eng_chatbot',
    'user': 'root',
    'password': '',
    'charset': 'utf8mb4'
}

def normalize_intent(category):
    """
    แมป category จากฐานข้อมูลไปยัง intent หลักที่ชัดเจน
    ลดจาก 57 categories ลงเหลือ 10 หมวดหมู่หลัก
    """
    # แมปทุก category ไปยัง intent หลัก
    intent_mapping = {
        # หลักสูตรและสาขา
        'program': 'program',
        'curriculum': 'program',
        'Curriculum': 'program',
        'Courses': 'program',
        'course': 'program',
        'Course Description': 'program',
        'หลักสูตรและวิชาเรียน': 'program',
        'โครงสร้างหลักสูตร': 'program',
        'รายวิชาและเนื้อหา': 'program',
        'ข้อมูลพื้นฐานสาขา': 'program',
        
        # การรับสมัคร
        'admission': 'admission',
        'Admission': 'admission',
        'การรับสมัคร': 'admission',
        'การรับสมัครและคุณสมบัติ': 'admission',
        
        # อาชีพและการทำงาน
        'career': 'career',
        'Careers': 'career',
        'อาชีพและใบอนุญาต': 'career',
        
        # ค่าเทอมและค่าใช้จ่าย
        'tuition': 'tuition',
        'fee': 'tuition',
        'Fees': 'tuition',
        'ค่าธรรมเนียม': 'tuition',
        
        # ทุนการศึกษาและกู้ยืม
        'loan': 'loan',
        'scholarship': 'loan',
        'Scholarship': 'loan',
        
        # ข้อมูลติดต่อ
        'contact': 'contact',
        'location': 'contact',
        
        # สิ่งอำนวยความสะดวก
        'facilities': 'facilities',
        'Facilities': 'facilities',
        'facility': 'facilities',
        'library': 'facilities',
        'sports': 'facilities',
        
        # กิจกรรมและข่าวสาร
        'activities': 'activities',
        'ข่าวสารและกิจกรรม': 'activities',
        
        # งานวิจัยและโครงงาน
        'research': 'research',
        'Research': 'research',
        'งานวิจัยและผลงาน': 'research',
        'โครงงานและงานวิจัย': 'research',
        
        # ข้อมูลทั่วไป
        'information': 'general',
        'general': 'general',
        'General Info': 'general',
        'about': 'general',
        'history': 'general',
        'accreditation': 'general',
        'ข้อมูลทั่วไป': 'general',
        'faq': 'general',
        'Faculty': 'general',
        'staff': 'general',
        'document': 'general',
        'cooperation': 'general',
        'partnership': 'general',
        'Co-op': 'general',
        
        # การจบการศึกษา
        'Graduation': 'graduation',
        'การวัดผลและจบการศึกษา': 'graduation',
        
        # กฎระเบียบ
        'Regulations': 'regulations',
        'Student Support': 'regulations',
    }
    
    # คืนค่า intent ที่แมปแล้ว ถ้าไม่พบให้ใช้ 'general'
    return intent_mapping.get(category, 'general')

def connect_db():
    """เชื่อมต่อฐานข้อมูล"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        print("✅ เชื่อมต่อฐานข้อมูลสำเร็จ")
        return conn
    except mysql.connector.Error as err:
        print(f"❌ ไม่สามารถเชื่อมต่อฐานข้อมูล: {err}")
        return None

def fetch_faq_data(conn):
    """ดึงข้อมูล FAQ จากฐานข้อมูล"""
    query = """
        SELECT id, question, answer, category, keywords
        FROM faq
        ORDER BY category, id
    """
    
    try:
        df = pd.read_sql(query, conn)
        print(f"✅ ดึงข้อมูล FAQ: {len(df)} รายการ")
        return df
    except Exception as e:
        print(f"❌ ไม่สามารถดึงข้อมูล: {e}")
        return None

def generate_question_variations(question, category):
    """
    สร้างรูปแบบคำถามที่หลากหลายจากคำถามหลัก
    """
    variations = [question]  # เริ่มต้นด้วยคำถามต้นฉบับ
    
    # รูปแบบคำถามสำหรับแต่ละ category
    if category == 'program':
        # คำถามเกี่ยวกับหลักสูตร
        if 'หลักสูตร' in question or 'อส.บ' in question or 'วิศวกรรม' in question:
            variations.extend([
                question + ' คืออะไร',
                question + ' เรียนอะไร',
                'บอกเกี่ยวกับ' + question,
            ])
        if 'เรียนกี่ปี' in question or 'ระยะเวลา' in question:
            variations.extend([
                'เรียนนานแค่ไหน',
                'ใช้เวลากี่ปี',
                'เรียนกี่ปีจบ',
                'ระยะเวลาเรียน'
            ])
        if 'เรียน' in question and 'วิชา' in question:
            variations.extend([
                'เรียนอะไร',
                'เรียนวิชาอะไรบ้าง',
                'มีวิชาอะไร',
            ])
            
    elif category == 'admission':
        # คำถามเกี่ยวกับการสมัคร/คุณสมบัติ
        variations.extend([
            'สมัคร',
            'สมัครได้ไหม',
            'สมัครยังไง',
            'วิธีสมัคร',
            'รับสมัคร',
            'การรับสมัคร',
            'TCAS',
            'รับตรง',
            'คุณสมบัติ',
            'รับสมัคร มทร',
        ])
            
    elif category == 'tuition':
        # คำถามเกี่ยวกับค่าเทอม - เพิ่ม variations เยอะขึ้น
        variations.extend([
            'ค่าเทอม',
            'ค่าเทอมเท่าไหร่',
            'ค่าเทอมเท่าไร',
            'เทอมละเท่าไหร่',
            'ค่าเรียน',
            'ค่าใช้จ่าย',
            'เสียเงินเท่าไหร่',
            'ค่าธรรมเนียม',
            'ค่าลงทะเบียน',
            'ค่าบำรุง',
            'ราคา',
            'ค่าใช้จ่ายในการเรียน',
            'ค่าใช้จ่ายต่อเทอม',
            'จ่ายค่าเทอม',
            'แพงไหม',
            'ถูกไหม',
            'เท่าไหร่',
            'ราคาเท่าไร',
            'ค่าใช้จ่ายเท่าไหร่',
            'เทอมละเท่าไร',
            'ค่าเรียนเท่าไหร่',
        ])
        
    elif category == 'loan':
        # คำถามเกี่ยวกับกู้ยืมและทุนการศึกษา - เพิ่ม variations เยอะขึ้น
        variations.extend([
            'กู้เงิน กยศ',
            'กู้ กยศ',
            'กยศ คืออะไร',
            'ขอกู้ได้ไหม',
            'กู้ยืมเงิน',
            'ทุนการศึกษา',
            'ขอทุน',
            'มีทุนไหม',
            'กยศ. ได้ไหม',
            'กู้เงินเรียน',
        ])
        
    elif category == 'career':
        # คำถามเกี่ยวกับอาชีพ
        variations.extend([
            'จบแล้วทำงานอะไร',
            'ทำงานอะไรได้บ้าง',
            'อาชีพหลังจบ',
            'ทำงานด้านไหน',
            'มีงานอะไรบ้าง'
        ])
        
    elif category == 'contact':
        # คำถามเกี่ยวกับติดต่อ
        variations.extend([
            'ติดต่อยังไง',
            'เบอร์โทร',
            'อีเมล',
            'ที่อยู่',
            'ติดต่อสาขา'
        ])
        
    elif category == 'facilities':
        # คำถามเกี่ยวกับสิ่งอำนวยความสะดวก
        variations.extend([
            'มีห้องแล็บไหม',
            'มีห้องสมุดไหม',
            'สนามกีฬา',
            'อุปกรณ์การเรียน'
        ])
        
    elif category == 'general':
        # คำถามเกี่ยวกับข้อมูลทั่วไป
        if 'อาจารย์' in question or 'staff' in question.lower():
            variations.extend([
                'อาจารย์ประจำสาขา',
                'คณาจารย์',
                'อาจารย์ผู้สอน',
            ])
    
    return variations

def create_training_data(df):
    """สร้าง training data จาก FAQ"""
    training_data = []
    
    for idx, row in df.iterrows():
        question = row['question']
        category = row['category']
        
        # แปลง category เป็น intent หลัก
        normalized_intent = normalize_intent(category)
        
        # สร้าง variations ของคำถาม
        variations = generate_question_variations(question, normalized_intent)
        
        # เพิ่มแต่ละ variation เป็น training example
        for var in variations:
            training_data.append({
                'question': var,
                'intent': normalized_intent,  # ใช้ normalized intent
                'original_faq_id': row['id'],
                'original_category': category  # เก็บ category เดิมไว้อ้างอิง
            })
    
    return pd.DataFrame(training_data)

def save_training_data(df, output_path):
    """บันทึก training data"""
    try:
        df.to_csv(output_path, index=False, encoding='utf-8-sig')
        print(f"✅ บันทึก training data: {output_path}")
        print(f"   จำนวน: {len(df)} examples")
        return True
    except Exception as e:
        print(f"❌ ไม่สามารถบันทึกไฟล์: {e}")
        return False

def main():
    print("=" * 70)
    print("EXPORT FAQ FROM DATABASE TO TRAINING DATA")
    print("=" * 70)
    print()
    
    # เชื่อมต่อฐานข้อมูล
    conn = connect_db()
    if not conn:
        return
    
    try:
        # ดึงข้อมูล FAQ
        print("\n[1/4] ดึงข้อมูล FAQ จากฐานข้อมูล...")
        faq_df = fetch_faq_data(conn)
        
        if faq_df is None or len(faq_df) == 0:
            print("❌ ไม่มีข้อมูล FAQ ในฐานข้อมูล")
            return
        
        # แสดงข้อมูล FAQ
        print("\nFAQ by Category:")
        print(faq_df.groupby('category').size())
        
        # สร้าง training data
        print("\n[2/4] สร้าง training data และ variations...")
        training_df = create_training_data(faq_df)
        
        print(f"\nTraining data created:")
        print(f"  - Original FAQ: {len(faq_df)} รายการ")
        print(f"  - Training examples: {len(training_df)} examples")
        print(f"\nExamples by Intent:")
        print(training_df.groupby('intent').size())
        
        # บันทึก training data
        print("\n[3/4] บันทึก training data...")
        output_dir = os.path.join(os.path.dirname(__file__), '..', 'data')
        output_path = os.path.join(output_dir, 'faq_training_data.csv')
        
        if save_training_data(training_df, output_path):
            # สำรองไฟล์เก่า
            old_file = os.path.join(output_dir, 'training_data.csv')
            if os.path.exists(old_file):
                backup_file = os.path.join(output_dir, f'training_data_backup_{datetime.now().strftime("%Y%m%d_%H%M%S")}.csv')
                os.rename(old_file, backup_file)
                print(f"📦 สำรองไฟล์เก่า: {backup_file}")
            
            # คัดลอกเป็นไฟล์ training_data.csv
            training_df.to_csv(old_file, index=False, encoding='utf-8-sig')
            print(f"✅ อัปเดต training_data.csv")
        
        # แสดงตัวอย่าง
        print("\n[4/4] ตัวอย่าง Training Data:")
        print("-" * 70)
        for intent in training_df['intent'].unique()[:3]:
            print(f"\nIntent: {intent}")
            examples = training_df[training_df['intent'] == intent]['question'].head(3).tolist()
            for i, ex in enumerate(examples, 1):
                print(f"  {i}. {ex}")
        
        print("\n" + "=" * 70)
        print("✅ เสร็จสิ้น!")
        print("=" * 70)
        print("\nNext steps:")
        print("1. ตรวจสอบไฟล์: ai/data/faq_training_data.csv")
        print("2. Train โมเดล: python ai/scripts/train_model.py")
        print("3. ทดสอบโมเดล: python ai/scripts/test_model.py")
        
    finally:
        conn.close()
        print("\n🔌 ปิดการเชื่อมต่อฐานข้อมูล")

if __name__ == '__main__':
    main()
