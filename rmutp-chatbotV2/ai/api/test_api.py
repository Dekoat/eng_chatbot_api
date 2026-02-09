#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Test AI API Server"""

import requests
import json

API_URL = 'http://localhost:5000/predict'

tests = [
    {'question': 'กู้เงิน กยศ', 'expected': 'loan'},
    {'question': 'รับสมัคร มทร 2569', 'expected': 'admission'},
    {'question': 'วิศวกรรมอุตสาหการเรียนอะไร', 'expected': 'program'},
    {'question': 'ค่าเทอมเท่าไหร่', 'expected': 'tuition'}
]

print("\n" + "="*70)
print("🎯 FINAL AI API TEST")
print("="*70 + "\n")

for test in tests:
    try:
        response = requests.post(
            API_URL,
            json={'question': test['question']},
            timeout=5
        )
        result = response.json()
        intent = result['intent']
        confidence = result['confidence'] * 100
        expected = test['expected']
        match = '✅' if intent == expected else '❌'
        
        print(f"{match} {test['question']}")
        print(f"   Result: {intent} ({confidence:.2f}%)")
        print(f"   Expected: {expected}")
        print()
    except Exception as e:
        print(f"❌ {test['question']}")
        print(f"   Error: {e}\n")

print("="*70)
