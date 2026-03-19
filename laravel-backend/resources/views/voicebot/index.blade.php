@extends('layouts.app')
@section('title', 'Book Appointment - Voice')
@push('styles')
<style>
  .mic-btn { width:80px;height:80px;border-radius:50%;font-size:2rem;
             transition:all .3s;border:none;background:#0d6efd;color:#fff; }
  .mic-btn.recording { background:#dc3545;animation:pulse 1s infinite; }
  @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
  .chat-box { height:300px;overflow-y:auto;background:#f8f9fa;
              border:1px solid #dee2e6;border-radius:8px;padding:15px; }
  .msg-bot { background:#e3f2fd;border-radius:8px;padding:8px 12px;margin:4px 0; }
  .msg-user { background:#d4edda;border-radius:8px;padding:8px 12px;
              margin:4px 0;text-align:right; }
</style>
@endpush
@section('content')
<div class="row justify-content-center">
 <div class="col-md-8">
  <div class="card shadow">
   <div class="card-header bg-primary text-white text-center">
    <h4><i class="fas fa-microphone me-2"></i>Voice Appointment Booking</h4>
   </div>
   <div class="card-body">
    <!-- Chat display -->
    <div class="chat-box mb-3" id="chatBox">
     <div class="msg-bot"><strong>Bot:</strong> Hello! I can help you book an appointment.
      Please press the microphone and say your name to get started.</div>
    </div>
    <!-- Voice controls -->
    <div class="text-center mb-3">
        <button class="mic-btn" id="micBtn" type="button">
            <i class="fas fa-microphone" id="micIcon"></i>
        </button>
        <p class="mt-2 text-muted" id="statusText">Press to speak</p>
    </div>
    <!-- Text input fallback -->
    <div class="input-group mb-3">
     <input type="text" class="form-control" id="textInput"
            placeholder="Or type here if microphone unavailable...">
     <button class="btn btn-primary" onclick="sendText()">Send</button>
    </div>
    <!-- Booking form (shown when bot collects all info) -->
    <div id="bookingForm" style="display:none">
     <hr><h5>Confirm Appointment</h5>
     <form id="finalForm">
      <div class="mb-2">
       <label>Patient Name</label>
       <input type="text" class="form-control" id="f_name" readonly>
      </div>
      <div class="mb-2">
       <label>Doctor</label>
       <select class="form-control" id="f_doctor">
        @foreach($doctors as $d)
        <option value="{{ $d->id }}">{{ $d->name }} - {{ $d->specialization }}</option>
        @endforeach
       </select>
      </div>
      <div class="mb-2">
       <label>Date</label>
       <input type="date" class="form-control" id="f_date" min="{{ date('Y-m-d') }}">
      </div>
      <div class="mb-2">
       <label>Time Slot</label>
       <select class="form-control" id="f_time"></select>
      </div>
      <button type="button" class="btn btn-success w-100" onclick="confirmBooking()">
       <i class="fas fa-check me-2"></i>Confirm Booking
      </button>
     </form>
    </div>
   </div>
  </div>
 </div>
</div>
@endsection
@push('scripts')
<script>
// ── State
let recognition  = null;
let holdActive   = false;
let buffer       = [];        // accumulates every phrase spoken during one hold
const csrf = document.querySelector('meta[name=csrf-token]').content;

// ────────────────────────────────────────────────────────────
// STEP 1 — Init speech (called once on DOMContentLoaded)
// KEY CHANGE: continuous = FALSE — this is what fixes the
// "network" error. continuous=true requires HTTPS.
// We simulate "hold to talk" by restarting recognition
// automatically each time it ends while the button is held.
// ────────────────────────────────────────────────────────────
function initSpeech() {
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) {
    document.getElementById('micBtn').disabled = true;
    addMessage('bot', 'Microphone not supported in this browser. Use Chrome or Edge, or type below.');
    return;
  }

  recognition = new SR();
  recognition.lang           = 'en-US';
  recognition.continuous     = false;   // MUST be false on http://localhost
  recognition.interimResults = true;    // lets us show live feedback while user speaks

  // ── Each time speech recognition produces a result
  recognition.onresult = function(event) {
    let interimText = '';
    for (let i = event.resultIndex; i < event.results.length; i++) {
      const transcript = event.results[i][0].transcript.trim();
      if (event.results[i].isFinal) {
        // Final phrase — add to buffer
        buffer.push(transcript);
        document.getElementById('statusText').textContent = 'Got: "' + transcript + '" — keep holding…';
      } else {
        // Interim — show live feedback only
        interimText = transcript;
        if (interimText) {
          document.getElementById('statusText').textContent = 'Hearing: "' + interimText + '"';
        }
      }
    }
  };

  // ── Errors
  recognition.onerror = function(event) {
    // no-speech = silence, not a real failure — just restart if still holding
    if (event.error === 'no-speech') {
      if (holdActive) restartRecognition();
      return;
    }
    // aborted = we called stop() ourselves — not an error
    if (event.error === 'aborted') return;

    // Real error — only show if user was actively holding
    if (holdActive) {
      stopHold();
      addMessage('bot', 'Microphone error (' + event.error + '). Check browser mic permissions.');
    }
  };

  // ── When one recognition session ends naturally
  // If the button is still held, restart immediately to keep listening
  recognition.onend = function() {
    if (holdActive) {
      restartRecognition();
    }
  };
}

// Safe restart — prevents InvalidStateError if called too quickly
function restartRecognition() {
  try {
    recognition.start();
  } catch (e) {
    // Already started — ignore
  }
}

// ────────────────────────────────────────────────────────────
// STEP 2 — Button hold start (mousedown / touchstart)
// ────────────────────────────────────────────────────────────
function startHold() {
  if (!recognition) {
    addMessage('bot', 'Speech API not ready. Please refresh the page.');
    return;
  }
  holdActive = true;
  buffer     = [];

  document.getElementById('micBtn').classList.add('recording');
  document.getElementById('micIcon').className  = 'fas fa-stop';
  document.getElementById('statusText').textContent = 'Listening… release when done';

  try {
    recognition.start();
  } catch (e) {
    // If recognition is already running from a previous session, abort it first
    recognition.abort();
    setTimeout(function() { try { recognition.start(); } catch(e2){} }, 150);
  }
}

// ────────────────────────────────────────────────────────────
// STEP 3 — Button release (mouseup / touchend)
// Stop listening, join everything in the buffer, send to bot
// ────────────────────────────────────────────────────────────
function stopHold() {
  if (!holdActive) return;
  holdActive = false;

  document.getElementById('micBtn').classList.remove('recording');
  document.getElementById('micIcon').className  = 'fas fa-microphone';
  document.getElementById('statusText').textContent = 'Press to speak';

  // Stop recognition — this will trigger onend but holdActive is now false
  // so it will NOT restart
  try { recognition.stop(); } catch (e) {}

  // Small delay to let the final onresult fire before we read the buffer
  setTimeout(function() {
    const fullText = buffer.join(' ').trim();
    buffer = [];
    if (fullText) {
      sendToBot(fullText);
    }
    // If nothing captured — stay silent, no error message
  }, 300);
}

// ────────────────────────────────────────────────────────────
// Typed text fallback
// ────────────────────────────────────────────────────────────
function sendText() {
  const val = document.getElementById('textInput').value.trim();
  if (!val) return;
  document.getElementById('textInput').value = '';
  sendToBot(val);
}

// ────────────────────────────────────────────────────────────
// Send text to Laravel → Python and display reply
// ────────────────────────────────────────────────────────────
async function sendToBot(text) {
  addMessage('user', text);
  try {
    const resp = await fetch('/api/voice/process', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body:    JSON.stringify({ text: text, session_id: getSession() })
    });
    const data = await resp.json();
    if (data.error) {
      addMessage('bot', 'Service error: ' + data.error);
      return;
    }
    addMessage('bot', data.message);
    if (data.action === 'show_booking_form') showBookingForm(data);
    if (data.action === 'booked')            localStorage.removeItem('voiceSession');
  } catch (e) {
    addMessage('bot', 'Cannot reach the server. Is php artisan serve running?');
  }
}

// ────────────────────────────────────────────────────────────
// Chat display
// ────────────────────────────────────────────────────────────
function addMessage(who, text) {
  const box = document.getElementById('chatBox');
  const div = document.createElement('div');
  div.className = (who === 'bot') ? 'msg-bot' : 'msg-user';
  div.innerHTML  = '<strong>' + (who === 'bot' ? 'Bot' : 'You') + ':</strong> ' + text;
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

// ────────────────────────────────────────────────────────────
// Booking form
// ────────────────────────────────────────────────────────────
function showBookingForm(data) {
  document.getElementById('bookingForm').style.display = 'block';
  if (data.patient_name) document.getElementById('f_name').value  = data.patient_name;
  if (data.date)         document.getElementById('f_date').value  = data.date;
  loadSlots();
  document.getElementById('f_date').addEventListener('change',   loadSlots);
  document.getElementById('f_doctor').addEventListener('change', loadSlots);
}

async function loadSlots() {
  const doctorId = document.getElementById('f_doctor').value;
  const date     = document.getElementById('f_date').value;
  if (!date || !doctorId) return;
  try {
    const resp = await fetch('/api/slots?doctor_id=' + doctorId + '&date=' + date);
    const data = await resp.json();
    const sel  = document.getElementById('f_time');
    sel.innerHTML = '';
    if (!data.slots || data.slots.length === 0) {
      sel.innerHTML = '<option value="">No slots available</option>';
      return;
    }
    data.slots.forEach(function(s) {
      const opt = document.createElement('option');
      opt.value = s; opt.textContent = s;
      sel.appendChild(opt);
    });
  } catch(e) { console.error('loadSlots error', e); }
}

async function confirmBooking() {
  const payload = {
    patient_name:     document.getElementById('f_name').value,
    doctor_id:        document.getElementById('f_doctor').value,
    appointment_date: document.getElementById('f_date').value,
    appointment_time: document.getElementById('f_time').value,
  };
  if (!payload.patient_name || !payload.appointment_date || !payload.appointment_time) {
    addMessage('bot', 'Please fill in all the fields before confirming.');
    return;
  }
  try {
    const resp = await fetch('/api/appointment/book', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body:    JSON.stringify(payload)
    });
    const data = await resp.json();
    if (data.success) {
      addMessage('bot', data.message + ' (Appointment ID: ' + data.appointment_id + ')');
      document.getElementById('bookingForm').style.display = 'none';
      localStorage.removeItem('voiceSession');
    } else {
      addMessage('bot', 'Booking failed. Please check all fields and try again.');
    }
  } catch(e) {
    addMessage('bot', 'Could not reach the booking server.');
  }
}

// ────────────────────────────────────────────────────────────
// Session helpers
// ────────────────────────────────────────────────────────────
function getSession() {
  if (!localStorage.voiceSession) localStorage.voiceSession = 'sess_' + Date.now();
  return localStorage.voiceSession;
}

// ────────────────────────────────────────────────────────────
// Wire everything on page load
// ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  initSpeech();

  const btn = document.getElementById('micBtn');

  // Desktop mouse
  btn.addEventListener('mousedown',  function(e) { e.preventDefault(); startHold(); });
  btn.addEventListener('mouseup',    function(e) { e.preventDefault(); stopHold();  });
  btn.addEventListener('mouseleave', function()  { if (holdActive) stopHold();       });

  // Mobile touch
  btn.addEventListener('touchstart',  function(e) { e.preventDefault(); startHold(); }, { passive: false });
  btn.addEventListener('touchend',    function(e) { e.preventDefault(); stopHold();  }, { passive: false });
  btn.addEventListener('touchcancel', function()  { if (holdActive) stopHold();       });

  // Enter key in text box
  document.getElementById('textInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') sendText();
  });
});
</script>
@endpush
