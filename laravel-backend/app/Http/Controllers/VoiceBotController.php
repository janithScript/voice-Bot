<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\Doctor;
use App\Models\Appointment;

class VoiceBotController extends Controller
{
    // Renders the main voicebot UI page
    public function index() {
        $doctors = Doctor::where('is_active', true)->get();
        return view('voicebot.index', compact('doctors'));
    }

    // Receives transcribed text from JS, sends to Python NLP
    public function processVoice(Request $request) {
        $request->validate(['text' => 'required|string|max:500']);

        $client = new Client(['timeout' => 10.0]);
        try {
            $response = $client->post(env('PYTHON_VOICEBOT_URL') . '/process', [
                'json' => [
                    'text'    => $request->text,
                    'session' => $request->session_id ?? session()->getId(),
                ]
            ]);
            $result = json_decode($response->getBody(), true);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'VoiceBot service unavailable: ' . $e->getMessage()], 503);
        }
    }

    // Saves the final appointment from the voicebot flow
    public function bookAppointment(Request $request) {
        $data = $request->validate([
            'patient_name'     => 'required|string|max:100',
            'phone'            => 'nullable|string|max:20',
            'doctor_id'        => 'required|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
        ]);
        $data['booking_source'] = 'voicebot';
        $appointment = Appointment::create($data);
        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'appointment_id' => $appointment->id,
        ]);
    }

    // Returns available slots for a given doctor and date
    public function getSlots(Request $request) {
        $request->validate(['doctor_id'=>'required','date'=>'required|date']);
        $booked = Appointment::where('doctor_id', $request->doctor_id)
            ->where('appointment_date', $request->date)
            ->where('status','!=','cancelled')
            ->pluck('appointment_time')
            ->map(fn($t) => substr($t,0,5))
            ->toArray();
        $allSlots = ['09:00','09:30','10:00','10:30','11:00','11:30',
                     '14:00','14:30','15:00','15:30','16:00','16:30'];
        $available = array_values(array_filter($allSlots, fn($s) => !in_array($s,$booked)));
        return response()->json(['slots' => $available]);
    }
}
