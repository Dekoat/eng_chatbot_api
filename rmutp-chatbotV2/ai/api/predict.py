"""
Helper class for calling Python ML API from PHP
This file provides utility functions to integrate AI predictions
"""

class PredictAPI:
    """Simple wrapper for calling the prediction API"""
    
    def __init__(self, api_url='http://localhost:5000'):
        self.api_url = api_url
    
    def predict(self, question):
        """
        Call the ML API to predict intent
        
        Args:
            question (str): User's question
            
        Returns:
            dict: {
                'intent': str,
                'confidence': float,
                'alternatives': list
            }
        """
        import requests
        import json
        
        try:
            response = requests.post(
                f'{self.api_url}/predict',
                json={'question': question},
                timeout=2
            )
            
            if response.status_code == 200:
                return response.json()
            else:
                return None
                
        except Exception as e:
            print(f"Error calling ML API: {e}")
            return None


# Example usage
if __name__ == '__main__':
    api = PredictAPI()
    
    test_questions = [
        "ค่าเทอมเท่าไหร่",
        "อาจารย์สาขาคอมพิวเตอร์",
        "สมัคร TCAS"
    ]
    
    for q in test_questions:
        result = api.predict(q)
        if result:
            print(f"\nQ: {q}")
            print(f"Intent: {result['intent']} ({result['confidence']:.2%})")
