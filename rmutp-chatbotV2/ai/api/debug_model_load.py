#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Debug: Check which model file API is loading"""

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from scripts.train_model import IntentClassifier

# Check model directory resolution
classifier = IntentClassifier()

# Show where load_model looks for files
model_dir = '../models'
script_dir = os.path.dirname(os.path.abspath(__file__))
resolved_dir = os.path.join(script_dir, model_dir)

print(f"Script dir: {script_dir}")
print(f"Model dir (relative): {model_dir}")
print(f"Model dir (resolved): {resolved_dir}")
print(f"Model dir (absolute): {os.path.abspath(resolved_dir)}")

model_path = os.path.join(resolved_dir, 'intent_classifier.pkl')
vectorizer_path = os.path.join(resolved_dir, 'vectorizer.pkl')

print(f"\nModel file: {model_path}")
print(f"Exists: {os.path.exists(model_path)}")
if os.path.exists(model_path):
    import datetime
    mtime = os.path.getmtime(model_path)
    print(f"Modified: {datetime.datetime.fromtimestamp(mtime)}")
    print(f"Size: {os.path.getsize(model_path)} bytes")

print(f"\nVectorizer file: {vectorizer_path}")
print(f"Exists: {os.path.exists(vectorizer_path)}")
if os.path.exists(vectorizer_path):
    import datetime
    mtime = os.path.getmtime(vectorizer_path)
    print(f"Modified: {datetime.datetime.fromtimestamp(mtime)}")
    print(f"Size: {os.path.getsize(vectorizer_path)} bytes")

# Load and test
print("\n" + "="*50)
classifier.load_model()
result = classifier.predict("กู้เงิน กยศ")
print(f"\nTest: กู้เงิน กยศ")
print(f"Intent: {result['intent']} ({result['confidence']:.2%})")
