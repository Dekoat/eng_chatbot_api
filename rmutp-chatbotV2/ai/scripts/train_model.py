"""
Intent Classifier Training Script
Train a simple ML model to classify user questions into predefined intents
"""

import pandas as pd
import numpy as np
from pythainlp import word_tokenize
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
import joblib
import os

class IntentClassifier:
    def __init__(self, model_type='logistic_regression'):
        self.vectorizer = TfidfVectorizer(
            tokenizer=self._tokenize_thai,
            max_features=1000,
            ngram_range=(1, 2),
            min_df=1
        )
        if model_type == 'logistic_regression':
            self.model = LogisticRegression(max_iter=1000, random_state=42)
        else:
            self.model = MultinomialNB(alpha=0.1)
        self.model_type = model_type
        
    def _tokenize_thai(self, text):
        """Tokenize Thai text using pythainlp"""
        return word_tokenize(text, engine='newmm')
    
    def load_data(self, filepath='../data/training_data.csv'):
        """Load training data from CSV"""
        # Handle both relative and absolute paths
        if not os.path.exists(filepath):
            # Try from script directory
            script_dir = os.path.dirname(os.path.abspath(__file__))
            filepath = os.path.join(script_dir, filepath)
        if not os.path.exists(filepath):
            # Try from project root
            filepath = os.path.join(os.getcwd(), 'ai', 'data', 'training_data.csv')
        
        df = pd.read_csv(filepath)
        print(f"Loaded {len(df)} training examples")
        print(f"Intents: {df['intent'].unique()}")
        print(f"\nIntent distribution:")
        print(df['intent'].value_counts())
        return df['question'].values, df['intent'].values
    
    def train(self, X, y, test_size=0.0, random_state=42):
        """Train the model (test_size=0.0 means use all data for training)"""
        
        if test_size > 0:
            # Split data
            X_train, X_test, y_train, y_test = train_test_split(
                X, y, test_size=test_size, random_state=random_state, stratify=y
            )
            
            print(f"\nTraining set: {len(X_train)} examples")
            print(f"Test set: {len(X_test)} examples")
        else:
            # Use all data for training
            X_train = X
            y_train = y
            X_test = X[:20]  # Use first 20 for sanity check
            y_test = y[:20]
            print(f"\nUsing ALL {len(X_train)} examples for training (no test split)")
            print(f"Sanity check with first 20 examples")
        
        # Transform text to TF-IDF features
        print("\nTransforming text to TF-IDF features...")
        X_train_tfidf = self.vectorizer.fit_transform(X_train)
        X_test_tfidf = self.vectorizer.transform(X_test)
        
        # Train model
        model_name = "Logistic Regression" if self.model_type == 'logistic_regression' else "Naive Bayes"
        print(f"Training {model_name} model...")
        self.model.fit(X_train_tfidf, y_train)
        
        # Evaluate
        y_pred = self.model.predict(X_test_tfidf)
        accuracy = accuracy_score(y_test, y_pred)
        
        print(f"\n{'='*50}")
        print(f"TRAINING COMPLETED")
        print(f"{'='*50}")
        print(f"Accuracy: {accuracy:.2%}")
        
        if accuracy >= 0.75:
            print("✅ SUCCESS: Accuracy meets target (≥75%)")
        else:
            print("⚠️  WARNING: Accuracy below target. Consider:")
            print("   - Adding more training examples")
            print("   - Balancing intent distribution")
            print("   - Tuning hyperparameters")
        
        print(f"\n{'-'*50}")
        print("Classification Report:")
        print(f"{'-'*50}")
        print(classification_report(y_test, y_pred, zero_division=0))
        
        print(f"\n{'-'*50}")
        print("Confusion Matrix:")
        print(f"{'-'*50}")
        print(confusion_matrix(y_test, y_pred))
        
        return accuracy
    
    def predict(self, text):
        """Predict intent for a single text"""
        X = self.vectorizer.transform([text])
        intent = self.model.predict(X)[0]
        probabilities = self.model.predict_proba(X)[0]
        confidence = max(probabilities)
        
        # Get top 3 predictions
        intent_classes = self.model.classes_
        top_indices = np.argsort(probabilities)[::-1][:3]
        alternatives = [
            {
                'intent': str(intent_classes[i]),  # Convert to Python string
                'confidence': float(probabilities[i])
            }
            for i in top_indices[1:]  # Skip first (already in main prediction)
        ]
        
        return {
            'intent': str(intent),  # Convert to Python string
            'confidence': float(confidence),
            'alternatives': alternatives
        }
    
    def save_model(self, model_dir='../models'):
        """Save trained model and vectorizer"""
        # Handle both relative and absolute paths
        if not os.path.isabs(model_dir):
            script_dir = os.path.dirname(os.path.abspath(__file__))
            model_dir = os.path.join(script_dir, model_dir)
        
        os.makedirs(model_dir, exist_ok=True)
        
        model_path = os.path.join(model_dir, 'intent_classifier.pkl')
        vectorizer_path = os.path.join(model_dir, 'vectorizer.pkl')
        
        joblib.dump(self.model, model_path)
        joblib.dump(self.vectorizer, vectorizer_path)
        
        print(f"\n✅ Model saved:")
        print(f"   - {model_path}")
        print(f"   - {vectorizer_path}")
    
    def load_model(self, model_dir='../models'):
        """Load trained model and vectorizer"""
        # Handle both relative and absolute paths
        if not os.path.isabs(model_dir):
            script_dir = os.path.dirname(os.path.abspath(__file__))
            model_dir = os.path.join(script_dir, model_dir)
        
        model_path = os.path.join(model_dir, 'intent_classifier.pkl')
        vectorizer_path = os.path.join(model_dir, 'vectorizer.pkl')
        
        self.model = joblib.load(model_path)
        self.vectorizer = joblib.load(vectorizer_path)
        
        print(f"[OK] Model loaded from {model_dir}")


def main():
    print("="*70)
    print("RMUTP CHATBOT - INTENT CLASSIFIER TRAINING")
    print("="*70)
    
    # Initialize classifier
    classifier = IntentClassifier()
    
    # Load data
    print("\n[1/4] Loading training data...")
    X, y = classifier.load_data()
    
    # Train model
    print("\n[2/4] Training model...")
    accuracy = classifier.train(X, y)
    
    # Save model
    print("\n[3/4] Saving model...")
    classifier.save_model()
    
    # Test with examples
    print("\n[4/4] Testing with sample questions...")
    print("="*70)
    
    test_questions = [
        "ค่าเทอมเท่าไหร่",
        "อาจารย์สาขาคอมพิวเตอร์",
        "สมัคร TCAS",
        "กู้เงิน กยศ",
        "มีสาขาอะไรบ้าง",
        "ห้องแล็บคอมพิวเตอร์",
    ]
    
    for question in test_questions:
        result = classifier.predict(question)
        print(f"\nQuestion: {question}")
        print(f"Intent: {result['intent']}")
        print(f"Confidence: {result['confidence']:.2%}")
        if result['alternatives']:
            print(f"Alternatives: {result['alternatives'][0]['intent']} ({result['alternatives'][0]['confidence']:.2%})")
    
    print("\n" + "="*70)
    print("TRAINING COMPLETE!")
    print("="*70)
    print("\nNext steps:")
    print("1. Run the Flask API: python api/app.py")
    print("2. Test predictions: python scripts/test_model.py")
    print("3. Integrate with PHP: Update backend/chatbot.php")


if __name__ == '__main__':
    main()
