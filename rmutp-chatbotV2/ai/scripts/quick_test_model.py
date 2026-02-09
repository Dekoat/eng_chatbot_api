#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Quick test trained model"""

from train_model import IntentClassifier

# Load model
classifier = IntentClassifier()
classifier.load_model()

# Test questions
test_questions = [
    "กู้เงิน กยศ",
    "รับสมัคร มทร 2569",
    "วิศวกรรมอุตสาหการเรียนอะไร",
    "ค่าเทอมเท่าไหร่"
]

print("\n=== Direct Model Test ===\n")
for q in test_questions:
    result = classifier.predict(q)
    print(f"Q: {q}")
    print(f"   Intent: {result['intent']} ({result['confidence']:.2%})")
    print()
