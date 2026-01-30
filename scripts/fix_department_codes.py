#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Fix duplicate/inconsistent department codes in FAQ database
Standardize all department codes to xxx_engineering format
"""

import mysql.connector
from collections import Counter

def connect_db():
    """Connect to MySQL database"""
    try:
        conn = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="eng_chatbot",
            charset='utf8mb4',
            collation='utf8mb4_unicode_ci'
        )
        return conn
    except Exception as e:
        print(f"❌ Database connection failed: {e}")
        return None

def fix_department_codes():
    """Fix and standardize department codes"""
    
    conn = connect_db()
    if not conn:
        return
    
    cursor = conn.cursor(dictionary=True)
    
    print("="*80)
    print("🔧 FIXING DEPARTMENT CODES")
    print("="*80)
    print()
    
    # 1. Check current department codes
    print("📊 Current Department Codes:\n")
    cursor.execute("""
        SELECT department, COUNT(*) as count 
        FROM faq 
        GROUP BY department 
        ORDER BY count DESC
    """)
    
    current_depts = cursor.fetchall()
    for dept in current_depts:
        print(f"   {dept['department']:50s} : {dept['count']:3d} FAQ")
    
    print("\n" + "="*80)
    print("🔄 Applying Standardization...")
    print("="*80)
    print()
    
    # 2. Define mapping for corrections
    corrections = {
        # Existing inconsistent codes → Standard codes
        'Electrical Engineering': 'electrical_engineering',
        'Electrical Communication and Intelligent Systems E': 'electronics_telecom_engineering',
        'Mechatronics Engineering': 'mechatronics_engineering',
        'SIME': 'sime_engineering',
        'sime_engineering': 'sime_engineering',  # Keep if already correct
        'jewelry_engineering': 'jewelry_engineering',  # Keep
        'industrial_engineering': 'industrial_engineering',  # Keep
        'computer_engineering': 'computer_engineering',  # Keep
        'mechanical_engineering': 'mechanical_engineering',  # Keep
        'civil_engineering': 'civil_engineering',  # Keep
        'tool_engineering': 'tool_engineering',  # Keep
        'student_affairs': 'student_affairs',  # Special case - not engineering
        'general': 'general',  # Special case
        'vocational_computer': 'vocational_computer',  # Special case
        'vocational': 'vocational',  # Special case
        'undergraduate': 'undergraduate',  # Special case
        'graduate': 'graduate'  # Special case
    }
    
    changes = {}
    total_affected = 0
    
    try:
        # 3. Apply corrections
        for old_code, new_code in corrections.items():
            if old_code == new_code:
                continue  # Skip if already correct
            
            # Check if this code exists
            cursor.execute("SELECT COUNT(*) as count FROM faq WHERE department = %s", (old_code,))
            count = cursor.fetchone()['count']
            
            if count > 0:
                # Update to new code
                cursor.execute("""
                    UPDATE faq 
                    SET department = %s 
                    WHERE department = %s
                """, (new_code, old_code))
                
                changes[old_code] = {'new_code': new_code, 'count': count}
                total_affected += count
                
                print(f"✅ '{old_code}' → '{new_code}' ({count} FAQ)")
        
        # Commit transaction
        conn.commit()
        
        print("\n" + "="*80)
        print("📊 Summary of Changes")
        print("="*80)
        print()
        
        if changes:
            print(f"✅ Successfully updated {len(changes)} department codes")
            print(f"📝 Total FAQ affected: {total_affected}")
            print()
            
            for old_code, info in changes.items():
                print(f"   • {old_code}")
                print(f"     → {info['new_code']} ({info['count']} FAQ)")
        else:
            print("✅ No changes needed - all codes are already standardized!")
        
        # 4. Show updated department list
        print("\n" + "="*80)
        print("📋 Updated Department Codes")
        print("="*80)
        print()
        
        cursor.execute("""
            SELECT department, COUNT(*) as count 
            FROM faq 
            GROUP BY department 
            ORDER BY count DESC
        """)
        
        updated_depts = cursor.fetchall()
        
        dept_names = {
            'electrical_engineering': 'วิศวกรรมไฟฟ้า',
            'electronics_telecom_engineering': 'วิศวกรรมอิเล็กทรอนิกส์และโทรคมนาคม',
            'mechatronics_engineering': 'วิศวกรรมเมคคาทรอนิกส์',
            'mechanical_engineering': 'วิศวกรรมเครื่องกล',
            'civil_engineering': 'วิศวกรรมโยธา',
            'tool_engineering': 'วิศวกรรมเครื่องมือและแม่พิมพ์',
            'computer_engineering': 'วิศวกรรมคอมพิวเตอร์',
            'industrial_engineering': 'วิศวกรรมอุตสาหการ',
            'sime_engineering': 'วิศวกรรมสื่อสารและระบบอัจฉริยะ',
            'jewelry_engineering': 'วิศวกรรมเครื่องประดับ',
            'student_affairs': 'งานกิจการนักศึกษา',
            'general': 'ทั่วไป',
            'vocational': 'ปวช./ปวส.',
            'vocational_computer': 'ปวช./ปวส. คอมพิวเตอร์',
            'undergraduate': 'ปริญญาตรี',
            'graduate': 'บัณฑิตศึกษา'
        }
        
        print(f"{'Department Code':<50s} | {'Count':>5s} | {'Thai Name'}")
        print("-" * 80)
        
        for dept in updated_depts:
            code = dept['department']
            count = dept['count']
            name = dept_names.get(code, code)
            print(f"{code:<50s} | {count:>5d} | {name}")
        
        print("\n" + "="*80)
        print("✅ Department Code Standardization Complete!")
        print("="*80)
        
        # 5. Validation
        print("\n🔍 Validation Check:")
        
        # Check for any remaining non-standard codes
        cursor.execute("""
            SELECT DISTINCT department 
            FROM faq 
            WHERE department NOT IN (
                'electrical_engineering', 'electronics_telecom_engineering',
                'mechatronics_engineering', 'mechanical_engineering',
                'civil_engineering', 'tool_engineering', 
                'computer_engineering', 'industrial_engineering',
                'sime_engineering', 'jewelry_engineering',
                'student_affairs', 'general', 
                'vocational', 'vocational_computer',
                'undergraduate', 'graduate'
            )
        """)
        
        remaining = cursor.fetchall()
        
        if remaining:
            print("\n⚠️  Warning: Found non-standard codes:")
            for dept in remaining:
                print(f"   • {dept['department']}")
        else:
            print("\n✅ All department codes are now standardized!")
        
    except Exception as e:
        conn.rollback()
        print(f"\n❌ Error during update: {e}")
        print("   Transaction rolled back")
    
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    fix_department_codes()
