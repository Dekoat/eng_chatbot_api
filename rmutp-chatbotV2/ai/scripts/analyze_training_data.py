"""
Analyze Training Data Distribution
"""
import pandas as pd
import os

# Load training data
script_dir = os.path.dirname(os.path.abspath(__file__))
data_file = os.path.join(script_dir, '../data/training_data.csv')
df = pd.read_csv(data_file)

print("="*70)
print("TRAINING DATA ANALYSIS")
print("="*70)
print(f"\nTotal examples: {len(df)}")
print(f"Unique intents: {len(df['intent'].unique())}")

# Intent distribution
print("\n" + "="*70)
print("INTENT DISTRIBUTION")
print("="*70)
dist = df['intent'].value_counts().sort_index()
for intent, count in dist.items():
    percentage = count / len(df) * 100
    bar = "█" * int(percentage / 2)
    print(f"{intent:20s}: {count:3d} examples ({percentage:5.1f}%) {bar}")

# Test cases analysis (last 90 rows)
print("\n" + "="*70)
print("TEST CASES ANALYSIS (Last 90 rows)")
print("="*70)
test_cases = df.tail(90)
test_dist = test_cases['intent'].value_counts().sort_index()
print("\nTest cases distribution:")
for intent, count in test_dist.items():
    print(f"{intent:20s}: {count:3d} test cases")

# Original FAQ data (first 392 rows)
print("\n" + "="*70)
print("ORIGINAL FAQ DATA (First 392 rows)")
print("="*70)
original = df.head(392)
original_dist = original['intent'].value_counts().sort_index()
print("\nOriginal data distribution:")
for intent, count in original_dist.items():
    print(f"{intent:20s}: {count:3d} examples")

# Stratified split simulation
print("\n" + "="*70)
print("STRATIFIED SPLIT SIMULATION (80/20)")
print("="*70)
print("\nExpected training/test split per intent:")
for intent, count in dist.items():
    train_count = int(count * 0.8)
    test_count = count - train_count
    print(f"{intent:20s}: {train_count:3d} train, {test_count:2d} test")

print("\n" + "="*70)
print("ACCURACY PROBLEMS")
print("="*70)
print("\nBatch test results:")
print("- Admission (ask_admission): 40% accuracy (6/10 errors)")
print("- Tuition (ask_tuition):     80% accuracy (2/10 errors) ✅")
print("- Loan (ask_loan):           30% accuracy (7/10 errors) ❌")
print("\nPossible reasons:")
print("1. Stratified split may put critical test cases into test set")
print("2. Admission and Loan intents need more training examples")
print("3. Model cannot distinguish similar questions across intents")
print("\nRecommendations:")
print("1. Increase training data for ask_admission and ask_loan")
print("2. Add more diverse question patterns")
print("3. Consider using different model (Logistic Regression, SVM)")
print("4. Adjust TF-IDF parameters (ngram_range, max_features)")
