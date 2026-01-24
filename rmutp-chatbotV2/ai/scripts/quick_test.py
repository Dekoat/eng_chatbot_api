import requests

questions = [
    'TCAS คืออะไร',
    'กยศ. คืออะไร', 
    'ค่าเทอมเท่าไหร่',
    'สมัคร กยศ. ทำยังไง',
    'กู้ได้เท่าไหร่'
]

for q in questions:
    r = requests.post('http://localhost:5000/predict', json={'question': q})
    result = r.json()
    method = result.get('method', '?')
    print(f"{q:30s} -> {result['intent']:15s} ({result['confidence']:6.2%}) [{method}]")
