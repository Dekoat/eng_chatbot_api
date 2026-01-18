"""
Flask API for Intent Classification
Provides /predict endpoint for PHP backend to call
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import sys
import os
import time
import logging

# Add parent directory to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from scripts.train_model import IntentClassifier

# Initialize Flask app
app = Flask(__name__)
app.config['JSON_AS_ASCII'] = False  # Support Thai characters
CORS(app)  # Enable CORS for PHP to call

# Configure logging
script_dir = os.path.dirname(os.path.abspath(__file__))
log_dir = os.path.join(script_dir, '..', 'logs')
os.makedirs(log_dir, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(log_dir, 'api.log')),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Load model at startup
classifier = IntentClassifier()
try:
    classifier.load_model()
    logger.info("✅ Model loaded successfully")
except FileNotFoundError:
    logger.error("❌ Model not found! Please train the model first.")
    logger.error("Run: python scripts/train_model.py")


@app.route('/', methods=['GET'])
def home():
    """API home page"""
    return jsonify({
        'service': 'RMUTP Chatbot Intent Classifier API',
        'version': '1.0.0',
        'status': 'running',
        'endpoints': {
            'predict': '/predict (POST)',
            'health': '/health (GET)'
        }
    })


@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'model_loaded': hasattr(classifier, 'model') and classifier.model is not None
    })


@app.route('/predict', methods=['POST'])
def predict():
    """
    Predict intent from user question
    
    Request JSON:
    {
        "question": "ค่าเทอมเท่าไหร่"
    }
    
    Response JSON:
    {
        "intent": "ask_tuition",
        "confidence": 0.92,
        "alternatives": [...],
        "processing_time_ms": 15
    }
    """
    start_time = time.time()
    
    try:
        # Get question from request
        data = request.get_json()
        
        if not data or 'question' not in data:
            return jsonify({
                'error': 'Missing required field: question'
            }), 400
        
        question = data['question'].strip()
        
        if not question:
            return jsonify({
                'error': 'Question cannot be empty'
            }), 400
        
        # Predict
        result = classifier.predict(question)
        
        # Calculate processing time
        processing_time = (time.time() - start_time) * 1000  # Convert to ms
        result['processing_time_ms'] = round(processing_time, 2)
        
        # Log prediction
        logger.info(f"Question: {question}")
        logger.info(f"Intent: {result['intent']} (confidence: {result['confidence']:.2%})")
        
        return jsonify(result)
    
    except Exception as e:
        logger.error(f"Error in prediction: {str(e)}")
        return jsonify({
            'error': 'Internal server error',
            'message': str(e)
        }), 500


@app.route('/batch_predict', methods=['POST'])
def batch_predict():
    """
    Predict intents for multiple questions
    
    Request JSON:
    {
        "questions": ["ค่าเทอมเท่าไหร่", "อาจารย์สาขาคอม"]
    }
    """
    try:
        data = request.get_json()
        
        if not data or 'questions' not in data:
            return jsonify({
                'error': 'Missing required field: questions'
            }), 400
        
        questions = data['questions']
        
        if not isinstance(questions, list):
            return jsonify({
                'error': 'questions must be an array'
            }), 400
        
        # Predict for all questions
        results = []
        for question in questions:
            if question.strip():
                result = classifier.predict(question.strip())
                results.append({
                    'question': question,
                    **result
                })
        
        return jsonify({
            'predictions': results,
            'count': len(results)
        })
    
    except Exception as e:
        logger.error(f"Error in batch prediction: {str(e)}")
        return jsonify({
            'error': 'Internal server error',
            'message': str(e)
        }), 500


if __name__ == '__main__':
    print("="*70)
    print("RMUTP CHATBOT - INTENT CLASSIFIER API")
    print("="*70)
    print("\nStarting Flask API server...")
    print("API will be available at: http://localhost:5000")
    print("\nEndpoints:")
    print("  - GET  /         : API information")
    print("  - GET  /health   : Health check")
    print("  - POST /predict  : Predict single question")
    print("  - POST /batch_predict : Predict multiple questions")
    print("\nPress Ctrl+C to stop")
    print("="*70 + "\n")
    
    app.run(host='0.0.0.0', port=5000, debug=True)
