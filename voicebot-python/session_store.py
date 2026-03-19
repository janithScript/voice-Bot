# Simple in-memory session storage
# Each session tracks what info has been collected
sessions = {}

def get_session(session_id):
    if session_id not in sessions:
        sessions[session_id] = {
            'step': 'ask_name',   # Current step in the booking flow
            'patient_name': None,
            'date': None,
            'time': None,
            'doctor_id': 1,       # Default doctor
        }
    return sessions[session_id]

def update_session(session_id, key, value):
    sessions[session_id][key] = value

def reset_session(session_id):
    if session_id in sessions:
        del sessions[session_id]
