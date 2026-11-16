@extends('layouts.admin')

@section('title', 'Edit Plan')
@section('form_page', '1')

@section('content')
    @include('admin.plans._form', [
        'plan' => $plan,
        'action' => route('admin.plans.update', $plan),
        'method' => 'PUT',
        'slugLocked' => $enrolmentsCount > 0,
        'enrolmentsCount' => $enrolmentsCount,
        'title' => 'Edit Saving Scheme — '.$plan->name,
        'back' => route('admin.plans.show', $plan),
        'backLabel' => 'Back to Scheme',
    ])
@endsection
