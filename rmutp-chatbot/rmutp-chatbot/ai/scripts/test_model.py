"""
Test trained model with custom questions
"""

import sys
import os
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from train_model import IntentClassifier

def test_model():
    print("="*70)
    print("INTENT CLASSIFIER - INTERACTIVE TESTING")
    print("="*70)
    
    # Load trained model
    classifier = IntentClassifier()
    try:
        classifier.load_model()
    except FileNotFoundError:
        print("\n❌ Error: Model not found!")
        print("Please train the model first: python train_model.py")
        return
    
    print("\nModel loaded successfully!")
    print("Type your question (or 'quit' to exit)")
    print("-"*70)
    
    while True:
        question = input("\nคำถาม: ").strip()
        
        if question.lower() in ['quit', 'exit', 'q']:
            print("\nGoodbye!")
            break
        
        if not question:
            continue
        
        # Predict
        result = classifier.predict(question)
        
        # Display result
        print(f"\n{'='*50}")
        print(f"Intent: {result['intent']}")
        print(f"Confidence: {result['confidence']:.2%}")
        
        if result['confidence'] >= 0.80:
            print("Status: ✅ HIGH CONFIDENCE (Use Rule-based)")
        elif result['confidence'] >= 0.60:
            print("Status: ⚠️  MEDIUM CONFIDENCE (Consider AI)")
        else:
            print("Status: ❌ LOW CONFIDENCE (Forward to staff)")
        
        if result['alternatives']:
            print(f"\nAlternatives:")
            for alt in result['alternatives']:
                print(f"  - {alt['intent']}: {alt['confidence']:.2%}")


if __name__ == '__main__':
    test_model()
