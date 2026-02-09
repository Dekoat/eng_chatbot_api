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
import importlib

# Add parent directory to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Import and reload to ensure latest version
import scripts.train_model
importlib.reload(scripts.train_model)
from scripts.train_model import IntentClassifier
import re

# Keyword rules for hybrid prediction (Optimized v2)
KEYWORD_RULES = {
    # Contact - ต้องเช็คก่อน (เพราะมี "อาจารย์", "โทร" ที่ซ้ำกับ department)
    'ask_contact': [
        r'ติดต่อ', r'อีเมล', r'email', r'ที่อยู่',
        r'เบอร์', r'โทร',  # "เบอร์โทร", "โทรศัพท์"
    ],
    
    # Grade - ต้องเช็คก่อน admission (เพราะมี "สอบ" ที่ซ้ำกัน)
    'ask_grade': [
        r'เกรด', r'ผลการเรียน', r'GPA',
        r'ตกสอบ', r'สอบตก',  # "ถ้าสอบตกต้องทำยังไง"
        r'ผลสอบ', r'เช็คเกรด', r'เช็ค.*ผล',  # "ดูผลสอบได้แล้วหรือยัง"
    ],
    
    # Loan
    'ask_loan': [
        r'กยศ\.?', r'กรอ\.?', r'กู้', r'ทุนการศึกษา',
        r'ทุน(?!การศึกษา)',  # "ทุน" แต่ไม่ต้องมี "การศึกษา" ก็ได้
        r'ยื่น.*เอกสาร.*กู้', r'รายได้ครอบครัว',
    ],
    
    # Tuition
    'ask_tuition': [
        r'ค่าเทอม', r'ค่าธรรมเนียม', r'ค่าใช้จ่าย', r'ค่าหนังสือ',
        r'จ่ายเงิน', r'ชำระ', r'ผ่อน',
        r'แพง', r'ถูก',  # "เรียนที่นี่แพงไหม"
    ],
    
    # Facility - ต้องเช็คก่อน news (เพราะ "มี" อาจซ้ำกัน)
    'ask_facility': [
        r'ห้องแล็บ', r'ห้องปฏิบัติการ', r'อุปกรณ์', r'สถานที่', r'อาคาร', r'ห้องสมุด',
        r'จอดรถ', r'ที่จอด',  # "มีที่จอดรถไหม"
    ],
    
    # News
    'ask_news': [
        r'ข่าวสาร', r'กิจกรรม', r'อบรม', r'สัมมนา',
        r'ข่าว(?!สาร)',  # "ข่าว" แต่ไม่ต้องมี "สาร" ก็ได้
    ],
    
    # Department - เช็คหลังสุด (เพราะ "อาจารย์" อาจซ้ำกับ contact)
    'ask_department': [
        r'สาขา', r'ภาควิชา', r'คณะ',
        r'วิศวะ.*เรียน', r'สาขา.*เรียน',  # "วิศวะไฟฟ้าเรียนอะไร"
        r'อาจารย์(?!.*ติดต่อ)(?!.*โทร)(?!.*เบอร์)',  # "อาจารย์" แต่ไม่มี contact keywords
    ],
    
    # Admission - เช็คหลังสุด (เพราะมี "สอบ" ที่อาจซ้ำกับ grade)
    'ask_admission': [
        r'TCAS', r'รับสมัคร', r'สมัครเข้า', r'Portfolio',
        r'GPAX', r'GAT', r'PAT',
        r'สอบเข้า', r'คะแนน.*สมัคร',
    ],
}

def check_keywords(question):
    """
    Check if question matches keyword rules (case-insensitive)
    เช็คตามลำดับความสำคัญ - คำที่เฉพาะเจาะจงก่อน
    """
    # เช็คคำที่เฉพาะเจาะจงมากก่อน
    
    # 1. สอบตก/ผลสอบ → ask_grade (ก่อน admission)
    if re.search(r'สอบตก|ตกสอบ|ผลสอบ', question, re.IGNORECASE):
        return ('ask_grade', 0.95)
    
    # 2. ติดต่อ+อาจารย์/เบอร์/โทร → ask_contact (ก่อน department)
    if re.search(r'ติดต่อ|เบอร์|โทร', question, re.IGNORECASE):
        return ('ask_contact', 0.95)
    
    # 3. ทุน → ask_loan
    if re.search(r'ทุน|กยศ|กรอ|กู้', question, re.IGNORECASE):
        return ('ask_loan', 0.95)
    
    # 4. จอดรถ → ask_facility
    if re.search(r'จอด|ที่จอด', question, re.IGNORECASE):
        return ('ask_facility', 0.95)
    
    # 5. ข่าว → ask_news
    if re.search(r'ข่าว', question, re.IGNORECASE):
        return ('ask_news', 0.95)
    
    # 6. วิศวะ/สาขา+เรียน → ask_department
    if re.search(r'(วิศวะ|สาขา).*เรียน', question, re.IGNORECASE):
        return ('ask_department', 0.95)
    
    # เช็คตาม KEYWORD_RULES แบบเดิม
    for intent, patterns in KEYWORD_RULES.items():
        for pattern in patterns:
            if re.search(pattern, question, re.IGNORECASE):
                return (intent, 0.95)
    
    return (None, 0)

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
    logger.info("[OK] Model loaded successfully")
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
        
        # ใช้ AI Model เพียงอย่างเดียว (ไม่ใช้ Keyword Rules แล้ว)
        # เพราะ Model ใหม่มี Accuracy 85% และ Intent ครอบคลุม
        result = classifier.predict(question)
        result['method'] = 'ai'
        
        result['processing_time_ms'] = round((time.time() - start_time) * 1000, 2)
        
        # Log prediction
        logger.info(f"Question: {question} -> {result['intent']} ({result['confidence']:.2%}) [ai]")
        
        return jsonify(result)
        # Calculate processing time
        processing_time = (time.time() - start_time) * 1000
        result['processing_time_ms'] = round(processing_time, 2)
        
        # Log prediction
        logger.info(f"Question: {question} -> {result['intent']} ({result['confidence']:.2%}) [{result['method']}]")
        
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
    
    app.run(host='0.0.0.0', port=5000, debug=False)
