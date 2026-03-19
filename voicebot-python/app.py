from email.mime import text

from flask import Flask, request, jsonify
from flask_cors import CORS
from session_store import get_session, update_session, reset_session
from nlp_processor import extract_name, extract_date, extract_time, is_affirmative, is_negative

app = Flask(__name__)
CORS(app)  # Allow requests from Laravel

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'service': 'clinic-voicebot'})

@app.route('/process', methods=['POST'])
def process():
    data    = request.get_json()
    text    = data.get('text','').strip()
    sid     = data.get('session', 'default')
    session = get_session(sid)
    step    = session['step']

    # ── Allow user to say "reset" or "start over" at any point
    if any(w in text.lower() for w in ['reset', 'start over', 'restart', 'cancel all']):
        reset_session(sid)
        return jsonify({'message': 'Session reset! Please tell me your name to start a new booking.'})

    # ── STEP 1: Collect patient name
    if step == 'ask_name':
        name = extract_name(text)
        if name:
            update_session(sid, 'patient_name', name)
            update_session(sid, 'step', 'ask_date')
            return jsonify({'message': f'Nice to meet you, {name}! What date would you like to book? (e.g. tomorrow, next Monday, or 2025-03-20)'})
        return jsonify({'message': 'I did not catch your name. Could you please tell me your full name?'})

    # ── STEP 2: Collect date
    elif step == 'ask_date':
        d = extract_date(text)
        if d:
            update_session(sid, 'date', d)
            update_session(sid, 'step', 'ask_time')
            return jsonify({'message': f'Great! And what time would you prefer on {d}? (e.g. 9am, 2:30pm)'})
        return jsonify({'message': 'I could not understand the date. Please say something like "tomorrow" or "March 25th".'})

    # ── STEP 3: Collect time
    elif step == 'ask_time':
        t = extract_time(text)
        if t:
            update_session(sid, 'time', t)
            update_session(sid, 'step', 'confirm')
            s = session
            return jsonify({
                'message': f'Perfect! Let me confirm: {s["patient_name"]} on {s["date"]} at {t}. Please confirm in the form below.',
                'action': 'show_booking_form',
                'patient_name': s['patient_name'],
                'date': s['date'],
                'time': t,
                'doctor_id': s['doctor_id'],
            })
        return jsonify({'message': 'I did not catch the time. Please say something like "10am" or "2:30 pm".'})

    # ── STEP 4: After booking confirmation
    elif step == 'confirm':
        if is_affirmative(text):
            reset_session(sid)
            return jsonify({'message': 'Your appointment has been booked! Is there anything else I can help you with?', 'action': 'booked'})
        elif is_negative(text):
            reset_session(sid)
            return jsonify({'message': 'No problem! Booking cancelled. Say your name to start again.'})
        return jsonify({'message': 'Please confirm by saying Yes or No.'})

    return jsonify({'message': 'Sorry, I did not understand. Please try again.'})

@app.route('/reset', methods=['POST'])
def reset():
    sid = request.get_json().get('session', 'default')
    reset_session(sid)
    return jsonify({'message': 'Session reset. Say your name to start.'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
