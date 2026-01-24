#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
ทดสอบ FAQ แต่ละ Batch
ทดสอบด้วยคำถามจริง วัด accuracy และแสดงผลลัพธ์
"""

import json
import requests
from datetime import datetime

class FAQBatchTester:
    def __init__(self, api_url="http://localhost:5000/predict"):
        self.api_url = api_url
        self.test_results = []
        
    def test_api_available(self):
        """เช็คว่า API พร้อมใช้งานหรือไม่"""
        try:
            health_url = self.api_url.replace('/predict', '/health')
            response = requests.get(health_url, timeout=3)
            if response.status_code == 200:
                print("✅ AI API พร้อมใช้งาน")
                return True
        except:
            print("❌ AI API ไม่พร้อม - กรุณาเปิด API ก่อน")
            print("   คำสั่ง: cd ai/api && python app.py")
            return False
    
    def predict_intent(self, question):
        """เรียก AI API เพื่อทำนาย intent"""
        try:
            response = requests.post(
                self.api_url,
                json={"question": question},
                timeout=5
            )
            if response.status_code == 200:
                return response.json()
            return None
        except Exception as e:
            print(f"❌ Error: {e}")
            return None
    
    def test_batch_tcas(self):
        """ทดสอบ Batch 1: TCAS"""
        print("\n" + "="*80)
        print("📦 ทดสอบ Batch 1: TCAS (การรับสมัคร)")
        print("="*80)
        
        test_cases = [
            {"q": "TCAS คืออะไร", "expected": "ask_admission"},
            {"q": "สมัครเข้าวิศวะต้องทำยังไง", "expected": "ask_admission"},
            {"q": "รับสมัครช่วงไหนบ้าง", "expected": "ask_admission"},
            {"q": "TCAS มีกี่รอบ", "expected": "ask_admission"},
            {"q": "รอบ Portfolio คืออะไร", "expected": "ask_admission"},
            {"q": "ต้องมีคะแนน GPAX เท่าไหร่", "expected": "ask_admission"},
            {"q": "สอบเข้าต้องสอบอะไรบ้าง", "expected": "ask_admission"},
            {"q": "มีสาขาอะไรบ้าง", "expected": "ask_department"},
            {"q": "สมัครออนไลน์ได้ไหม", "expected": "ask_admission"},
            {"q": "ต้องใช้คะแนน GAT PAT ไหม", "expected": "ask_admission"},
        ]
        
        return self._run_tests("TCAS", test_cases)
    
    def test_batch_tuition(self):
        """ทดสอบ Batch 2: Tuition"""
        print("\n" + "="*80)
        print("📦 ทดสอบ Batch 2: Tuition (ค่าเทอม)")
        print("="*80)
        
        test_cases = [
            {"q": "ค่าเทอมเท่าไหร่", "expected": "ask_tuition"},
            {"q": "จ่ายค่าเทอมที่ไหน", "expected": "ask_tuition"},
            {"q": "ค่าเทอมต้องจ่ายเมื่อไหร่", "expected": "ask_tuition"},
            {"q": "ผ่อนค่าเทอมได้ไหม", "expected": "ask_tuition"},
            {"q": "ค่าเทอมแพงไหม", "expected": "ask_tuition"},
            {"q": "มีค่าใช้จ่ายอะไรอีก", "expected": "ask_tuition"},
            {"q": "ลืมจ่ายค่าเทอมจะเป็นยังไง", "expected": "ask_tuition"},
            {"q": "มีส่วนลดค่าเทอมไหม", "expected": "ask_tuition"},
            {"q": "ค่าหนังสือเท่าไหร่", "expected": "ask_tuition"},
            {"q": "ชำระผ่านแอปได้ไหม", "expected": "ask_tuition"},
        ]
        
        return self._run_tests("Tuition", test_cases)
    
    def test_batch_loan(self):
        """ทดสอบ Batch 3: Student Loans"""
        print("\n" + "="*80)
        print("📦 ทดสอบ Batch 3: Student Loans (กยศ./กรอ.)")
        print("="*80)
        
        test_cases = [
            {"q": "กยศ. คืออะไร", "expected": "ask_loan"},
            {"q": "กรอ. ต่างจาก กยศ. ยังไง", "expected": "ask_loan"},
            {"q": "สมัคร กยศ. ทำยังไง", "expected": "ask_loan"},
            {"q": "กู้ได้เท่าไหร่", "expected": "ask_loan"},
            {"q": "เงื่อนไขการกู้มีอะไรบ้าง", "expected": "ask_loan"},
            {"q": "ต้องเตรียมเอกสารอะไร", "expected": "ask_loan"},
            {"q": "เมื่อไหร่จะได้เงิน", "expected": "ask_loan"},
            {"q": "ลาออกต้องคืนเงินไหม", "expected": "ask_loan"},
            {"q": "ต้องมีรายได้ครอบครัวเท่าไหร่", "expected": "ask_loan"},
            {"q": "กยศ. คืนเงินยังไง", "expected": "ask_loan"},
        ]
        
        return self._run_tests("Loan", test_cases)
    
    def _run_tests(self, batch_name, test_cases):
        """รันการทดสอบและเก็บผลลัพธ์"""
        correct = 0
        total = len(test_cases)
        batch_results = []
        
        for i, test in enumerate(test_cases, 1):
            question = test["q"]
            expected = test["expected"]
            
            print(f"\n{i}. ทดสอบ: {question}")
            
            result = self.predict_intent(question)
            
            if result:
                predicted = result.get('intent', 'unknown')
                confidence = result.get('confidence', 0)
                
                is_correct = (predicted == expected)
                status = "✅ ถูก" if is_correct else "❌ ผิด"
                
                if is_correct:
                    correct += 1
                
                print(f"   {status} - Predicted: {predicted} (confidence: {confidence:.2%})")
                print(f"   Expected: {expected}")
                
                batch_results.append({
                    "batch": batch_name,
                    "question": question,
                    "expected": expected,
                    "predicted": predicted,
                    "confidence": confidence,
                    "correct": is_correct
                })
            else:
                print(f"   ❌ API Error")
                batch_results.append({
                    "batch": batch_name,
                    "question": question,
                    "expected": expected,
                    "predicted": "error",
                    "confidence": 0,
                    "correct": False
                })
        
        accuracy = (correct / total * 100) if total > 0 else 0
        
        print(f"\n{'='*80}")
        print(f"📊 ผลลัพธ์ Batch '{batch_name}':")
        print(f"   - ทดสอบทั้งหมด: {total} คำถาม")
        print(f"   - ถูกต้อง: {correct} คำถาม")
        print(f"   - ผิดพลาด: {total - correct} คำถาม")
        print(f"   - Accuracy: {accuracy:.2f}%")
        print(f"{'='*80}")
        
        self.test_results.extend(batch_results)
        
        return {
            "batch": batch_name,
            "total": total,
            "correct": correct,
            "accuracy": accuracy,
            "details": batch_results
        }
    
    def save_results(self, filename="ai/data/test_results.json"):
        """บันทึกผลการทดสอบ"""
        import os
        os.makedirs(os.path.dirname(filename), exist_ok=True)
        
        summary = {
            "test_date": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "total_tests": len(self.test_results),
            "total_correct": sum(1 for r in self.test_results if r['correct']),
            "overall_accuracy": (sum(1 for r in self.test_results if r['correct']) / len(self.test_results) * 100) if self.test_results else 0,
            "results": self.test_results
        }
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(summary, f, ensure_ascii=False, indent=2)
        
        print(f"\n💾 บันทึกผลการทดสอบที่: {filename}")
    
    def show_summary(self):
        """แสดงสรุปผลการทดสอบทั้งหมด"""
        if not self.test_results:
            print("\n⚠️  ไม่มีผลการทดสอบ")
            return
        
        total = len(self.test_results)
        correct = sum(1 for r in self.test_results if r['correct'])
        accuracy = (correct / total * 100) if total > 0 else 0
        
        print("\n" + "="*80)
        print("📊 สรุปผลการทดสอบทั้งหมด")
        print("="*80)
        print(f"   - ทดสอบทั้งหมด: {total} คำถาม")
        print(f"   - ถูกต้อง: {correct} คำถาม ({accuracy:.2f}%)")
        print(f"   - ผิดพลาด: {total - correct} คำถาม ({100-accuracy:.2f}%)")
        
        # แสดงรายละเอียดข้อผิดพลาด
        errors = [r for r in self.test_results if not r['correct']]
        if errors:
            print(f"\n❌ คำถามที่ทำนายผิด ({len(errors)} คำถาม):")
            for i, err in enumerate(errors, 1):
                print(f"\n   {i}. {err['question']}")
                print(f"      Expected: {err['expected']}")
                print(f"      Predicted: {err['predicted']} (confidence: {err['confidence']:.2%})")
        
        print("="*80)

def main():
    print("="*80)
    print("🧪 FAQ Batch Tester - ทดสอบ FAQ แต่ละชุด")
    print("="*80)
    
    tester = FAQBatchTester()
    
    # เช็ค API
    if not tester.test_api_available():
        return
    
    # ทดสอบแต่ละ batch
    tester.test_batch_tcas()
    tester.test_batch_tuition()
    tester.test_batch_loan()
    
    # แสดงสรุปและบันทึกผล
    tester.show_summary()
    tester.save_results()
    
    print("\n✅ เสร็จสิ้นการทดสอบ!")

if __name__ == "__main__":
    main()
