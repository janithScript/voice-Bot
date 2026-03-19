import re
import dateparser
from datetime import date

def extract_name(text):
    """Extract a person's name from various natural phrasings."""
    t = text.strip()

    # Pattern 1: explicit introduction phrases — highest confidence
    patterns = [
        r"my name is ([A-Za-z][A-Za-z '-]{1,40})",
        r"i(?:'m| am) ([A-Za-z][A-Za-z '-]{1,40})",
        r"call me ([A-Za-z][A-Za-z '-]{1,40})",
        r"this is ([A-Za-z][A-Za-z '-]{1,40})",
        r"it'?s ([A-Za-z][A-Za-z '-]{1,40})",
        r"name'?s? ([A-Za-z][A-Za-z '-]{1,40})",
    ]
    for pattern in patterns:
        m = re.search(pattern, t, re.IGNORECASE)
        if m:
            candidate = m.group(1).strip().rstrip('.,!?').title()
            # Reject if candidate is a common non-name word
            if _is_valid_name(candidate):
                return candidate

    # Pattern 2: 1–3 plain words with no digits, likely a name typed/spoken directly
    # Handles: "Janith", "John Smith", "Mary Anne Jones"
    cleaned = re.sub(r'[^A-Za-z\s]', '', t).strip()
    words = cleaned.split()
    if 1 <= len(words) <= 3 and all(len(w) >= 2 for w in words):
        candidate = ' '.join(w.title() for w in words)
        if _is_valid_name(candidate):
            return candidate

    return None


def _is_valid_name(text):
    """Reject words that are clearly not names."""
    # Common trigger words that are not names
    non_names = {
        'hello', 'hi', 'hey', 'yes', 'no', 'ok', 'okay', 'sure',
        'tomorrow', 'today', 'monday', 'tuesday', 'wednesday', 'thursday',
        'friday', 'saturday', 'sunday', 'january', 'february', 'march',
        'april', 'may', 'june', 'july', 'august', 'september', 'october',
        'november', 'december', 'reset', 'cancel', 'stop', 'start',
        'book', 'appointment', 'doctor', 'clinic', 'please', 'thank',
        'thanks', 'great', 'good', 'morning', 'afternoon', 'evening',
    }
    words_lower = {w.lower() for w in text.split()}
    if words_lower & non_names:
        return False
    if len(text.strip()) < 2:
        return False
    return True


def extract_date(text):
    """Extract a date from natural language."""
    # Try dateparser first — handles 'tomorrow', 'next Monday', 'March 25th' etc.
    try:
        parsed = dateparser.parse(
            text,
            settings={
                'PREFER_DATES_FROM': 'future',
                'RETURN_AS_TIMEZONE_AWARE': False,
            }
        )
        if parsed and parsed.date() >= date.today():
            return parsed.strftime('%Y-%m-%d')
    except Exception:
        pass

    # Fallback: explicit YYYY-MM-DD in the text
    m = re.search(r'(\d{4}[-/]\d{2}[-/]\d{2})', text)
    if m:
        return m.group(1).replace('/', '-')

    return None


def extract_time(text):
    """Extract a time like '9am', '2:30 pm', '14:00', 'half past two'."""
    t = text.lower().strip()

    # Match standard time patterns: 9am, 9:30am, 2:30 pm, 14:00
    m = re.search(r'\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b', t)
    if m:
        h    = int(m.group(1))
        mins = m.group(2) or '00'
        ampm = m.group(3)

        # Reject if hour is implausible (e.g. years like 2025)
        if h > 23:
            return None

        # AM/PM conversion
        if ampm == 'pm' and h < 12:
            h += 12
        elif ampm == 'am' and h == 12:
            h = 0

        # If no AM/PM given, assume business hours (8–18)
        if not ampm and h < 8:
            h += 12   # '9' without context → probably 9am; '2' → 2pm

        # Clamp to valid time
        if 0 <= h <= 23:
            return f'{h:02d}:{mins}'

    return None


def is_affirmative(text):
    words = ['yes', 'yeah', 'yep', 'yup', 'correct', 'sure', 'ok',
             'okay', 'confirm', 'confirmed', 'right', 'absolutely', 'go ahead']
    return any(w in text.lower() for w in words)


def is_negative(text):
    words = ['no', 'nope', 'nah', 'cancel', 'stop', 'dont', "don't",
             'wrong', 'incorrect', 'abort']
    return any(w in text.lower() for w in words)