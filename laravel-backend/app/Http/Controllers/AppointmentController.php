<?php
namespace App\Http\Controllers;
use App\Models\Appointment;

class AppointmentController extends Controller {
    public function index() {
        $appointments = Appointment::with('doctor')
            ->orderBy('appointment_date','desc')
            ->paginate(15);
        return view('appointments.index', compact('appointments'));
    }
}
