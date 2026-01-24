#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Import FAQ จากไฟล์ JSON เข้า MySQL Database
"""

import json
import mysql.connector
from datetime import datetime

class FAQImporter:
    def __init__(self, host="localhost", user="root", password="", database="eng_chatbot"):
        self.host = host
        self.user = user
        self.password = password
        self.database = database
        self.conn = None
        
    def connect(self):
        """เชื่อมต่อ database"""
        try:
            self.conn = mysql.connector.connect(
                host=self.host,
                user=self.user,
                password=self.password,
                database=self.database,
                charset='utf8mb4',
                collation='utf8mb4_unicode_ci'
            )
            print(f"✅ เชื่อมต่อ database '{self.database}' สำเร็จ")
            return True
        except Exception as e:
            print(f"❌ ไม่สามารถเชื่อมต่อ database: {e}")
            return False
    
    def check_duplicate(self, question):
        """เช็คว่า FAQ นี้มีในฐานข้อมูลแล้วหรือไม่"""
        cursor = self.conn.cursor()
        query = "SELECT id, question FROM faq WHERE question = %s"
        cursor.execute(query, (question,))
        result = cursor.fetchone()
        cursor.close()
        return result
    
    def insert_faq(self, faq_data):
        """เพิ่ม FAQ เข้า database"""
        cursor = self.conn.cursor()
        
        # เช็คซ้ำก่อน
        duplicate = self.check_duplicate(faq_data['question'])
        if duplicate:
            print(f"   ⚠️  ข้าม (มีอยู่แล้ว): {faq_data['question'][:50]}...")
            cursor.close()
            return False
        
        # Insert ข้อมูล
        query = """
        INSERT INTO faq (question, answer, category, keywords, created_at, updated_at)
        VALUES (%s, %s, %s, %s, NOW(), NOW())
        """
        
        # แปลง keywords เป็น string
        keywords_str = ', '.join(faq_data.get('keywords', [])) if isinstance(faq_data.get('keywords'), list) else ''
        
        values = (
            faq_data['question'],
            faq_data['answer'],
            faq_data.get('category', 'general'),
            keywords_str
        )
        
        try:
            cursor.execute(query, values)
            self.conn.commit()
            faq_id = cursor.lastrowid
            print(f"   ✅ เพิ่มสำเร็จ (ID: {faq_id}): {faq_data['question'][:50]}...")
            cursor.close()
            return True
        except Exception as e:
            print(f"   ❌ เกิดข้อผิดพลาด: {e}")
            cursor.close()
            return False
    
    def import_from_json(self, json_file):
        """Import FAQs จากไฟล์ JSON"""
        print(f"\n📂 อ่านไฟล์: {json_file}")
        
        try:
            with open(json_file, 'r', encoding='utf-8') as f:
                faqs = json.load(f)
            
            print(f"📦 พบ {len(faqs)} FAQs")
            print("\n🔄 เริ่มนำเข้าข้อมูล...\n")
            
            success_count = 0
            skip_count = 0
            error_count = 0
            
            for i, faq in enumerate(faqs, 1):
                print(f"{i}. ", end="")
                result = self.insert_faq(faq)
                
                if result:
                    success_count += 1
                elif result is False and self.check_duplicate(faq['question']):
                    skip_count += 1
                else:
                    error_count += 1
            
            print("\n" + "="*80)
            print("📊 สรุปผลการนำเข้า:")
            print(f"   - เพิ่มสำเร็จ: {success_count} รายการ")
            print(f"   - ข้าม (มีอยู่แล้ว): {skip_count} รายการ")
            print(f"   - ผิดพลาด: {error_count} รายการ")
            print(f"   - รวมทั้งหมด: {len(faqs)} รายการ")
            print("="*80)
            
            return success_count
            
        except FileNotFoundError:
            print(f"❌ ไม่พบไฟล์: {json_file}")
            return 0
        except json.JSONDecodeError:
            print(f"❌ ไฟล์ JSON ผิดรูปแบบ")
            return 0
        except Exception as e:
            print(f"❌ เกิดข้อผิดพลาด: {e}")
            return 0
    
    def get_faq_count(self):
        """นับจำนวน FAQ ทั้งหมดใน database"""
        cursor = self.conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM faq")
        count = cursor.fetchone()[0]
        cursor.close()
        return count
    
    def show_latest_faqs(self, limit=5):
        """แสดง FAQ ล่าสุด"""
        cursor = self.conn.cursor(dictionary=True)
        query = """
        SELECT id, question, category, created_at 
        FROM faq 
        ORDER BY id DESC 
        LIMIT %s
        """
        cursor.execute(query, (limit,))
        results = cursor.fetchall()
        cursor.close()
        
        if results:
            print(f"\n📋 FAQ ล่าสุด {limit} รายการ:")
            for faq in results:
                print(f"   ID: {faq['id']} | {faq['question'][:60]}... | [{faq['category']}]")
    
    def close(self):
        """ปิดการเชื่อมต่อ"""
        if self.conn:
            self.conn.close()
            print("\n✅ ปิดการเชื่อมต่อ database")

def main():
    print("="*80)
    print("📥 FAQ Importer - นำเข้า FAQ เข้า Database")
    print("="*80)
    
    # สร้าง importer
    importer = FAQImporter()
    
    # เชื่อมต่อ database
    if not importer.connect():
        return
    
    # นับจำนวนเดิม
    old_count = importer.get_faq_count()
    print(f"📊 จำนวน FAQ เดิม: {old_count} รายการ")
    
    # Import จากไฟล์
    json_file = "ai/data/faq_batches_all.json"
    success_count = importer.import_from_json(json_file)
    
    # นับจำนวนใหม่
    new_count = importer.get_faq_count()
    print(f"📊 จำนวน FAQ ใหม่: {new_count} รายการ (เพิ่ม +{new_count - old_count})")
    
    # แสดง FAQ ล่าสุด
    importer.show_latest_faqs(5)
    
    # ปิดการเชื่อมต่อ
    importer.close()
    
    print("\n✅ เสร็จสิ้น!")

if __name__ == "__main__":
    main()
