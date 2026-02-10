"""
Utilities for Thai text processing
แยกออกมาเป็นโมดูลอิสระเพื่อให้ pickle ได้ง่าย
"""

from pythainlp import word_tokenize

def tokenize_thai(text):
    """Tokenize Thai text using pythainlp"""
    return word_tokenize(text, engine='newmm')
