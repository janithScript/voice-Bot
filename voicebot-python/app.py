from flask import Flask, request, jsonify
from flask_cors import CORS
from session_store import get_session, update_session, reset_session
from nlp_processor import extract_name, extract_date, extract_time, is_affirmative, is_negative

app = Flask(__name__)
CORS(app)


@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'service': 'clinic-voicebot'})


@app.route('/process', methods=['POST'])
def process():
    body = request.get_json(silent=True)
    if not body:
        return jsonify({'message': 'Invalid request.'}), 400

    user_text = body.get('text', '').strip()
    sid       = body.get('session', 'default')
    session   = get_session(sid)
    step      = session['step']

    # ── Global reset command — works at any step
    if any(w in user_text.lower() for w in ['reset', 'start over', 'restart', 'cancel all', 'begin again']):
        reset_session(sid)
        return jsonify({'message': 'Session reset! Please tell me your name to start a new booking.'})

    # ── STEP 1: Get patient name
    if step == 'ask_name':
        name = extract_name(user_text)
        if name:
            update_session(sid, 'patient_name', name)
            update_session(sid, 'step', 'ask_date')
            return jsonify({
                'message': f"Nice to meet you, {name}! What date would you like to book? "
                           f"You can say 'tomorrow', 'next Monday', or a specific date like 'March 25th'."
            })
        return jsonify({'message': "I didn't catch your name. Could you please say your full name?"})

    # ── STEP 2: Get appointment date
    elif step == 'ask_date':
        d = extract_date(user_text)
        if d:
            update_session(sid, 'date', d)
            update_session(sid, 'step', 'ask_time')
            return jsonify({
                'message': f"Great! And what time would you prefer on {d}? "
                           f"For example: '9am', '2:30pm', or '14:00'."
            })
        return jsonify({
            'message': "I couldn't understand that date. Try saying 'tomorrow', 'next Friday', "
                       "or a date like 'March 25th'."
        })

    # ── STEP 3: Get appointment time
    elif step == 'ask_time':
        t = extract_time(user_text)
        if t:
            update_session(sid, 'time', t)
            update_session(sid, 'step', 'confirm')
            s = get_session(sid)   # re-fetch to get all values
            return jsonify({
                'message':      f"Perfect! I have {s['patient_name']} on {s['date']} at {t}. "
                                f"Please confirm in the form below.",
                'action':       'show_booking_form',
                'patient_name': s['patient_name'],
                'date':         s['date'],
                'time':         t,
                'doctor_id':    s['doctor_id'],
            })
        return jsonify({
            'message': "I didn't catch the time. Please say something like '10am', '2:30pm', or '3 o'clock'."
        })

    # ── STEP 4: Confirm or cancel
    elif step == 'confirm':
        if is_affirmative(user_text):
            reset_session(sid)
            return jsonify({
                'message': 'Great! Your appointment details are confirmed in the form. '
                           'Press the green Confirm Booking button to save it.',
                'action':  'booked'
            })
        elif is_negative(user_text):
            reset_session(sid)
            return jsonify({
                'message': 'No problem — booking cancelled. Say your name whenever you want to start again.'
            })
        return jsonify({'message': "Please say 'yes' to confirm or 'no' to cancel."})

    return jsonify({'message': "Sorry, I didn't understand that. Please try again."})


@app.route('/reset', methods=['POST'])
def reset_route():
    body = request.get_json(silent=True) or {}
    sid  = body.get('session', 'default')
    reset_session(sid)
    return jsonify({'message': 'Session reset. Say your name to start.'})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)