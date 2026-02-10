#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Direct AI Prediction (for PHP chatbot.php)
รับคำถามจาก command line → คืน JSON
"""

import sys
import json
import os

# Add path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from train_model import IntentClassifier

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No question provided'}, ensure_ascii=False))
        sys.exit(1)
    
    question = sys.argv[1]
    
    # Load model (suppress output)
    classifier = IntentClassifier()
    try:
        # Redirect stdout temporarily to suppress model load message
        import io
        old_stdout = sys.stdout
        sys.stdout = io.StringIO()
        
        classifier.load_model()
        
        # Restore stdout
        sys.stdout = old_stdout
    except:
        sys.stdout = old_stdout
        print(json.dumps({'error': 'Model not found'}, ensure_ascii=False))
        sys.exit(1)
    
    # Predict
    result = classifier.predict(question)
    
    # Output JSON only
    print(json.dumps(result, ensure_ascii=False))

if __name__ == '__main__':
    main()
