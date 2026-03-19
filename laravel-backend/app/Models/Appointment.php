<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model {
    protected $fillable = [
        'patient_name','phone','email','doctor_id',
        'appointment_date','appointment_time','status','notes','booking_source'
    ];

    protected $casts = ['appointment_date' => 'date'];

    public function doctor() {
        return $this->belongsTo(Doctor::class);
    }
}

