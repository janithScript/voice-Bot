import re, dateparser
from datetime import datetime, date

def extract_name(text):
    """Extract name from 'my name is X' or 'I am X'"""
    patterns = [
        r'my name is ([A-Za-z ]+)',
        r"i'?m ([A-Za-z ]+)",
        r'call me ([A-Za-z ]+)',
        r'^([A-Za-z ]{2,40})$',  # Just a name by itself
    ]
    for p in patterns:
        m = re.search(p, text.lower())
        if m:
            name = m.group(1).strip().title()
            if len(name) > 2:
                return name
    return None

def extract_date(text):
    """Extract date from natural language like 'tomorrow', 'next Monday'"""
    # Try dateparser for natural language
    parsed = dateparser.parse(text, settings={'PREFER_DATES_FROM': 'future'})
    if parsed and parsed.date() >= date.today():
        return parsed.strftime('%Y-%m-%d')
    # Fallback: look for YYYY-MM-DD
    m = re.search(r'(\d{4}-\d{2}-\d{2})', text)
    return m.group(1) if m else None

def extract_time(text):
    """Extract time like '9am', '2:30 pm', '14:00'"""
    m = re.search(r'(\d{1,2})(?::(\d{2}))?\s*(am|pm)?', text.lower())
    if m:
        h = int(m.group(1)); mins = m.group(2) or '00'; ampm = m.group(3)
        if ampm == 'pm' and h < 12: h += 12
        if ampm == 'am' and h == 12: h = 0
        return f'{h:02d}:{mins}'
    return None

def is_affirmative(text):
    return any(w in text.lower() for w in ['yes','yeah','yep','correct','sure','ok','okay'])

def is_negative(text):
    return any(w in text.lower() for w in ['no','nope','cancel','stop'])
