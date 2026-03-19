@extends('layouts.app')
@section('title','All Appointments')
@section('content')
<h3>All Appointments</h3>
<table class="table table-striped">
 <thead><tr>
  <th>#</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th><th>Source</th>
 </tr></thead>
 <tbody>
  @foreach($appointments as $a)
  <tr>
   <td>{{ $a->id }}</td>
   <td>{{ $a->patient_name }}</td>
   <td>{{ $a->doctor->name ?? 'N/A' }}</td>
   <td>{{ $a->appointment_date->format('d M Y') }}</td>
   <td>{{ $a->appointment_time }}</td>
   <td><span class="badge bg-{{ $a->status==='confirmed'?'success':($a->status==='cancelled'?'danger':'warning') }}">
       {{ ucfirst($a->status) }}</span></td>
   <td>{{ $a->booking_source }}</td>
  </tr>
  @endforeach
 </tbody>
</table>
{{ $appointments->links() }}
@endsection
