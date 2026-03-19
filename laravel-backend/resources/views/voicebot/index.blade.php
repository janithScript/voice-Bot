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
// ── State variables
let recognition = null;
let holdActive = false;          // true only while button is physically held
let transcriptBuffer = [];       // collects interim results during a hold
let bookingData = {};
const csrf = document.querySelector('meta[name=csrf-token]').content;

// ── STEP 1: Initialize Web Speech API once on page load
function initSpeech() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    document.getElementById('micBtn').disabled = true;
    addMessage('bot', 'Microphone not supported. Please use the text input below.');
    return;
  }

  recognition = new SpeechRecognition();
  recognition.lang = 'en-US';
  recognition.continuous = true;       // keep listening while held
  recognition.interimResults = false;  // only fire onresult when a phrase is complete

  // ── Collect every phrase into the buffer while holding
  recognition.onresult = function(e) {
    for (let i = e.resultIndex; i < e.results.length; i++) {
      if (e.results[i].isFinal) {
        transcriptBuffer.push(e.results[i][0].transcript.trim());
      }
    }
  };

  // ── CRITICAL FIX: only show error after the user was actively holding
  recognition.onerror = function(e) {
    // 'no-speech' fires immediately if the API warms up before sound — ignore it
    // Only show an error if the hold was long enough to expect a result
    if (!holdActive) return;
    if (e.error === 'no-speech') return; // silent — restart will handle it
    stopHold();
    addMessage('bot', 'Microphone error: ' + e.error + '. Please try again.');
  };

  // ── CRITICAL FIX: if still holding when recognition ends, restart it silently
  // This prevents the browser's ~5s timeout from cutting you off mid-sentence
  recognition.onend = function() {
    if (holdActive) {
      try { recognition.start(); } catch(err) { /* already started */ }
    }
  };
}

// ── STEP 2: Start listening on button press (mousedown / touchstart)
function startHold() {
  if (!recognition) return;
  holdActive = true;
  transcriptBuffer = [];
  document.getElementById('micBtn').classList.add('recording');
  document.getElementById('micIcon').className = 'fas fa-stop';
  document.getElementById('statusText').textContent = 'Listening… release to send';
  try { recognition.start(); } catch(err) { /* already started from a previous onend restart */ }
}

// ── STEP 3: Stop listening and send when button is released (mouseup / touchend)
function stopHold() {
  if (!holdActive) return;
  holdActive = false;
  document.getElementById('micBtn').classList.remove('recording');
  document.getElementById('micIcon').className = 'fas fa-microphone';
  document.getElementById('statusText').textContent = 'Press to speak';
  try { recognition.stop(); } catch(err) {}

  // Send whatever was collected during the hold
  const fullText = transcriptBuffer.join(' ').trim();
  transcriptBuffer = [];
  if (fullText) {
    sendToBot(fullText);
  }
  // If nothing was captured, stay silent — no "Could not hear you"
}

// ── Text box fallback — unchanged behaviour
function sendText() {
  const t = document.getElementById('textInput').value.trim();
  if (!t) return;
  document.getElementById('textInput').value = '';
  sendToBot(t);
}

// ── Send to Python via Laravel and display response
async function sendToBot(text) {
  addMessage('user', text);
  try {
    const res = await fetch('/api/voice/process', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ text: text, session_id: getSession() })
    });
    const data = await res.json();
    if (data.error) {
      addMessage('bot', 'Service error: ' + data.error);
      return;
    }
    addMessage('bot', data.message);
    if (data.action === 'show_booking_form') showBookingForm(data);
    if (data.action === 'booked') {
      // Reset local session so the next booking starts fresh
      localStorage.removeItem('voiceSession');
    }
  } catch(e) {
    addMessage('bot', 'Error connecting to server. Is the Python service running?');
  }
}

// ── Chat display helper
function addMessage(who, text) {
  const box = document.getElementById('chatBox');
  const div = document.createElement('div');
  div.className = who === 'bot' ? 'msg-bot' : 'msg-user';
  div.innerHTML = '<strong>' + (who === 'bot' ? 'Bot' : 'You') + ':</strong> ' + text;
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

// ── Reveal the booking confirmation form
function showBookingForm(data) {
  document.getElementById('bookingForm').style.display = 'block';
  if (data.patient_name) document.getElementById('f_name').value = data.patient_name;
  if (data.date)         document.getElementById('f_date').value = data.date;
  loadSlots();
  document.getElementById('f_date').addEventListener('change', loadSlots);
  document.getElementById('f_doctor').addEventListener('change', loadSlots);
}

// ── Load available time slots from Laravel
async function loadSlots() {
  const doctorId = document.getElementById('f_doctor').value;
  const date     = document.getElementById('f_date').value;
  if (!date || !doctorId) return;
  const res  = await fetch('/api/slots?doctor_id=' + doctorId + '&date=' + date);
  const data = await res.json();
  const sel  = document.getElementById('f_time');
  sel.innerHTML = '';
  if (!data.slots || data.slots.length === 0) {
    sel.innerHTML = '<option>No slots available</option>';
    return;
  }
  data.slots.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s; opt.textContent = s;
    sel.appendChild(opt);
  });
}

// ── Confirm booking via Laravel API
async function confirmBooking() {
  const payload = {
    patient_name:     document.getElementById('f_name').value,
    doctor_id:        document.getElementById('f_doctor').value,
    appointment_date: document.getElementById('f_date').value,
    appointment_time: document.getElementById('f_time').value,
  };
  if (!payload.patient_name || !payload.appointment_date || !payload.appointment_time) {
    addMessage('bot', 'Please fill in all fields before confirming.');
    return;
  }
  const res  = await fetch('/api/appointment/book', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (data.success) {
    addMessage('bot', data.message + ' (Appointment ID: ' + data.appointment_id + ')');
    document.getElementById('bookingForm').style.display = 'none';
    localStorage.removeItem('voiceSession');
  } else {
    addMessage('bot', 'Booking failed. Please check the form and try again.');
  }
}

// ── Per-tab session token
function getSession() {
  if (!localStorage.voiceSession) localStorage.voiceSession = 'sess_' + Date.now();
  return localStorage.voiceSession;
}

// ── Wire up the button on page load
document.addEventListener('DOMContentLoaded', function() {
  initSpeech();

  const btn = document.getElementById('micBtn');

  // Desktop: hold mouse button
  btn.addEventListener('mousedown',  function(e) { e.preventDefault(); startHold(); });
  btn.addEventListener('mouseup',    function(e) { e.preventDefault(); stopHold(); });
  btn.addEventListener('mouseleave', function(e) { if (holdActive) stopHold(); }); // release if mouse drifts off

  // Mobile: hold touch
  btn.addEventListener('touchstart', function(e) { e.preventDefault(); startHold(); }, { passive: false });
  btn.addEventListener('touchend',   function(e) { e.preventDefault(); stopHold(); },  { passive: false });
  btn.addEventListener('touchcancel',function(e) { if (holdActive) stopHold(); });

  // Enter key in text box
  document.getElementById('textInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') sendText();
  });
});
</script>
@endpush
